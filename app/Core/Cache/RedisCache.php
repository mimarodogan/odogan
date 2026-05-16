<?php
declare(strict_types=1);

namespace App\Core\Cache;

use Predis\Client;

/**
 * Redis-backed cache with tag-based invalidation.
 *
 * Storage layout:
 *   <prefix>v:<key>          serialized value (TTL on key)
 *   <prefix>t:<tag>          SET of keys carrying this tag
 *
 * Invalidating a tag walks the SET, DELs every value key, then DELs the SET.
 * This stays cheap because the SET is bounded by how often you tag.
 */
final class RedisCache implements CacheInterface
{
    private string $prefix;
    private Client $client;

    public function __construct(Client $client, string $prefix = 'odogan:')
    {
        $this->client = $client;
        $this->prefix = $prefix;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $raw = $this->client->get($this->vk($key));
        if ($raw === null) {
            return $default;
        }
        $data = @unserialize((string) $raw, ['allowed_classes' => true]);
        return $data === false && $raw !== serialize(false) ? $default : $data;
    }

    public function has(string $key): bool
    {
        return (int) $this->client->exists($this->vk($key)) > 0;
    }

    public function set(string $key, mixed $value, int $ttl = 0, array $tags = []): bool
    {
        $vk = $this->vk($key);
        $payload = serialize($value);
        if ($ttl > 0) {
            $this->client->setex($vk, $ttl, $payload);
        } else {
            $this->client->set($vk, $payload);
        }
        foreach ($tags as $tag) {
            $tk = $this->tk($tag);
            $this->client->sadd($tk, [$vk]);
            // Tag indexes outlive items; cap their lifetime so they self-collect.
            $this->client->expire($tk, max(86400, $ttl > 0 ? $ttl * 4 : 86400));
        }
        return true;
    }

    public function delete(string $key): bool
    {
        $this->client->del([$this->vk($key)]);
        return true;
    }

    public function invalidateTags(array $tags): void
    {
        foreach ($tags as $tag) {
            $tk = $this->tk($tag);
            $members = $this->client->smembers($tk);
            if ($members) {
                $this->client->del($members);
            }
            $this->client->del([$tk]);
        }
    }

    public function flush(): void
    {
        // Only flush our own keyspace, never the whole Redis db.
        $iter = null;
        do {
            [$iter, $keys] = $this->client->scan($iter ?? 0, ['MATCH' => $this->prefix . '*', 'COUNT' => 200]);
            if ($keys) {
                $this->client->del($keys);
            }
        } while ((int) $iter !== 0);
    }

    public function remember(string $key, int $ttl, callable $producer, array $tags = []): mixed
    {
        $hit = $this->get($key, $sentinel = "\0__miss__\0");
        if ($hit !== $sentinel) {
            return $hit;
        }
        $value = $producer();
        $this->set($key, $value, $ttl, $tags);
        return $value;
    }

    public function client(): Client
    {
        return $this->client;
    }

    private function vk(string $key): string
    {
        return $this->prefix . 'v:' . $key;
    }

    private function tk(string $tag): string
    {
        return $this->prefix . 't:' . $tag;
    }
}
