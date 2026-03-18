<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Product;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ProductVoter extends Voter
{
    public const CREATE = 'PRODUCT_CREATE';
    public const PATCH  = 'PRODUCT_PATCH';
    public const DELETE = 'PRODUCT_DELETE';
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::CREATE, self::PATCH, self::DELETE], true)
            && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user) {
            return false;
        }

        /** @var Product $product */
        $product = $subject;

        if (in_array('ROLE_TRUSTED_USER', $user->getRoles(), true)) {
            return $product->getCategory() === Product::CATEGORY_NOTEBOOK;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return false;
    }
}
