<?php

namespace App\Services\Backup;

/**
 * Removes credentials from any text before it is logged or returned to a user.
 */
class BackupRedactor
{
    public static function redact(string $text): string
    {
        $patterns = [
            // postgres://user:pass@host/db  (and any scheme://user:pass@)
            '#([a-z][a-z0-9+.\-]*://)[^/\s:@]+:[^/\s@]+@#i' => '$1***:***@',
            '#(PGPASSWORD\s*=\s*)\S+#i' => '$1***',
            '#((?:password|passwd|pwd|secret|token|access[_-]?key|api[_-]?key)["\']?\s*[=:]\s*)["\']?[^\s"\',;]+#i' => '$1***',
            '#\bAKIA[0-9A-Z]{12,}\b#' => '***',
            '#\bBearer\s+[A-Za-z0-9._\-]+#i' => 'Bearer ***',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }
}
