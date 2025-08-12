<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\Prompt;
use App\Entity\Image;
use App\Service\GeminiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        GeminiService $geminiService
    ): JsonResponse {
        $user = $tokenStorage->getToken()->getUser();
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Script not found or forbidden'], 404);
        }
        // Récupérer tous les prompts liés à ce script
        $prompts = $em->getRepository(Prompt::class)->findBy(['script' => $script]);
        if (!$prompts) {
            return $this->json(['error' => 'No prompts found for this script'], 400);
        }
        $imagesResult = [];
        foreach ($prompts as $promptEntity) {
            $promptList = json_decode($promptEntity->getContent(), true);
            $sceneImages = [];
            foreach ($promptList as $idx => $promptText) {
                $saveDir = __DIR__ . '/../../public/images/' . $script->getId();
                $filename = 'scene_' . $promptEntity->getId() . '_img_' . ($idx+1) . '.png';
                $path = $geminiService->generateImage($promptText, $saveDir, $filename);
                if ($path) {
                    $sceneImages[] = '/images/' . $script->getId() . '/' . $filename;
                }
            }
            // Stocker dans la table Image (array)
            $imageEntity = new Image();
            $imageEntity->setUrl(json_encode($sceneImages));
            $imageEntity->setScript($script);
            $em->persist($imageEntity);
            $imagesResult[$promptEntity->getId()] = $sceneImages;
        }
        $em->flush();
        return $this->json(['success' => true, 'images' => $imagesResult]);
    }
}
