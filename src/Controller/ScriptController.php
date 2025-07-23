<?php

namespace App\Controller;

use App\Entity\Script;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ScriptController extends AbstractController
{
    #[Route('/api/scripts', name: 'script_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $scripts = $em->getRepository(Script::class)->findAll();
        $data = array_map(fn($script) => [
            'id' => $script->getId(),
            'title' => $script->getTitle(),
            'content' => $script->getContent(),
            'createdAt' => $script->getCreatedAt()->format('c'),
            'user' => $script->getUser() ? $script->getUser()->getId() : null,
        ], $scripts);
        return $this->json($data);
    }

    #[Route('/api/scripts', name: 'script_create', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['title'], $data['content'])) {
            return $this->json(['error' => 'Title and content are required'], 400);
        }
        $user = $tokenStorage->getToken()->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }
        $script = new Script();
        $script->setTitle($data['title']);
        $script->setContent($data['content']);
        $script->setUser($user);
        $em->persist($script);
        $em->flush();
        return $this->json([
            'id' => $script->getId(),
            'title' => $script->getTitle(),
            'content' => $script->getContent(),
            'createdAt' => $script->getCreatedAt()->format('c'),
            'user' => $user->getId(),
        ], 201);
    }

    #[Route('/api/scripts/{id}', name: 'script_show', methods: ['GET'])]
    public function show($id, EntityManagerInterface $em): JsonResponse
    {
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script) {
            return $this->json(['error' => 'Script not found'], 404);
        }
        return $this->json([
            'id' => $script->getId(),
            'title' => $script->getTitle(),
            'content' => $script->getContent(),
            'createdAt' => $script->getCreatedAt()->format('c'),
            'user' => $script->getUser() ? $script->getUser()->getId() : null,
        ]);
    }

    #[Route('/api/scripts/{id}', name: 'script_update', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update($id, Request $request, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script) {
            return $this->json(['error' => 'Script not found'], 404);
        }
        $user = $tokenStorage->getToken()->getUser();
        if (!$user instanceof User || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $data = json_decode($request->getContent(), true);
        if (isset($data['title'])) {
            $script->setTitle($data['title']);
        }
        if (isset($data['content'])) {
            $script->setContent($data['content']);
        }
        $em->flush();
        return $this->json([
            'id' => $script->getId(),
            'title' => $script->getTitle(),
            'content' => $script->getContent(),
            'createdAt' => $script->getCreatedAt()->format('c'),
            'user' => $user->getId(),
        ]);
    }

    #[Route('/api/scripts/{id}', name: 'script_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function delete($id, EntityManagerInterface $em, TokenStorageInterface $tokenStorage): JsonResponse
    {
        $script = $em->getRepository(Script::class)->find($id);
        if (!$script) {
            return $this->json(['error' => 'Script not found'], 404);
        }
        $user = $tokenStorage->getToken()->getUser();
        if (!$user instanceof User || $script->getUser()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Forbidden'], 403);
        }
        $em->remove($script);
        $em->flush();
        return $this->json(['success' => true]);
    }
}
