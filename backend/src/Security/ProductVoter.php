<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Product;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Psr\Log\LoggerInterface;

class ProductVoter extends Voter
{
    public function __construct(private LoggerInterface $logger)
    {
    }

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
            $this->logger->info('[ProductVoter] access denied: anonymous');
            return false;
        }

        /** @var Product $product */
        $product = $subject;

        if (in_array('ROLE_TRUSTED_USER', $user->getRoles(), true)) {
            $allowed = $product->getCategory() === Product::CATEGORY_NOTEBOOK;
            $this->logger->info('[ProductVoter] ' . ($allowed ? 'access granted' : 'access denied'), [
                'user' => $user->getEmail(),
            ]);

            return $allowed;
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $this->logger->info('[ProductVoter] access granted', [
                'user' => $user->getEmail(),
            ]);

            return true;
        }

        $this->logger->warning('[ProductVoter] access denied', [
            'user' => $user->getEmail(),
        ]);

        return false;
    }
}
