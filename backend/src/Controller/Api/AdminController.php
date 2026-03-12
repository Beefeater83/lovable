<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AdminController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }

    #[Route('/admin/logout', methods: ['POST'])]
    public function logout(
        RequestStack $requestStack,
        TokenStorageInterface $tokenStorage
    ): JsonResponse {

        $session = $requestStack->getSession();

        if ($session) {
            $session->remove('_security_main');
            $session->clear();
            $session->invalidate();
        }

        $tokenStorage->setToken(null);

        return new JsonResponse(['success' => true]);
    }
}
