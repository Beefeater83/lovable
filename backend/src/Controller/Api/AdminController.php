<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;

class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {}

    private function getSession(): ?SessionInterface
    {
        return $this->requestStack->getSession();
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        $email = 'beefeater83@gmail.com';

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !in_array(User::ROLE_ADMIN, $user->getRoles())) {
            return new JsonResponse(['error' => 'Not admin'], 403);
        }

        $session = $this->getSession();
        if ($session) {
            $session->set('user_id', $user->getId());
        }

        return new JsonResponse(['success' => true, 'name' => $user->getName()]);
    }
}
