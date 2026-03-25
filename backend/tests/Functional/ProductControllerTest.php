<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Product;
use App\Entity\User;
use App\Kernel;
use App\Services\ImageStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private UploadedFile $file;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $this->em = $container->get('doctrine')->getManager();

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
        $this->em->createQuery('DELETE FROM ' . Product::class . ' p')->execute();
        $this->em->createQuery('DELETE FROM ' . User::class . ' u')->execute();

        parent::tearDown();
    }

    private function createAndLoginUser(string $email, array $roles): User
    {
        $user = (new User())
            ->setName('Test User')
            ->setEmail($email)
            ->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);

        return $user;
    }

    private function requestCreateProduct(): void
    {
        $this->client->request('POST', '/api/products', [
            'name' => 'Test product',
            'price' => 100,
            'category' => Product::CATEGORY_PHONE,
        ], [
            'image' => $this->file
        ]);
    }

    public function testCreateProductAsAdmin(): void
    {
        $this->createAndLoginUser('admin@gmail.com', ['ROLE_ADMIN']);
        $this->requestCreateProduct();
        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateProductUnauthorized(): void
    {
        $this->requestCreateProduct();
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCreateProductForbiddenForTrustedUser(): void
    {
        $this->createAndLoginUser('trusted@gmail.com', ['ROLE_TRUSTED_USER']);
        $this->requestCreateProduct();
        $this->assertResponseStatusCodeSame(403);
    }

    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }
}
