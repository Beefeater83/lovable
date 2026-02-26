<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Beefeater\CrudEventBundle\Model\Filter;
use Beefeater\CrudEventBundle\Model\Page;
use Beefeater\CrudEventBundle\Model\PaginatedResult;
use Beefeater\CrudEventBundle\Model\Sort;
use Beefeater\CrudEventBundle\Exception\PayloadValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Beefeater\CrudEventBundle\Repository\AbstractRepository as BundleAbstractRepository;

abstract class CrudController extends AbstractController
{
    protected BundleAbstractRepository $repository;

    protected EntityManagerInterface $entityManager;

    public function __construct(BundleAbstractRepository $repository, EntityManagerInterface $entityManager)
    {
        $this->repository = $repository;
        $this->entityManager = $entityManager;
    }

    public function saveEntity(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function remove($id): JsonResponse
    {
        $entity = $this->repository->find($id);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Entity deleted'], JsonResponse::HTTP_NO_CONTENT);
    }

    public function readEntity(string $id): JsonResponse
    {
        $entity = $this->repository->find($id);

        return $this->json($entity, JsonResponse::HTTP_OK);
    }

    public function listEntities(Page $page, Sort $sort, Filter $filter): JsonResponse
    {
        $orderBy = $sort->getOrderBy();
        $criteria = $filter->getCriteria() ?? [];

        $entities = $this->repository->findPaginated($criteria, $orderBy, $page->getOffset(), $page->getLimit());

        $paginatedResponse = new PaginatedResult(
            $entities,
            $page->getPage(),
            $page->getPageSize(),
            $this->repository->countByCriteria($criteria)
        );

        return $this->json($paginatedResponse, Response::HTTP_OK);
    }

    protected function fromJson(Request $request, $className, object $model = null, array $groups = []): object
    {
        /**
         * @var $serializer SerializerInterface
         */
        $serializer = $this->container->get('serializer');

        $context = ($model) ? [AbstractNormalizer::OBJECT_TO_POPULATE => $model] : [];

        if (!empty($groups)) {
            $context['groups'] = $groups;
        }

        return $serializer->deserialize($request->getContent(), $className, 'json', $context);
    }

    protected function validate(ValidatorInterface $validator, object $model, ?string $group = null): void
    {
        $groups = $group ? [$group] : null;
        $errors = $validator->validate($model, null, $groups);

        if (count($errors) > 0) {
            throw new PayloadValidationException(get_class($model), $errors);
        }
    }
}
