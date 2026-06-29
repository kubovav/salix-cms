<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsEntityListener(event: Events::prePersist, entity: User::class)]
#[AsEntityListener(event: Events::preUpdate, entity: User::class)]
class UserPasswordListener
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function prePersist(User $user): void
    {
        $this->hashPassword($user);
        $user->setUpdatedAt(new \DateTimeImmutable());
    }

    public function preUpdate(User $user, PreUpdateEventArgs $args): void
    {
        $this->hashPassword($user);
        $user->setUpdatedAt(new \DateTimeImmutable());

        // The changeset is already computed by the time preUpdate runs, so the
        // password/updatedAt values set above would be ignored on flush. Recompute
        // it so they are included in the UPDATE.
        $em = $args->getObjectManager();
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet(
            $em->getClassMetadata(User::class),
            $user,
        );
    }

    private function hashPassword(User $user): void
    {
        $plainPassword = $user->getPlainPassword();
        if (null === $plainPassword) {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setPlainPassword('');
    }
}
