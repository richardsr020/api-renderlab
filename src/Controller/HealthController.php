<?php

namespace App\Controller;

use App\Service\CacheService;
use App\Service\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HealthController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheService $cacheService,
        private RateLimiterService $rateLimiter
    ) {}

    #[Route('/api/health', name: 'health_check', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'groq_api' => $this->checkGroqApi(),
            'gemini_api' => $this->checkGeminiApi(),
            'cache' => $this->checkCache(),
            'rate_limiter' => $this->checkRateLimiter()
        ];
        
        $healthy = !in_array(false, $checks);
        $statusCode = $healthy ? 200 : 503;
        
        return $this->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => new \DateTime(),
            'checks' => $checks,
            'cache_stats' => $this->cacheService->getStats()
        ], $statusCode);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->getDoctrine()->getConnection()->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            $stats = $this->cacheService->getStats();
            return isset($stats['memory_used']);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkGroqApi(): bool
    {
        try {
            $apiKey = $_ENV['GROQ_API_KEY'] ?? '';
            if (empty($apiKey)) {
                return false;
            }
            
            // Test simple de l'API
            $response = $this->httpClient->request('GET', 'https://api.groq.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkGeminiApi(): bool
    {
        try {
            $apiKey = $_ENV['GEMINI_API_KEY'] ?? '';
            if (empty($apiKey)) {
                return false;
            }
            
            // Test simple de l'API
            $response = $this->httpClient->request('GET', 'https://generativelanguage.googleapis.com/v1beta/models', [
                'headers' => [
                    'x-goog-api-key' => $apiKey,
                ],
                'timeout' => 5
            ]);
            
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCache(): bool
    {
        try {
            $testKey = 'health_check_' . time();
            $testData = ['test' => 'data'];
            
            $this->cacheService->set($testKey, $testData, 60);
            $retrieved = $this->cacheService->get($testKey);
            $this->cacheService->delete($testKey);
            
            return $retrieved === $testData;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkRateLimiter(): bool
    {
        try {
            $testUserId = 999999;
            $quota = $this->rateLimiter->getRemainingQuota('groq', $testUserId);
            return isset($quota['hourly']['limit']);
        } catch (\Exception $e) {
            return false;
        }
    }
} 