<?php

declare(strict_types=1);

namespace App\Controller\Api;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class GithubController extends AbstractController
{
    #[Route('/connect/github', name: 'connect_github')]
    public function connectGithub(ClientRegistry $clientRegistry)
    {
        return $clientRegistry->getClient('github')
            ->redirect(['email', 'profile']);
    }

    #[Route('/connect/github/check', name: 'connect_github_check')]
    public function connectGithubCheck()
    {
    }
}
