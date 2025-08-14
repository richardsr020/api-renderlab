<?php

namespace App\Service;

class RetryService
{
    private int $maxRetries;
    private int $baseDelay;
    private float $multiplier;

    public function __construct(int $maxRetries = 3, int $baseDelay = 1000, float $multiplier = 2.0)
    {
        $this->maxRetries = $maxRetries;
        $this->baseDelay = $baseDelay;
        $this->multiplier = $multiplier;
    }

    public function retryWithBackoff(callable $operation, array $context = []): mixed
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Log l'erreur
                error_log(sprintf(
                    "Retry attempt %d/%d failed: %s. Context: %s",
                    $attempt,
                    $this->maxRetries,
                    $e->getMessage(),
                    json_encode($context)
                ));
                
                if ($attempt === $this->maxRetries) {
                    break;
                }
                
                // Calculer le délai avec backoff exponentiel
                $delay = $this->calculateDelay($attempt);
                
                // Ajouter un peu de jitter pour éviter les thundering herds
                $jitter = rand(0, 100);
                $delay += $jitter;
                
                error_log("Retrying in {$delay}ms...");
                usleep($delay * 1000); // Convertir en microsecondes
            }
        }
        
        throw new \Exception(
            "Operation failed after {$this->maxRetries} attempts. Last error: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    private function calculateDelay(int $attempt): int
    {
        return (int) ($this->baseDelay * pow($this->multiplier, $attempt - 1));
    }

    public function retryWithExponentialBackoff(callable $operation, array $context = []): mixed
    {
        return $this->retryWithBackoff($operation, $context);
    }

    public function retryWithLinearBackoff(callable $operation, array $context = []): mixed
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                error_log(sprintf(
                    "Linear retry attempt %d/%d failed: %s",
                    $attempt,
                    $this->maxRetries,
                    $e->getMessage()
                ));
                
                if ($attempt === $this->maxRetries) {
                    break;
                }
                
                $delay = $this->baseDelay * $attempt;
                usleep($delay * 1000);
            }
        }
        
        throw new \Exception(
            "Operation failed after {$this->maxRetries} attempts. Last error: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }

    public function retryWithFixedDelay(callable $operation, int $delay = 1000, array $context = []): mixed
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                error_log(sprintf(
                    "Fixed delay retry attempt %d/%d failed: %s",
                    $attempt,
                    $this->maxRetries,
                    $e->getMessage()
                ));
                
                if ($attempt === $this->maxRetries) {
                    break;
                }
                
                usleep($delay * 1000);
            }
        }
        
        throw new \Exception(
            "Operation failed after {$this->maxRetries} attempts. Last error: " . $lastException->getMessage(),
            0,
            $lastException
        );
    }
} 