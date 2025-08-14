<?php

namespace App\Service;

use Predis\ClientInterface;

class RateLimiterService
{
    private ClientInterface $redis;
    private array $limits;

    public function __construct(ClientInterface $redis)
    {
        $this->redis = $redis;
        $this->limits = [
            'groq' => [
                'requests_per_hour' => 100,
                'requests_per_minute' => 10
            ],
            'gemini' => [
                'requests_per_hour' => 200,
                'requests_per_minute' => 20
            ],
            'image_generation' => [
                'requests_per_hour' => 50,
                'requests_per_minute' => 5
            ],
            'audio_generation' => [
                'requests_per_hour' => 30,
                'requests_per_minute' => 3
            ]
        ];
    }

    public function checkLimit(string $api, int $userId): bool
    {
        $hourlyKey = "rate_limit:{$api}:{$userId}:hourly";
        $minuteKey = "rate_limit:{$api}:{$userId}:minute";
        
        $hourlyLimit = $this->limits[$api]['requests_per_hour'] ?? 100;
        $minuteLimit = $this->limits[$api]['requests_per_minute'] ?? 10;
        
        // Vérifier la limite par minute
        $currentMinute = $this->redis->incr($minuteKey);
        if ($currentMinute === 1) {
            $this->redis->expire($minuteKey, 60); // 1 minute
        }
        
        if ($currentMinute > $minuteLimit) {
            return false;
        }
        
        // Vérifier la limite par heure
        $currentHour = $this->redis->incr($hourlyKey);
        if ($currentHour === 1) {
            $this->redis->expire($hourlyKey, 3600); // 1 heure
        }
        
        return $currentHour <= $hourlyLimit;
    }

    public function getRemainingQuota(string $api, int $userId): array
    {
        $hourlyKey = "rate_limit:{$api}:{$userId}:hourly";
        $minuteKey = "rate_limit:{$api}:{$userId}:minute";
        
        $hourlyLimit = $this->limits[$api]['requests_per_hour'] ?? 100;
        $minuteLimit = $this->limits[$api]['requests_per_minute'] ?? 10;
        
        $currentHour = (int) $this->redis->get($hourlyKey) ?: 0;
        $currentMinute = (int) $this->redis->get($minuteKey) ?: 0;
        
        return [
            'hourly' => [
                'used' => $currentHour,
                'limit' => $hourlyLimit,
                'remaining' => max(0, $hourlyLimit - $currentHour)
            ],
            'minute' => [
                'used' => $currentMinute,
                'limit' => $minuteLimit,
                'remaining' => max(0, $minuteLimit - $currentMinute)
            ]
        ];
    }

    public function resetQuota(string $api, int $userId): void
    {
        $hourlyKey = "rate_limit:{$api}:{$userId}:hourly";
        $minuteKey = "rate_limit:{$api}:{$userId}:minute";
        
        $this->redis->del($hourlyKey, $minuteKey);
    }

    public function isRateLimited(string $api, int $userId): bool
    {
        return !$this->checkLimit($api, $userId);
    }
} 