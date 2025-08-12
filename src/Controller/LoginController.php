<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class LoginController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JWTTokenManagerInterface $jwtManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email'], $data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], 400);
        }
        $user = $em->getRepository('App:User')->findOneBy(['email' => $data['email']]);
        if (!$user) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }
        if (!$hasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], 401);
        }
        $token = $jwtManager->create($user);
        // Génération d'un refresh token simple (à améliorer en prod)
        $refreshToken = bin2hex(random_bytes(64));
        $user->setRefreshToken($refreshToken);
        $em->persist($user);
        $em->flush();
        return new JsonResponse([
            'token' => $token,
            'refresh_token' => $refreshToken
        ]);
    }
}
