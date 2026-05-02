<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layout'): void
    {
        $viewFile = dirname(__DIR__) . '/views/' . $template . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View bulunamadi: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === '') {
            echo self::prefixAppBase($content);
            return;
        }

        $layoutFile = dirname(__DIR__) . '/views/' . $layout . '.php';
        ob_start();
        require $layoutFile;
        echo self::prefixAppBase(ob_get_clean());
    }

    private static function prefixAppBase(string $html): string
    {
        if (!function_exists('app_base_path')) {
            return $html;
        }

        $base = app_base_path();
        if ($base === '') {
            return $html;
        }

        return preg_replace_callback('/\b(href|src|action)="\/(?!\/)([^"]*)"/', static function (array $matches) use ($base): string {
            $path = '/' . $matches[2];
            if (str_starts_with($path, $base . '/') || $path === $base) {
                return $matches[0];
            }

            return $matches[1] . '="' . $base . $path . '"';
        }, $html) ?? $html;
    }
}
