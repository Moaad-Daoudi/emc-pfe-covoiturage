<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Trip;
use App\Entity\User;
use App\Service\ReviewManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ReviewVoter extends Voter
{
    public const CREATE = 'REVIEW_CREATE';

    public function __construct(
        private readonly ReviewManager $reviewManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::CREATE
            && ($subject instanceof Trip || $subject instanceof ReviewTarget);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if ($subject instanceof ReviewTarget) {
            return $this->reviewManager->canReview($user, $subject->target, $subject->trip);
        }

        /** @var Trip $subject */
        return count($this->reviewManager->getReviewableTargets($user, $subject)) > 0;
    }
}
