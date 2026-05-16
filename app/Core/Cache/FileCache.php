<?php
declare(strict_types=1);

namespace App\Core\Cache;

final class FileCache implements CacheInterface
{
    private string $dir;
    private string $tagDir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
        $this->tagDir = $this->dir . '/tags';
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }
        if (!is_dir($this->tagDir)) {
            @mkdir($this->tagDir, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $default;
        }
        $data = @unserialize($raw, ['allowed_classes' => true]);
        if (!is_array($data) || !array_key_exists('v', $data)) {
            return $default;
        }
        if (($data['e'] ?? 0) > 0 && $data['e'] < time()) {
            @unlink($file);
            return $default;
        }
        return $data['v'];
    }

    public function has(string $key): bool
    {
        return $this->get($key, $sentinel = "\0__miss__\0") !== $sentinel;
    }

    public function set(string $key, mixed $value, int $ttl = 0, array $tags = []): bool
    {
        $file = $this->path($key);
        $payload = [
            'v' => $value,
            'e' => $ttl > 0 ? time() + $ttl : 0,
            't' => $tags,
        ];
        $ok = (bool) @file_put_contents($file, serialize($payload), LOCK_EX);
        foreach ($tags as $tag) {
            $this->indexTag($tag, $key);
        }
        return $ok;
    }

    public function delete(string $key): bool
    {
        $file = $this->path($key);
        return is_file($file) ? @unlink($file) : true;
    }

    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $idx = $this->tagFile($tag);
            if (!is_file($idx)) {
                continue;
            }
            $keys = array_filter(array_map('trim', (array) @file($idx, FILE_IGNORE_NEW_LINES)));
            foreach ($keys as $k) {
                $this->delete($k);
            }
            @unlink($idx);
        }
    }

    public function flush(): void
    {
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob($this->tagDir . '/*.tag') ?: [] as $f) {
            @unlink($f);
        }
    }

    public function remember(string $key, int $ttl, callable $producer, array $tags = []): mixed
    {
        $sentinel = "\0__miss__\0";
        $hit = $this->get($key, $sentinel);
        if ($hit !== $sentinel) {
            return $hit;
        }
        $value = $producer();
        $this->set($key, $value, $ttl, $tags);
        return $value;
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.cache';
    }

    private function tagFile(string $tag): string
    {
        return $this->tagDir . '/' . sha1($tag) . '.tag';
    }

    private function indexTag(string $tag, string $key): void
    {
        @file_put_contents($this->tagFile($tag), $key . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
