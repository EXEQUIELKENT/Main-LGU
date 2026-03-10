<?php
// Entry point — loads the citizen dashboard as the homepage
$page = __DIR__ . '/citizendash.php';

if (file_exists($page)) {
    require $page;
} else {
    http_response_code(404);
    echo '<h1>404 - Page Not Found</h1>';
}