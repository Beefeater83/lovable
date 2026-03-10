<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class SessionUserProvider implements UserProviderInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {}

    private function getSession(): ?SessionInterface
    {
        return $this->requestStack->getSession();
    }

    public function getUserFromSession(): ?User
    {
        $session = $this->getSession();
        if (!$session) {
            return null;
        }
        $id = $session->get('user_id');
        if (!$id) return null;
        return $this->em->getRepository(User::class)->find($id);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        $reloaded = $this->em->getRepository(User::class)->find($user->getId());
        if (!$reloaded) {
            throw new UnsupportedUserException();
        }
        return $reloaded;
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->em->getRepository(User::class)->find($identifier);
    }
}
