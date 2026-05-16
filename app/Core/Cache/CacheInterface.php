<?php
declare(strict_types=1);

namespace App\Core\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    /**
     * @param array<int,string> $tags
     */
    public function set(string $key, mixed $value, int $ttl = 0, array $tags = []): bool;

    public function delete(string $key): bool;

    /**
     * Invalidate all entries that carry any of the supplied tags.
     */
    public function invalidateTags(array $tags): void;

    public function flush(): void;

    /**
     * Get-or-compute helper.
     */
    public function remember(string $key, int $ttl, callable $producer, array $tags = []): mixed;
}
