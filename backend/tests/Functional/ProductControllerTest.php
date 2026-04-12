<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Product;
use App\Entity\User;
use App\Services\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class ProductControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private UploadedFile $file;
    private JWTTokenManagerInterface $jwtManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);

        $mock = $this->createMock(ImageStorageService::class);
        $mock->method('upload')->willReturn('test.png');
        $container->set(ImageStorageService::class, $mock);

        $this->file = new UploadedFile(
            __DIR__ . '/../Fixtures/iphone.png',
            'iphone.png',
            'image/png',
            null,
            true
        );
    }

    protected function tearDown(): void
    {
        $this->em->createQuery('DELETE FROM ' . Product::class)->execute();
        $this->em->createQuery('DELETE FROM ' . User::class)->execute();

        parent::tearDown();
    }

    private function createUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setName('Test User')
            ->setEmail($email)
            ->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    private function authHeader(User $user): array
    {
        $token = $this->jwtManager->create($user);

        return [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ];
    }

    private function requestCreateProduct(array $headers = []): array
    {
        $this->client->request('POST', '/api/products', [
            'name' => 'Test product',
            'price' => 100,
            'category' => Product::CATEGORY_PHONE,
        ], [
            'image' => $this->file
        ], $headers);

        $content = $this->client->getResponse()->getContent();
        $data = json_decode($content, true);

        return is_array($data) ? $data : [];
    }

    public function testCreateProductAsAdmin(): void
    {
        $user = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);
        $response = $this->requestCreateProduct($this->authHeader($user));
        $this->assertResponseStatusCodeSame(201);
        $this->assertArrayHasKey('id', $response);
    }

    public function testCreateProductUnauthorized(): void
    {
        $this->requestCreateProduct();
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateProductForbiddenForTrustedUser(): void
    {
        $user = $this->createUser('trusted@gmail.com', ['ROLE_TRUSTED_USER']);
        $this->requestCreateProduct($this->authHeader($user));
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteProductAsAdmin(): void
    {
        $user = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);

        $productData = $this->requestCreateProduct($this->authHeader($user));
        $id = $productData['id'];

        $this->client->request('DELETE', '/api/products/' . $id, [], [], $this->authHeader($user));

        $this->assertResponseStatusCodeSame(204);
    }

    public function testDeleteProductForbiddenForTrustedUser(): void
    {
        $user = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);

        $productData = $this->requestCreateProduct($this->authHeader($user));
        $id = $productData['id'];

        $limitedAccessUser = $this->createUser('trusted@gmail.com', ['ROLE_TRUSTED_USER']);

        $this->client->request('DELETE', '/api/products/' . $id, [], [], $this->authHeader($limitedAccessUser));
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDeleteProductUnauthorized(): void
    {
        $user = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);

        $productData = $this->requestCreateProduct($this->authHeader($user));
        $id = $productData['id'];

        $this->client->request('DELETE', '/api/products/' . $id);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testPatchProductAsAdmin(): void
    {
        $admin = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);
        $productData = $this->requestCreateProduct($this->authHeader($admin));
        $id = $productData['id'];

        $this->client->request(
            'PATCH',
            '/api/products/' . $id,
            [],
            [],
            array_merge($this->authHeader($admin), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['name' => 'Updated Name'])
        );

        $this->assertResponseStatusCodeSame(200);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Name', $content['name']);
    }

    public function testPatchProductForbiddenForTrustedUser(): void
    {
        $admin = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);
        $productData = $this->requestCreateProduct($this->authHeader($admin));
        $id = $productData['id'];

        $limitedAccessUser = $this->createUser('trusted@gmail.com', ['ROLE_TRUSTED_USER']);
        $this->client->request(
            'PATCH',
            '/api/products/' . $id,
            [],
            [],
            array_merge($this->authHeader($limitedAccessUser), ['CONTENT_TYPE' => 'application/json']),
            json_encode(['name' => 'Updated Name'])
        );

        $this->assertResponseStatusCodeSame(403);
    }

    public function testPatchProductUnauthorized(): void
    {
        $admin = $this->createUser('admin@gmail.com', ['ROLE_ADMIN']);
        $productData = $this->requestCreateProduct($this->authHeader($admin));
        $id = $productData['id'];

        $this->client->request(
            'PATCH',
            '/api/products/' . $id,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['name' => 'Updated Name'])
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
