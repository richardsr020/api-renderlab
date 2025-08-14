<?php

namespace App\Service;

use Predis\ClientInterface;

class CacheService
{
    private ClientInterface $redis;
    private int $defaultTtl;

    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
        $this->defaultTtl = 3600; // 1 heure par défaut
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        return $value ? json_decode($value, true) : null;
    }

    public function set(string $key, mixed $data, int $ttl = null): bool
    {
        $ttl = $ttl ?? $this->defaultTtl;
        $serialized = json_encode($data);
        
        if ($serialized === false) {
            return false;
        }
        
        return $this->redis->setex($key, $ttl, $serialized);
    }

    public function delete(string $key): bool
    {
        return (bool) $this->redis->del($key);
    }

    public function exists(string $key): bool
    {
        return (bool) $this->redis->exists($key);
    }

    public function getOrSet(string $key, callable $callback, int $ttl = null): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }

    public function invalidatePattern(string $pattern): int
    {
        $keys = $this->redis->keys($pattern);
        if (empty($keys)) {
            return 0;
        }
        
        return $this->redis->del($keys);
    }

    public function getScriptCacheKey(int $scriptId, string $type): string
    {
        return "script:{$scriptId}:{$type}";
    }

    public function getPromptCacheKey(int $scriptId): string
    {
        return $this->getScriptCacheKey($scriptId, 'prompts');
    }

    public function getImageCacheKey(int $scriptId): string
    {
        return $this->getScriptCacheKey($scriptId, 'images');
    }

    public function getAudioCacheKey(int $scriptId): string
    {
        return $this->getScriptCacheKey($scriptId, 'audio');
    }

    public function invalidateScriptCache(int $scriptId): void
    {
        $pattern = "script:{$scriptId}:*";
        $this->invalidatePattern($pattern);
    }

    public function getStats(): array
    {
        $info = $this->redis->info();
        
        return [
            'memory_used' => $info['used_memory_human'] ?? 'unknown',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'hit_rate' => $this->calculateHitRate($info),
            'total_commands' => $info['total_commands_processed'] ?? 0
        ];
    }

    private function calculateHitRate(array $info): float
    {
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;
        
        return $total > 0 ? ($hits / $total) * 100 : 0;
    }
} 