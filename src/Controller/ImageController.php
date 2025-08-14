<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Entity\Image;
use App\Entity\Task;
use App\Message\GenerateImagesMessage;
use App\Service\GeminiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ImageController extends AbstractController
{
    #[Route('/api/images/{id}', name: 'get_image', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getImage($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $image = $em->getRepository(Image::class)->find($id);
        if (!$image || $image->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Image not found or forbidden'], 404);
        }
        return $this->json($image);
    }


    #[Route('/api/images/{id}', name: 'delete_image', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteImage($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $image = $em->getRepository(Image::class)->find($id);
        if (!$image || $image->getScript()->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Image not found or forbidden'], 404);
        }
        $em->remove($image);
        $em->flush();
        return $this->json(['success' => true]);
    }

    #[Route('/api/scripts/{id}/generate_images', name: 'generate_images', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateImages(
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

        // Vérifier si des prompts existent
        $prompts = $em->getRepository(Prompt::class)->findBy(['script' => $script]);
        if (!$prompts) {
            return $this->json(['error' => 'No prompts found for this script. Generate prompts first.'], 400);
        }

        // Vérifier si une tâche est déjà en cours
        $existingTask = $em->getRepository(Task::class)->findOneBy([
            'scriptId' => $script->getId(),
            'type' => 'images',
            'status' => ['pending', 'in_progress']
        ]);

        if ($existingTask) {
            return $this->json([
                'success' => true,
                'task_id' => $existingTask->getTaskId(),
                'status' => $existingTask->getStatus(),
                'message' => 'Image generation already in progress'
            ]);
                }

        // Créer et envoyer le message
        $message = new GenerateImagesMessage($script->getId(), $user->getId());
        $messageBus->dispatch($message);

        return $this->json([
            'success' => true,
            'task_id' => $message->taskId,
            'status' => 'pending',
            'message' => 'Image generation started'
        ]);
    }
}
