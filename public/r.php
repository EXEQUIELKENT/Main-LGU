<?php
/**
 * _r.php — token router for the pages in THIS directory.
 *
 * .htaccess rewrites  /<16-hex token>  ->  _r.php?__h=<token>, and this
 * resolves it back to the real page and include()s it. It lives in the same
 * directory as the pages it serves so __DIR__, relative require_once and every
 * relative asset URL behave exactly as on a direct hit.
 * See includes/page_routes.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/page_routes.php';

$__dir   = basename(__DIR__);
$__token = (string)($_GET['__h'] ?? '');
$__file  = mlgu_resolve_page_token($__dir, $__token);

if ($__file === null) {
    http_response_code(404);
    exit('Not found.');
}

// Strip the routing parameter so the page sees only the caller's own query
// string, then present the script identity of the real page (pages derive the
// active nav item from basename($_SERVER['PHP_SELF']), and
// mlgu_current_route_dir() reads SCRIPT_FILENAME).
unset($_GET['__h'], $_REQUEST['__h']);
$__qs   = $_GET ? http_build_query($_GET) : '';
$__self = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? ""), "/") . "/" . $__file;

$_SERVER['SCRIPT_FILENAME'] = __DIR__ . DIRECTORY_SEPARATOR . $__file;
$_SERVER['SCRIPT_NAME']     = $__self;
$_SERVER['PHP_SELF']        = $__self;
$_SERVER['QUERY_STRING']    = $__qs;
$_SERVER['REQUEST_URI']     = $__self . ($__qs !== '' ? '?' . $__qs : '');

require __DIR__ . '/' . $__file;
