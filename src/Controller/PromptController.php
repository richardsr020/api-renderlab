<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PromptController extends AbstractController
{
    #[Route('/api/scripts/{id}/get_prompts', name: 'get_prompts', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getPrompts(
        $id,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        GroqService $groqService
    ): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Script not found or forbidden'], 404);
        }
        // Appel GroqService
        $scenePrompts = $groqService->generatePrompts($script->getContent());
        // Stockage en base
        foreach ($scenePrompts as $scene => $prompts) {
            $promptEntity = new Prompt();
            $promptEntity->setContent(json_encode($prompts));
            $promptEntity->setScript($script);
            $em->persist($promptEntity);
        }
        $em->flush();
        return $this->json(['success' => true, 'prompts' => $scenePrompts]);
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
}
