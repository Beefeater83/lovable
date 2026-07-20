<?php

declare(strict_types=1);

namespace App\Tests\Unit\Services;

use App\Services\OAuthEmailService;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OAuthEmailServiceTest extends TestCase
{
    public function testReturnGoogleUserEmail(): void
    {
        $client = $this->createMock(OAuth2ClientInterface::class);
        $accessToken = $this->createMock(AccessToken::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $googleUser = $this->createMock(GoogleUser::class);

        $googleUser
            ->expects($this->once())
            ->method('getEmail')
            ->willReturn('test@gmail.com');

        $client
            ->expects($this->once())
            ->method('fetchUserFromToken')
            ->with($accessToken)
            ->willReturn($googleUser);

        $service = new OAuthEmailService($httpClient);
        $email = $service->getEmail('google', $client, $accessToken);
        $this->assertSame('test@gmail.com', $email);
    }

    public function testReturnGithubPrimaryVerifiedEmail(): void
    {
        $client = $this->createMock(OAuth2ClientInterface::class);

        $accessToken = new AccessToken([
            'access_token' => 'test-token',
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            [
                'email' => 'noreply@users.github.com',
                'primary' => false,
                'verified' => true,
            ],
            [
                'email' => 'test@gmail.com',
                'primary' => true,
                'verified' => true,
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new OAuthEmailService($httpClient);

        $this->assertSame(
            'test@gmail.com',
            $service->getEmail('github', $client, $accessToken)
        );
    }
}
