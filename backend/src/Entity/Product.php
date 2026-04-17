<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
class Product
{
    use TimestampableEntity;

    public const CATEGORY_PHONE      = 'phone';
    public const CATEGORY_NOTEBOOK   = 'notebook';
    public const CATEGORY_HEADPHONES = 'headphones';

    public const ALLOWED_CATEGORIES = [
        self::CATEGORY_PHONE,
        self::CATEGORY_NOTEBOOK,
        self::CATEGORY_HEADPHONES,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(
        message: 'Name should not be blank.',
        groups: ['create', 'patch']
    )]
    #[Assert\Length(
        min: 2,
        minMessage: 'Name should have at least 2 characters.',
        groups: ['create', 'patch']
    )]
    #[Groups(['create', 'patch'])]
    private string $name;

    #[ORM\Column(name: "price", type: Types::FLOAT, nullable: false)]
    #[Assert\NotBlank(
        message: 'Price should not be blank.',
        groups: ['create', 'patch']
    )]
    #[Assert\Positive(
        message: 'Price must be greater than 0.',
        groups: ['create', 'patch']
    )]
    #[Groups(['create', 'patch'])]
    private float $price;

    #[ORM\Column(length: 255, nullable: false)]
    #[Assert\NotBlank(
        message: 'Image path should not be blank.',
        groups: ['create', 'patch']
    )]
    #[Groups(['create', 'patch'])]
    private string $imagePath;

    #[ORM\Column(length: 100)]
    #[Assert\Choice(
        choices: self::ALLOWED_CATEGORIES,
        message: 'Invalid category. Allowed: phone, notebook, headphones.',
        groups: ['create', 'patch']
    )]
    #[Groups(['create', 'patch'])]
    private string $category;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "user_id",referencedColumnName: "id", nullable: true)]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getImagePath(): string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }
}
