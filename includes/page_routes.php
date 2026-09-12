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
            'admin'  => ['r.php', '_r.php'],
            'public' => ['r.php', '_r.php'],
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

/**
 * How (and whether) this directory can serve token URLs right now — so a
 * PARTIAL DEPLOY can never take the site down.
 *
 * Tokenised links only work if the files that resolve them actually reached
 * the server, and "_r.php" / ".htaccess" are exactly what deployment tooling
 * tends to skip (dotfiles are hidden by default in most FTP clients;
 * underscore-prefixed files are a common exclude). That really happened on the
 * CIMM domain: pages were uploaded and began emitting tokens while _r.php had
 * not been, so every link 404'd — including Log in.
 *
 *   'pretty' — _r.php + .htaccess present:  /admin/<token>
 *   'query'  — _r.php only:                 /admin/_r.php?__h=<token>
 *              (hides the page name equally well, needs no mod_rewrite)
 *   'off'    — _r.php missing: emit the real .php URL, i.e. exactly how the
 *              site behaved before any of this existed
 */
if (!defined('MLGU_ROUTER_FILE')) {
    define('MLGU_ROUTER_FILE', 'r.php');
}

if (!function_exists('mlgu_route_mode')) {
    function mlgu_route_mode(string $dir): string {
        static $cache = [];
        if (isset($cache[$dir])) {
            return $cache[$dir];
        }
        $base = mlgu_app_dir() . '/' . $dir;

        // Accept the legacy underscore name too, so an install that already
        // has _r.php deployed keeps working without re-uploading anything.
        $router = null;
        foreach ([MLGU_ROUTER_FILE, '_r.php'] as $candidate) {
            if (is_file($base . '/' . $candidate)) { $router = $candidate; break; }
        }
        if ($router === null) {
            return $cache[$dir] = 'off';
        }
        // Pretty URLs need the .htaccess rewrite, which is exactly the kind of
        // file deployment tooling skips — so it is opt-in, and the query form
        // (which cannot fail that way) is the default.
        if (defined('MLGU_PRETTY_PAGE_URLS') && MLGU_PRETTY_PAGE_URLS === true
            && is_file($base . '/.htaccess')) {
            return $cache[$dir] = 'pretty';
        }
        return $cache[$dir] = 'query:' . $router;
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

        $mode = mlgu_route_mode($dir);
        if ($mode === 'off') {
            return $target;                          // router not deployed here
        }

        $token = mlgu_page_token($dir, $file);
        $base  = $prefix . ($sub !== '' ? $sub . '/' : '');

        if (strncmp($mode, 'query', 5) === 0) {
            $router = substr($mode, 6) ?: MLGU_ROUTER_FILE;
            $extra = '';
            if ($suffix !== '' && $suffix[0] === '?') {
                $extra = '&' . substr($suffix, 1);
            } elseif ($suffix !== '') {
                $extra = $suffix;                    // bare #fragment
            }
            if ($extra !== '' && $extra[0] === '&') {
                $hash = '';
                if (($hp = strpos($extra, '#')) !== false) {
                    $hash  = substr($extra, $hp);
                    $extra = substr($extra, 0, $hp);
                }
                return $base . $router . '?__h=' . $token . $extra . $hash;
            }
            return $base . $router . '?__h=' . $token . $extra;
        }

        return $base . $token . $suffix;
    }
}

if (!function_exists('mlgu_url_attr')) {
    function mlgu_url_attr(string $target, ?string $dirOverride = null): string {
        return htmlspecialchars(mlgu_url($target, $dirOverride), ENT_QUOTES, 'UTF-8');
    }
}
