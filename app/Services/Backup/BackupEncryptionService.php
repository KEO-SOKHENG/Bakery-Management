<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Streaming authenticated encryption (libsodium secretstream XChaCha20-Poly1305).
 * Large files are processed in 1 MB chunks, never loaded into memory.
 *
 * Format v2:  "BKPENC2\n" | 8-byte key id | 24-byte stream header | repeated [4-byte BE length | ciphertext chunk]
 * Format v1:  "BKPENC1\n" |                24-byte stream header | repeated [...]   (still readable)
 *
 * Keys:
 *  - Encryption uses BACKUP_ENCRYPTION_KEY when configured, otherwise APP_KEY.
 *  - Decryption tries, in order: the dedicated key, APP_KEY, Laravel's previous APP keys
 *    (app.previous_keys) and BACKUP_PREVIOUS_ENCRYPTION_KEYS. The key id in the header selects the
 *    right one without trial decryption; v1 packages are tried against every candidate.
 *  - The key id is a truncated hash of the derived key. It identifies a key but does not reveal it.
 */
class BackupEncryptionService
{
    public const MAGIC_V1 = "BKPENC1\n";
    public const MAGIC_V2 = "BKPENC2\n";
    /** Kept for backwards compatibility with callers/tests that only know "the" magic. */
    public const MAGIC = self::MAGIC_V2;

    private const CHUNK = 1048576;
    private const KEY_ID_BYTES = 8;

    public function isEncrypted(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (!$h) {
            return false;
        }
        $magic = fread($h, 8);
        fclose($h);

        return $magic === self::MAGIC_V1 || $magic === self::MAGIC_V2;
    }

    /** Key id (hex) of the key used for new backups. Safe to show and log. */
    public function currentKeyId(): string
    {
        return bin2hex($this->keyId($this->encryptionKey()));
    }

    /** Key id (hex) stored in a package header, or null for v1 packages / non-packages. */
    public function packageKeyId(string $path): ?string
    {
        $h = @fopen($path, 'rb');
        if (!$h) {
            return null;
        }
        $magic = fread($h, 8);
        $id = $magic === self::MAGIC_V2 ? fread($h, self::KEY_ID_BYTES) : null;
        fclose($h);

        return $id !== null && $id !== false && strlen($id) === self::KEY_ID_BYTES ? bin2hex($id) : null;
    }

    /** @return array<int,string> key ids of every key this installation can decrypt with */
    public function knownKeyIds(): array
    {
        return array_values(array_map(fn ($k) => bin2hex($this->keyId($k)), $this->candidateKeys()));
    }

    public function encryptFile(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if (!$in || !$out) {
            throw new RuntimeException('Unable to open files for encryption.');
        }

        try {
            $key = $this->encryptionKey();
            [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
            fwrite($out, self::MAGIC_V2 . $this->keyId($key) . $header);

            $buffer = '';
            while (!feof($in)) {
                $buffer .= (string) fread($in, self::CHUNK);
                while (strlen($buffer) > self::CHUNK) {
                    $chunk = substr($buffer, 0, self::CHUNK);
                    $buffer = substr($buffer, self::CHUNK);
                    $this->writeChunk($out, sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk));
                }
            }
            $this->writeChunk($out, sodium_crypto_secretstream_xchacha20poly1305_push(
                $state, $buffer, '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL
            ));
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    public function decryptFile(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = fopen($destination, 'wb');
        if (!$in || !$out) {
            throw new RuntimeException('Unable to open files for decryption.');
        }

        try {
            $magic = (string) fread($in, 8);
            if ($magic !== self::MAGIC_V1 && $magic !== self::MAGIC_V2) {
                throw new RuntimeException('Not an encrypted backup package.');
            }

            $keyId = null;
            if ($magic === self::MAGIC_V2) {
                $keyId = (string) fread($in, self::KEY_ID_BYTES);
                if (strlen($keyId) !== self::KEY_ID_BYTES) {
                    throw new RuntimeException('Encrypted package header is truncated.');
                }
            }
            $header = (string) fread($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (strlen($header) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new RuntimeException('Encrypted package header is truncated.');
            }

            $candidates = $this->candidateKeys();
            if ($keyId !== null) {
                $candidates = array_values(array_filter($candidates, fn ($k) => hash_equals($this->keyId($k), $keyId)));
                if (empty($candidates)) {
                    throw new RuntimeException('No key available for this package (key id ' . bin2hex($keyId)
                        . '). Restore the matching key, see docs/BACKUP_KEY_RECOVERY.md.');
                }
            }

            $dataStart = ftell($in);
            $lastError = null;
            foreach ($candidates as $key) {
                fseek($in, $dataStart);
                ftruncate($out, 0);
                rewind($out);
                try {
                    $this->decryptStream($in, $out, $header, $key);

                    return;
                } catch (RuntimeException $e) {
                    $lastError = $e;
                }
            }
            throw $lastError ?? new RuntimeException('Encrypted package failed authentication (tampered or wrong key).');
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    // ── Internals ──

    private function decryptStream($in, $out, string $header, string $key): void
    {
        $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $key);

        $sawFinal = false;
        while (!feof($in)) {
            $len = fread($in, 4);
            if ($len === '' || $len === false) {
                break;
            }
            if (strlen($len) !== 4) {
                throw new RuntimeException('Encrypted package is truncated.');
            }
            $size = unpack('N', $len)[1];
            if ($size < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $size > self::CHUNK + 64) {
                throw new RuntimeException('Encrypted package is corrupted.');
            }
            $cipher = $this->readExactly($in, $size);
            $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $cipher);
            if ($result === false) {
                throw new RuntimeException('Encrypted package failed authentication (tampered or wrong key).');
            }
            [$plain, $tag] = $result;
            fwrite($out, $plain);
            if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                $sawFinal = true;
                break;
            }
        }
        if (!$sawFinal) {
            throw new RuntimeException('Encrypted package is incomplete.');
        }
    }

    private function writeChunk($out, string $cipher): void
    {
        fwrite($out, pack('N', strlen($cipher)) . $cipher);
    }

    private function readExactly($handle, int $length): string
    {
        $data = '';
        while (strlen($data) < $length) {
            $part = fread($handle, $length - strlen($data));
            if ($part === false || $part === '') {
                throw new RuntimeException('Encrypted package is truncated.');
            }
            $data .= $part;
        }

        return $data;
    }

    private function keyId(string $derivedKey): string
    {
        return substr(hash('sha256', 'bakery-backup-key-id-v1' . $derivedKey, true), 0, self::KEY_ID_BYTES);
    }

    private function encryptionKey(): string
    {
        $dedicated = (string) config('backup.encryption_key', '');
        if ($dedicated !== '') {
            return $this->derive($dedicated);
        }

        $app = (string) config('app.key');
        if ($app === '') {
            throw new RuntimeException('No encryption key is configured (set APP_KEY or BACKUP_ENCRYPTION_KEY).');
        }

        return $this->derive($app);
    }

    /** @return array<int,string> derived keys: dedicated, APP_KEY, previous keys (deduplicated) */
    private function candidateKeys(): array
    {
        $raw = [];
        foreach ([(string) config('backup.encryption_key', ''), (string) config('app.key')] as $k) {
            if ($k !== '') {
                $raw[] = $k;
            }
        }
        foreach ((array) config('app.previous_keys', []) as $k) {
            if (is_string($k) && $k !== '') {
                $raw[] = $k;
            }
        }
        foreach ((array) config('backup.previous_encryption_keys', []) as $k) {
            if (is_string($k) && $k !== '') {
                $raw[] = $k;
            }
        }

        $keys = [];
        foreach ($raw as $k) {
            $derived = $this->derive($k);
            $keys[bin2hex($derived)] = $derived; // dedupe
        }

        return array_values($keys);
    }

    private function derive(string $material): string
    {
        if (str_starts_with($material, 'base64:')) {
            $material = base64_decode(substr($material, 7), true) ?: '';
        }
        if ($material === '') {
            throw new RuntimeException('Encryption key material is empty or invalid.');
        }

        return hash_hkdf('sha256', $material, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, 'bakery-backup-package-v1');
    }
}
