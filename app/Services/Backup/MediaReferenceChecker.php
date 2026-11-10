<?php

namespace App\Services\Backup;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compares media paths referenced by the database with the objects captured by the media backup.
 * Reports missing references and orphaned objects so inconsistencies are visible, not silent.
 */
class MediaReferenceChecker
{
    private const PREFIXES = 'logos|avatars|products|categories|receipts';

    /**
     * @return array<string,array<int,string>> normalised media key => where it is referenced
     */
    public function collectReferences(?string $connection = null): array
    {
        $refs = [];
        $external = 0;
        $add = function (?string $raw, string $source) use (&$refs, &$external) {
            $key = $this->normalize($raw);
            if ($key === null) {
                if (!empty($raw)) {
                    $external++;
                }

                return;
            }
            $refs[$key][] = $source;
        };

        $db = DB::connection($connection);

        foreach ($db->table('settings')->where(function ($q) {
            $q->where('key', 'shop_logo')->orWhere('key', 'admin_avatar')->orWhere('key', 'like', 'user_avatar_%');
        })->get(['key', 'value']) as $row) {
            $add($row->value, 'settings.' . $row->key);
        }

        if (Schema::connection($connection)->hasTable('products') && Schema::connection($connection)->hasColumn('products', 'image_path')) {
            foreach ($db->table('products')->whereNotNull('image_path')->get(['id', 'image_path']) as $row) {
                $add($row->image_path, 'products.image_path#' . $row->id);
            }
        }

        $this->external = $external;

        return $refs;
    }

    private int $external = 0;

    /** Map a stored value (path or URL) to a media-disk key, or null when it is not an app media object. */
    public function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Legacy product uploads: uploads/products/x.png -> products/x.png
        if (preg_match('#^/?uploads/products/(.+)$#', $value, $m)) {
            return 'products/' . $m[1];
        }

        $path = $value;
        if (preg_match('#^https?://#i', $value)) {
            $path = (string) parse_url($value, PHP_URL_PATH);
        }

        // /storage/logos/x, /storage/v1/object/public/<bucket>/logos/x, bucket/logos/x ...
        if (preg_match('#(?:^|/)((?:' . self::PREFIXES . ')/[^?\#]+)$#', $path, $m)) {
            return $m[1];
        }

        return null;
    }

    public function externalCount(): int
    {
        return $this->external;
    }

    /**
     * @param array<int,array<string,mixed>> $manifestObjects entries with a "path"
     * @return array{references:int,missing:array<int,array{path:string,sources:array<int,string>}>,orphans:int,orphan_sample:array<int,string>,external:int}
     */
    public function check(array $manifestObjects, ?string $connection = null): array
    {
        return $this->compare($this->collectReferences($connection), $manifestObjects);
    }

    /**
     * Compare already collected references with a set of present objects (paths).
     *
     * @param array<string,array<int,string>> $refs
     * @param array<int,array<string,mixed>> $manifestObjects
     * @return array{references:int,missing:array<int,array{path:string,sources:array<int,string>}>,orphans:int,orphan_sample:array<int,string>,external:int}
     */
    public function compare(array $refs, array $manifestObjects): array
    {
        $present = array_flip(array_column($manifestObjects, 'path'));

        $missing = [];
        foreach ($refs as $key => $sources) {
            if (!isset($present[$key])) {
                $missing[] = ['path' => $key, 'sources' => array_values(array_unique($sources))];
            }
        }

        $orphans = array_values(array_diff(array_keys($present), array_keys($refs)));
        // Objects under categories/ and receipts/ are not referenced by any tracked column.
        $orphans = array_values(array_filter($orphans, fn ($p) => !str_starts_with($p, 'categories/') && !str_starts_with($p, 'receipts/')));

        return [
            'references' => count($refs),
            'missing' => $missing,
            'orphans' => count($orphans),
            'orphan_sample' => array_slice($orphans, 0, 10),
            'orphan_paths' => $orphans,
            'external' => $this->external,
        ];
    }
}
