<?php

declare(strict_types=1);

namespace App\Services;

use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OAuthEmailService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getEmail(
        string $provider,
        OAuth2ClientInterface $client,
        AccessToken $accessToken
    ): string {
        return match ($provider) {
            'google' => $this->getGoogleEmail($client, $accessToken),
            'github' => $this->getGithubEmail($accessToken),
            default => throw new AuthenticationException('Unsupported provider')
        };
    }

    private function getGoogleEmail(
        OAuth2ClientInterface $client,
        AccessToken $accessToken
    ): string {
        /** @var GoogleUser $oauthUser */
        $googleUser = $client->fetchUserFromToken($accessToken);

        $email = $googleUser->getEmail();
        if (!$email) {
            throw new AuthenticationException('Email not available');
        }

        return $email;
    }

    private function getGithubEmail(AccessToken $accessToken): string
    {
        $response = $this->httpClient->request(
            'GET',
            'https://api.github.com/user/emails',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken->getToken(),
                    'Accept' => 'application/vnd.github+json',
                ],
            ]
        );

        $emails = $response->toArray();
        foreach ($emails as $email) {
            if (
                ($email['primary'] ?? false)
                && ($email['verified'] ?? false)
            ) {
                return $email['email'];
            }
        }

        throw new AuthenticationException('Primary verified email not found');
    }
}
