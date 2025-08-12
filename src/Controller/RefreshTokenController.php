<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class RefreshTokenController
{
    #[Route('/api/refresh_token', name: 'api_refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request, EntityManagerInterface $em, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['refresh_token'])) {
            return new JsonResponse(['error' => 'Refresh token is required'], 400);
        }
        // Ici, on suppose que le refresh_token est stocké en base et lié à l'utilisateur
        $user = $em->getRepository(User::class)->findOneBy(['refreshToken' => $data['refresh_token']]);
        if (!$user) {
            return new JsonResponse(['error' => 'Invalid refresh token'], 401);
        }
        $token = $jwtManager->create($user);
        // On renvoie aussi le refresh_token pour le frontend
        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $user->getRefreshToken()
        ]);
    }
}
