<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Product images live on the application's media disk under products/ (Supabase in production,
 * the public disk locally), so they are persistent and included in Full Backups.
 *
 * Legacy rows store "uploads/products/<file>" (the old public_path folder). Those references are
 * kept unchanged and still resolve: to the legacy file while it exists, otherwise to the same file
 * under products/ on the media disk (after products:migrate-images has copied it).
 */
class ProductImageStorage
{
    public function __construct(private StorageDiagnosticsService $diagnostics)
    {
    }

    public function diskName(): string
    {
        return $this->diagnostics->getActiveDiskName();
    }

    public function disk()
    {
        return Storage::disk($this->diskName());
    }

    /** Stores an uploaded image on the media disk and returns the value for products.image_path. */
    public function store(UploadedFile $file): string
    {
        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        $ext = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $ext : 'jpg';
        $name = time() . '_' . uniqid() . '.' . $ext;

        $this->disk()->putFileAs('products', $file, $name, ['visibility' => 'public']);

        return 'products/' . $name;
    }

    /** Media-disk key (products/<file>) for a stored value, or null if it is not a product image path. */
    public function mediaKey(?string $path): ?string
    {
        $clean = ltrim(trim((string) $path), '/');
        if (preg_match('#^uploads/products/([^/]+)$#', $clean, $m) || preg_match('#^products/([^/]+)$#', $clean, $m)) {
            return 'products/' . $m[1];
        }

        return null;
    }

    public function url(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $clean = ltrim($path, '/');
        $key = $this->mediaKey($clean);

        if ($key !== null) {
            // Legacy location still present on this server: keep serving it.
            if (str_starts_with($clean, 'uploads/') && file_exists(public_path($clean))) {
                return asset($clean);
            }

            $disk = $this->disk();
            $isLocal = $this->isLocalDisk();
            if (!$isLocal || $disk->exists($key)) {
                return $this->diskUrl($key);
            }
            if (file_exists(public_path($clean))) {
                return asset($clean);
            }

            return null;
        }

        // Anything else: previous behaviour.
        if (file_exists(public_path($clean))) {
            return asset($clean);
        }
        if (file_exists(public_path('storage/' . $clean))) {
            return asset('storage/' . $clean);
        }
        if (Storage::disk('public')->exists($clean)) {
            return Storage::disk('public')->url($clean);
        }

        return null;
    }

    /** Removes the stored image (legacy file and/or media object). Never throws. */
    public function delete(?string $path): void
    {
        $clean = ltrim(trim((string) $path), '/');
        if ($clean === '' || str_starts_with($clean, 'http')) {
            return;
        }

        try {
            if (str_starts_with($clean, 'uploads/') && file_exists(public_path($clean))) {
                @unlink(public_path($clean));
            }
            $key = $this->mediaKey($clean);
            if ($key !== null) {
                $this->disk()->delete($key);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function isLocalDisk(): bool
    {
        return (string) config('filesystems.disks.' . $this->diskName() . '.driver', 'local') === 'local';
    }

    private function diskUrl(string $key): string
    {
        $url = $this->disk()->url($key);
        if (str_contains($url, '.storage.supabase.co/storage/v1/s3/')) {
            $url = str_replace('.storage.supabase.co/storage/v1/s3/', '.supabase.co/storage/v1/object/public/', $url);
        }

        return $url;
    }
}
