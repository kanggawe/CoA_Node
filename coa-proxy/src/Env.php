<?php

namespace CoaProxy;

class Env
{
    private static bool $loaded = false;
    private static array $variables = [];

    /**
     * Load environment variables from a file into $_ENV and getenv()
     */
    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Strip surrounding quotes if present
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            self::$variables[$name] = $value;
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with default fallback
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$variables)) {
            $val = self::$variables[$key];
        } elseif (isset($_ENV[$key])) {
            $val = $_ENV[$key];
        } elseif (isset($_SERVER[$key])) {
            $val = $_SERVER[$key];
        } else {
            $getenvVal = getenv($key);
            $val = $getenvVal !== false ? $getenvVal : $default;
        }

        if (is_string($val)) {
            $lower = strtolower($val);
            if ($lower === 'true' || $lower === '(true)') return true;
            if ($lower === 'false' || $lower === '(false)') return false;
            if ($lower === 'empty' || $lower === '(empty)') return '';
            if ($lower === 'null' || $lower === '(null)') return null;
        }

        return $val ?? $default;
    }
}
