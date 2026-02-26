<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class ProductController extends CrudController
{
    private ProductRepository $productRepository;
    private ValidatorInterface $validator;

    public function __construct(
        ProductRepository $productRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ) {
        parent::__construct($productRepository, $entityManager);
        $this->productRepository = $productRepository;
        $this->validator = $validator;
    }

    #[Route('/products', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $name = $request->request->get('name') ?? '';
        $price = $request->request->get('price') ?? '';
        $category = $request->request->get('category') ?? '';
        $imageFile = $request->files->get('image');

        if ($name === '' || $category === '' || $price === '' || !$imageFile) {
            return new Response(
                "name, price, category and image are mandatory",
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!in_array($imageFile->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'])) {
            return new Response('Invalid image type', Response::HTTP_BAD_REQUEST);
        }

        $uploadsDir = $this->getParameter('upload_dir');
        $newFilename = uniqid() . '.' . $imageFile->guessExtension();
        $imageFile->move($uploadsDir, $newFilename);

        $product = (new Product())
            ->setName($name)
            ->setCategory($category)
            ->setPrice((float)$price)
            ->setImagePath('/uploads/products/' . $newFilename);

        parent::validate($this->validator, $product, "create");
        parent::saveEntity($product);

        return new JsonResponse(['message' => 'Product created'], Response::HTTP_CREATED);
    }
}
