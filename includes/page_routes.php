<?php
/**
 * page_routes.php — opaque ("hashed") URLs for Main LGU's pages.
 *
 * Same design as the CIMM portal's includes/core/page_routes.php; see that
 * file for the full rationale. In short:
 *
 *   - A page's real filename is replaced by a 16-hex token in the SAME
 *     directory, so "/admin/dashboard.php" is served as "/admin/9c3f...".
 *     Keeping the directory identical is what lets every relative asset,
 *     include and form action keep resolving exactly as before.
 *   - .htaccess rewrites  ^([A-Fa-f0-9]{16})$  ->  _r.php?__h=$1, and _r.php
 *     (which lives in that same directory) resolves the token and include()s
 *     the real page.
 *   - The real .php URLs keep working, so bookmarks, already-sent emails and
 *     any link another system holds are unaffected. mlgu_url() returns
 *     anything it cannot tokenise unchanged, so a missed link degrades to
 *     today's behaviour rather than to a 404.
 *
 * This hides URL structure; it is not access control. require_super_admin()
 * and the auth gates remain the real security boundary and are untouched.
 */

declare(strict_types=1);

if (!function_exists('mlgu_route_dirs')) {
    /** Directories whose pages get tokenised => files inside that must keep their real name. */
    function mlgu_route_dirs(): array {
        return [
            'admin'  => ['_r.php'],
            'public' => ['_r.php'],
        ];
    }
}

if (!function_exists('mlgu_route_secret')) {
    function mlgu_route_secret(): string {
        static $secret = null;
        if ($secret !== null) {
            return $secret;
        }
        $env = getenv('MAINLGU_ROUTE_SECRET');
        if (is_string($env) && $env !== '') {
            return $secret = $env;
        }
        $localOverride = __DIR__ . '/route.local.php';
        if (is_file($localOverride)) {
            $fromFile = require $localOverride;
            if (is_string($fromFile) && $fromFile !== '') {
                return $secret = $fromFile;
            }
        }
        return $secret = 'MAINLGU_PAGE_ROUTE_SECRET_2026';
    }
}

if (!function_exists('mlgu_page_token')) {
    function mlgu_page_token(string $dir, string $file): string {
        return substr(hash_hmac('sha256', $dir . '/' . $file, mlgu_route_secret()), 0, 16);
    }
}

if (!function_exists('mlgu_app_dir')) {
    /** Project root (the folder holding admin/ and public/). */
    function mlgu_app_dir(): string {
        return dirname(__DIR__);
    }
}

if (!function_exists('mlgu_is_hashable_page')) {
    function mlgu_is_hashable_page(string $dir, string $file): bool {
        $dirs = mlgu_route_dirs();
        if (!isset($dirs[$dir])) {
            return false;
        }
        if (substr($file, -4) !== '.php' || in_array($file, $dirs[$dir], true)) {
            return false;
        }
        if ($file !== basename($file)) {
            return false;
        }
        return is_file(mlgu_app_dir() . '/' . $dir . '/' . $file);
    }
}

if (!function_exists('mlgu_resolve_page_token')) {
    function mlgu_resolve_page_token(string $dir, string $token): ?string {
        if (!preg_match('/^[A-Fa-f0-9]{16}$/', $token) || !isset(mlgu_route_dirs()[$dir])) {
            return null;
        }
        $token = strtolower($token);
        foreach (glob(mlgu_app_dir() . '/' . $dir . '/*.php') ?: [] as $path) {
            $file = basename($path);
            if (!mlgu_is_hashable_page($dir, $file)) {
                continue;
            }
            if (hash_equals(mlgu_page_token($dir, $file), $token)) {
                return $file;
            }
        }
        return null;
    }
}

if (!function_exists('mlgu_current_route_dir')) {
    function mlgu_current_route_dir(): string {
        $script = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['SCRIPT_NAME'] ?? '');
        return basename(dirname(str_replace('\\', '/', (string)$script)));
    }
}

/**
 * Give it a link exactly as it is written today; get back the tokenised form.
 * Query strings, fragments and ../ prefixes ride along untouched, and anything
 * that is not a tokenisable page comes back byte-for-byte unchanged.
 */
if (!function_exists('mlgu_url')) {
    function mlgu_url(string $target, ?string $dirOverride = null): string {
        $trimmed = trim($target);
        if ($trimmed === '') {
            return $target;
        }
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:|//)#i', $trimmed)) {
            return $target;                          // absolute URL / mailto: / tel:
        }

        $suffix = '';
        $path = $trimmed;
        $cut = strcspn($path, '?#');
        if ($cut < strlen($path)) {
            $suffix = substr($path, $cut);
            $path   = substr($path, 0, $cut);
        }
        if ($path === '') {
            return $target;
        }

        $prefix = '';
        while (preg_match('#^(\.\./|\./)#', $path, $m)) {
            $prefix .= $m[1];
            $path = substr($path, strlen($m[1]));
        }

        $file = basename($path);
        $sub  = trim(dirname($path), './');
        $dir  = $sub !== '' ? $sub : ($dirOverride ?? mlgu_current_route_dir());

        if (!mlgu_is_hashable_page($dir, $file)) {
            return $target;
        }

        return $prefix . ($sub !== '' ? $sub . '/' : '') . mlgu_page_token($dir, $file) . $suffix;
    }
}

if (!function_exists('mlgu_url_attr')) {
    function mlgu_url_attr(string $target, ?string $dirOverride = null): string {
        return htmlspecialchars(mlgu_url($target, $dirOverride), ENT_QUOTES, 'UTF-8');
    }
}
