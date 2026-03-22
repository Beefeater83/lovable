<?php

declare(strict_types=1);

namespace App\Controller\Api;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GoogleController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(ClientRegistry $clientRegistry)
    {
        return $clientRegistry->getClient('google')
            ->redirect(['email', 'profile']);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck()
    {
    }
/*
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
*/
}
