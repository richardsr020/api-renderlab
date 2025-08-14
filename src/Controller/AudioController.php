<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Audio;
use App\Entity\Task;
use App\Message\GenerateAudioMessage;
use App\Service\GeminiAudioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Request;
use App\Service\AudioGenerationService;

/**
 * Contrôleur pour la gestion de la génération d'audio
 * 
 * Ce contrôleur gère toutes les opérations liées à l'audio :
 * - Génération d'audio à partir de scripts
 * - Consultation des voix disponibles
 * - Gestion des fichiers audio générés
 * - Statut des tâches de génération
 * 
 * Toutes les routes nécessitent une authentification JWT.
 * 
 * @author RenderLab Team
 * @version 2.0
 */
class AudioController extends AbstractController
{
    /**
     * Récupère la liste des voix disponibles pour la génération d'audio
     * 
     * Cette route retourne toutes les voix supportées par l'API Gemini TTS
     * pour aider l'utilisateur à choisir.
     * 
     * @param AudioGenerationService $audioService Service de génération d'audio
     * @return JsonResponse Liste des voix disponibles
     */
    #[Route('/api/audio/voices', name: 'get_available_voices', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAvailableVoices(AudioGenerationService $audioService): JsonResponse
    {
        $voices = $audioService->getAvailableVoices();
        
        return $this->json([
            'voices' => $voices,
            'default_voice' => 'Kore',
            'total_count' => count($voices),
            'message' => 'Voix disponibles pour la génération d\'audio'
        ]);
    }

    /**
     * Récupère les informations d'une voix spécifique
     * 
     * @param string $voiceName Le nom de la voix
     * @param AudioGenerationService $audioService Service de génération d'audio
     * @return JsonResponse Informations de la voix
     */
    #[Route('/api/audio/voices/{voiceName}', name: 'get_voice_info', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getVoiceInfo(string $voiceName, AudioGenerationService $audioService): JsonResponse
    {
        if (!$audioService->isValidVoice($voiceName)) {
            return $this->json([
                'error' => 'Voice not found',
                'message' => 'The specified voice does not exist. Use /api/audio/voices to see available options.'
            ], 404);
        }
        
        return $this->json([
            'voice' => $voiceName,
            'is_valid' => true,
            'message' => 'Voice is available for audio generation'
        ]);
    }

    #[Route('/api/audio/{id}', name: 'get_audio', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getAudio($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $audio = $em->getRepository(Audio::class)->find($id);
        if (!$audio || $audio->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Audio not found or forbidden'], 404);
        }
        return $this->json($audio);
    }

    #[Route('/api/audio/{id}', name: 'delete_audio', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteAudio($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $audio = $em->getRepository(Audio::class)->find($id);
        if (!$audio || $audio->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Audio not found or forbidden'], 404);
        }
        $em->remove($audio);
        $em->flush();
        return $this->json(['success' => true]);
    }

    /**
     * Déclenche la génération d'audio pour un script
     * 
     * Cette route initie le processus de génération d'audio en arrière-plan.
     * Elle vérifie les permissions, valide la voix, crée une tâche en queue,
     * et retourne immédiatement avec l'ID de la tâche pour le suivi.
     * 
     * @param int $id L'ID du script
     * @param Request $request Requête HTTP
     * @param EntityManagerInterface $em Gestionnaire d'entités Doctrine
     * @param TokenStorageInterface $tokenStorage Stockage des tokens d'authentification
     * @param MessageBusInterface $messageBus Bus de messages pour la queue
     * @param AudioGenerationService $audioService Service de génération d'audio
     * @return JsonResponse Réponse avec l'ID de la tâche créée
     */
    #[Route('/api/scripts/{id}/generate_audio', name: 'generate_audio', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateAudio(
        $id,
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        MessageBusInterface $messageBus,
        AudioGenerationService $audioService
    ): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Script not found or forbidden'], 404);
        }

        // Vérifier si déjà généré
        $existing = $em->getRepository(Audio::class)->findOneBy(['script' => $script]);
        if ($existing) {
            return $this->json(['audio' => json_decode($existing->getUrl(), true), 'already_generated' => true]);
        }

        // Vérifier si une tâche est déjà en cours
        $existingTask = $em->getRepository(Task::class)->findOneBy([
            'scriptId' => $script->getId(),
            'type' => 'audio',
            'status' => ['pending', 'in_progress']
        ]);

        if ($existingTask) {
            return $this->json([
                'success' => true,
                'task_id' => $existingTask->getTaskId(),
                'status' => $existingTask->getStatus(),
                'message' => 'Audio generation already in progress'
            ]);
        }

        $data = json_decode($request->getContent(), true);
        $requestedVoice = $data['voice'] ?? '';
        
        // Normaliser la voix (utilise la voix par défaut si invalide)
        $normalizedVoice = $audioService->normalizeVoice($requestedVoice);
        
        // Créer et envoyer le message
        $message = new GenerateAudioMessage($script->getId(), $user->getId(), $normalizedVoice);
        $messageBus->dispatch($message);

        $response = [
            'success' => true,
            'task_id' => $message->taskId,
            'status' => 'pending',
            'message' => 'Audio generation started',
            'voice_used' => $normalizedVoice
        ];
        
        // Ajouter un avertissement si la voix demandée était invalide
        if (!empty($requestedVoice) && $requestedVoice !== $normalizedVoice) {
            $response['warning'] = 'Invalid voice requested, using default voice: ' . $normalizedVoice;
            $response['requested_voice'] = $requestedVoice;
        }

        return $this->json($response);
    }
}
