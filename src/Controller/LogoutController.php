<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class LogoutController
{
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['refresh_token'])) {
            return new JsonResponse(['error' => 'Refresh token is required'], 400);
        }
        $user = $em->getRepository(User::class)->findOneBy(['refreshToken' => $data['refresh_token']]);
        if (!$user) {
            return new JsonResponse(['error' => 'Invalid refresh token'], 401);
        }
        // Révoquer le refresh token
        $user->setRefreshToken(null);
        $em->persist($user);
        $em->flush();
        return new JsonResponse(['message' => 'Déconnexion réussie']);
    }
}
