<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static ?self $instance = null;

    public function __construct(private array $values)
    {
    }

    public static function load(): self
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        $file = dirname(__DIR__, 2) . '/config/config.php';
        if (!is_file($file)) {
            $file = dirname(__DIR__, 2) . '/config/config.example.php';
        }

        $values = require $file;
        self::$instance = new self(is_array($values) ? $values : []);

        return self::$instance;
    }

    public static function reload(): self
    {
        self::$instance = null;
        return self::load();
    }

    public function all(): array
    {
        return $this->values;
    }

    public function isInstalled(): bool
    {
        return is_file(dirname(__DIR__, 2) . '/config/config.php')
            && (bool) $this->get('app.installed', false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function write(array $values): void
    {
        $file = dirname(__DIR__, 2) . '/config/config.php';
        $content = "<?php\n\nreturn " . var_export($values, true) . ";\n";
        file_put_contents($file, $content, LOCK_EX);
        self::$instance = new self($values);
    }
}

