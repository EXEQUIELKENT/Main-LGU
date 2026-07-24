<?php
function envValue(string $key, ?string $default = null): ?string
{
    static $parsed = null;

    if ($parsed === null) {
        $parsed = [];
        $envPath = dirname(__DIR__) . '/.env';
        if (is_file($envPath)) {
            foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }

                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), "\"'");

                if ($name !== '') {
                    $parsed[$name] = $value;
                }
            }
        }
    }

    // Deliberately NOT using putenv() here: on threaded/persistent SAPIs
    // (e.g. Apache's WinNT MPM on Windows XAMPP), putenv() writes are
    // process-level and can leak into a *different* HTTP request handled
    // by the same reused worker — which actually happened here: this app's
    // DB_NAME leaked into a sibling system's request when the dashboard's
    // curl call landed on the same worker, making it query the wrong
    // database. A static array is request-scoped and can't leak.
    if (array_key_exists($key, $parsed) && $parsed[$key] !== '') {
        return $parsed[$key];
    }

    $value = getenv($key);
    return $value === false || $value === '' ? $default : $value;
}
