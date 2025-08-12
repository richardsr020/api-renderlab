<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Audio;
use App\Service\GeminiAudioService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\Request;

class AudioController extends AbstractController
{
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

    #[Route('/api/scripts/{id}/generate_audio', name: 'generate_audio', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function generateAudio(
        $id,
        Request $request,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        GeminiAudioService $audioService
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
        $data = json_decode($request->getContent(), true);
        $voice = $data['voice'] ?? 'default';
        $saveDir = __DIR__ . '/../../public/audio/' . $script->getId();
        $filename = 'audio_' . $script->getId() . '.mp3';
        $path = $audioService->generateAudio($script->getContent(), $voice, $saveDir, $filename);
        if (!$path) {
            return $this->json(['error' => 'Audio generation failed'], 500);
        }
        $audioEntity = new Audio();
        $audioEntity->setUrl(json_encode(['/audio/' . $script->getId() . '/' . $filename]));
        $audioEntity->setScript($script);
        $em->persist($audioEntity);
        $em->flush();
        return $this->json(['success' => true, 'audio' => ['/audio/' . $script->getId() . '/' . $filename]]);
    }
}
