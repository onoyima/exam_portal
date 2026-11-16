<?php
/**
 * Shared portal helpers.
 */

/**
 * Resolve the login background image.
 *
 * The database may still point at an upload that was never copied to the
 * current container (for example login_bg_1794387051.jpeg). Fall back to the
 * newest existing login_bg_* file so the login page never shows a broken
 * background.
 */
function portal_image_path(?string $configured, ?string $root = null): string
{
    $root = $root ?? dirname(__DIR__);
    $configured = trim((string) $configured);

    if ($configured !== '') {
        $relative = ltrim($configured, '/');
        if (is_file($root . '/' . $relative)) {
            return $relative;
        }
    }

    $matches = glob($root . '/assets/img/login_bg_*') ?: [];
    if ($matches) {
        // Uploads are named login_bg_<unix-timestamp>; prefer the newest one.
        usort($matches, static function (string $a, string $b): int {
            $ta = preg_match('/login_bg_(\d+)/', basename($a), $ma) ? (int) $ma[1] : 0;
            $tb = preg_match('/login_bg_(\d+)/', basename($b), $mb) ? (int) $mb[1] : 0;
            return $tb <=> $ta;
        });
        return 'assets/img/' . basename($matches[0]);
    }

    foreach (['assets/img/login_bg.jpg', 'assets/img/hero.png'] as $fallback) {
        if (is_file($root . '/' . $fallback)) {
            return $fallback;
        }
    }

    return '';
}
