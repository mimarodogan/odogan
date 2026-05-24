<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    private static ?string $layout = null;
    private static array $sections = [];
    private static array $stack = [];

    public static function render(string $template, array $data = []): string
    {
        $file = self::resolve($template);
        return self::evaluate($file, $data);
    }

    public static function layout(string $name): void
    {
        self::$layout = $name;
    }

    public static function section(string $name, string $content = ''): void
    {
        if ($content !== '') {
            self::$sections[$name] = $content;
            return;
        }
        ob_start();
        self::$stack[] = $name;
    }

    public static function endSection(): void
    {
        $name = array_pop(self::$stack);
        if ($name === null) {
            return;
        }
        self::$sections[$name] = (string) ob_get_clean();
    }

    public static function yield(string $name, string $default = ''): string
    {
        return self::$sections[$name] ?? $default;
    }

    private static function evaluate(string $file, array $data): string
    {
        $previousLayout = self::$layout;
        self::$layout = null;

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $body = (string) ob_get_clean();

        if (self::$layout !== null) {
            $layoutFile = self::resolve('layouts.' . self::$layout);
            self::$sections['content'] = $body;
            extract($data, EXTR_SKIP);
            ob_start();
            require $layoutFile;
            $body = (string) ob_get_clean();
        }

        self::$layout = $previousLayout;
        return $body;
    }

    private static function resolve(string $template): string
    {
        $path = Config::root() . '/app/Views/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: $template");
        }
        return $path;
    }
}
