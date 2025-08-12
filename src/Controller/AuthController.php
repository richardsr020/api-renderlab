<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['email'], $data['password'])) {
            return $this->json(['error' => 'Email and password are required'], 400);
        }
        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($hasher->hashPassword($user, $data['password']));
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['error' => (string) $errors], 422);
        }
        // Génération d'un refresh token simple (à améliorer en prod)
        $refreshToken = bin2hex(random_bytes(64));
        $user->setRefreshToken($refreshToken);
        $em->persist($user);
        $em->flush();
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'refresh_token' => $refreshToken
        ], 201);
    }
}
