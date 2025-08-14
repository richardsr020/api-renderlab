<?php

namespace App\MessageHandler;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Entity\Task;
use App\Message\GeneratePromptsMessage;
use App\Service\PromptGenerationService;
use App\Service\MercurePublisherService;
use App\Service\RateLimiterService;
use App\Service\CacheService;
use App\Service\RetryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Handler pour la génération de prompts d'images
 * 
 * Ce handler traite les messages GeneratePromptsMessage en arrière-plan.
 * Il analyse les scripts, génère des prompts via l'API Groq, et les
 * stocke en base de données. Il inclut également :
 * - Gestion des erreurs et retry
 * - Rate limiting
 * - Cache Redis
 * - Notifications Mercure
 * 
 * @author RenderLab Team
 * @version 2.0
 */
#[AsMessageHandler]
class GeneratePromptsHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PromptGenerationService $promptGenerationService,
        private MercurePublisherService $mercurePublisher,
        private RateLimiterService $rateLimiter,
        private CacheService $cacheService,
        private RetryService $retryService
    ) {}

    /**
     * Traite un message de génération de prompts
     * 
     * Cette méthode est appelée automatiquement par Symfony Messenger
     * quand un message GeneratePromptsMessage est reçu. Elle :
     * 1. Vérifie les permissions et rate limiting
     * 2. Récupère le script depuis la base de données
     * 3. Génère les prompts via l'API Groq
     * 4. Stocke les résultats en base de données
     * 5. Publie les notifications via Mercure
     * 
     * @param GeneratePromptsMessage $message Le message à traiter
     * @throws \Exception Si le traitement échoue
     */
    public function __invoke(GeneratePromptsMessage $message): void
    {
        try {
            // Créer ou récupérer la tâche
            $task = $this->entityManager->getRepository(Task::class)->findOneBy(['taskId' => $message->taskId]);
            if (!$task) {
                $task = new Task();
                $task->setTaskId($message->taskId)
                     ->setType('prompts')
                     ->setScriptId($message->scriptId)
                     ->setUserId($message->userId)
                     ->setStatus('pending');
                $this->entityManager->persist($task);
                $this->entityManager->flush();
            }

            // Notifier le début du traitement
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'in_progress',
                'message' => 'Analyzing script and generating prompts...'
            ]);

            $task->setStatus('in_progress');
            $this->entityManager->flush();

            // Vérifier le rate limiting
            if ($this->rateLimiter->isRateLimited('groq', $message->userId)) {
                throw new \Exception('Rate limit exceeded for Groq API');
            }

            // Récupérer le script
            $script = $this->entityManager->getRepository(Script::class)->find($message->scriptId);
            if (!$script) {
                throw new UnrecoverableMessageHandlingException('Script not found');
            }

            // Vérifier le cache
            $cacheKey = $this->cacheService->getPromptCacheKey($script->getId());
            $cachedPrompts = $this->cacheService->get($cacheKey);
            
            if ($cachedPrompts) {
                $scenePrompts = $cachedPrompts;
            } else {
                // Générer les prompts avec retry
                $scenePrompts = $this->retryService->retryWithBackoff(
                    fn() => $this->promptGenerationService->generatePrompts($script->getContent()),
                    ['script_id' => $script->getId(), 'user_id' => $message->userId]
                );
                
                // Mettre en cache
                $this->cacheService->set($cacheKey, $scenePrompts, 3600); // 1 heure
            }

            // Stocker en base
            foreach ($scenePrompts as $scene => $prompts) {
                $promptEntity = new Prompt();
                $promptEntity->setContent(json_encode($prompts));
                $promptEntity->setScript($script);
                $this->entityManager->persist($promptEntity);
            }

            $this->entityManager->flush();

            // Marquer comme terminé
            $task->setStatus('completed')
                 ->setResult(['scenes' => count($scenePrompts), 'total_prompts' => array_sum(array_map('count', $scenePrompts))]);
            $this->entityManager->flush();

            // Notifier la fin
            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'completed',
                'result' => $task->getResult(),
                'message' => 'Prompts generated successfully'
            ]);

            // Notifier la mise à jour du script
            $this->mercurePublisher->publishScriptUpdate($message->scriptId, $message->userId, [
                'type' => 'prompts_generated',
                'data' => $task->getResult()
            ]);

        } catch (\Exception $e) {
            // Gérer l'erreur
            if (isset($task)) {
                $task->setStatus('failed')
                     ->setError($e->getMessage());
                $this->entityManager->flush();
            }

            $this->mercurePublisher->publishTaskUpdate($message->taskId, [
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }
} 