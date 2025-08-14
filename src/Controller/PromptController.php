<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Entity\Task;
use App\Message\GeneratePromptsMessage;
use App\Service\PromptGenerationService;
use App\Service\RateLimiterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Contrôleur pour la gestion des prompts d'images
 * 
 * Ce contrôleur gère toutes les opérations liées aux prompts :
 * - Génération de prompts à partir de scripts
 * - Consultation et modification des prompts
 * - Statut des tâches de génération
 * - Quotas d'utilisation des APIs
 * 
 * Toutes les routes nécessitent une authentification JWT.
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class PromptController extends AbstractController
{
    /**
     * Déclenche la génération de prompts pour un script
     * 
     * Cette route initie le processus de génération de prompts en arrière-plan.
     * Elle vérifie les permissions, crée une tâche en queue, et retourne
     * immédiatement avec l'ID de la tâche pour le suivi.
     * 
     * @param int $id L'ID du script
     * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine
     * @param TokenStorageInterface $tokenStorage Stockage des tokens d'authentification
     * @param MessageBusInterface $messageBus Bus de messages pour la queue
     * @return JsonResponse Réponse avec l'ID de la tâche créée
     */
    #[Route('/api/scripts/{id}/get_prompts', name: 'get_prompts', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getPrompts(
        $id,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        MessageBusInterface $messageBus
    ): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Script not found or forbidden'], 404);
        }

        // Vérifier si une tâche est déjà en cours
        $existingTask = $em->getRepository(Task::class)->findOneBy([
            'scriptId' => $script->getId(),
            'type' => 'prompts',
            'status' => ['pending', 'in_progress']
        ]);

        if ($existingTask) {
            return $this->json([
                'success' => true,
                'task_id' => $existingTask->getTaskId(),
                'status' => $existingTask->getStatus(),
                'message' => 'Task already in progress'
            ]);
        }

        // Créer et envoyer le message
        $message = new GeneratePromptsMessage($script->getId(), $user->getId());
        $messageBus->dispatch($message);

        return $this->json([
            'success' => true,
            'task_id' => $message->taskId,
            'status' => 'pending',
            'message' => 'Prompt generation started'
        ]);
    }

    #[Route('/api/prompts/{id}', name: 'get_prompt', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getPrompt($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $prompt = $em->getRepository(Prompt::class)->find($id);
        if (!$prompt || $prompt->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Prompt not found or forbidden'], 404);
        }
        return $this->json($prompt);
    }

    #[Route('/api/prompts/{id}', name: 'update_prompt', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function updatePrompt($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $prompt = $em->getRepository(Prompt::class)->find($id);
        if (!$prompt || $prompt->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Prompt not found or forbidden'], 404);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['content'])) {
            $prompt->setContent($data['content']);
        }
        $em->flush();
        return $this->json(['success' => true, 'prompt' => $prompt]);
    }

    #[Route('/api/prompts/{id}', name: 'delete_prompt', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deletePrompt($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $prompt = $em->getRepository(Prompt::class)->find($id);
        if (!$prompt || $prompt->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Prompt not found or forbidden'], 404);
        }
        $em->remove($prompt);
        $em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/api/tasks/{taskId}/status', name: 'get_task_status', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getTaskStatus($taskId, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $task = $em->getRepository(Task::class)->findOneBy(['taskId' => $taskId]);
        
        if (!$task || $task->getUserId() !== $user->getId()) {
            return $this->json(['error' => 'Task not found or forbidden'], 404);
        }

        return $this->json([
            'task_id' => $task->getTaskId(),
            'type' => $task->getType(),
            'status' => $task->getStatus(),
            'result' => $task->getResult(),
            'error' => $task->getError(),
            'created_at' => $task->getCreatedAt()->format('c'),
            'updated_at' => $task->getUpdatedAt()?->format('c')
        ]);
    }

    #[Route('/api/quota/{api}', name: 'get_quota', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getQuota($api, TokenStorageInterface $tokenStorage, RateLimiterService $rateLimiter): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        
        $quota = $rateLimiter->getRemainingQuota($api, $user->getId());
        
        return $this->json([
            'api' => $api,
            'user_id' => $user->getId(),
            'quota' => $quota
        ]);
    }
}
