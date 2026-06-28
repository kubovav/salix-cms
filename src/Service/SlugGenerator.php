<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContentPage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Builds URL-safe, collision-free slugs for content pages.
 */
class SlugGenerator
{
    /** Matches the ContentPage::$slug column length. */
    private const MAX_LENGTH = 180;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Returns a slugified version of $title, suffixed with -1, -2, … until unique.
     * Pass $excludeId to ignore the page being updated when checking uniqueness.
     */
    public function generateUniqueSlug(string $title, ?int $excludeId = null): string
    {
        $base = $this->slugify($title);
        $slug = $base;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $suffix = '-' . $counter++;
            $slug = $this->truncate($base, self::MAX_LENGTH - \strlen($suffix)) . $suffix;
        }

        return $slug;
    }

    /**
     * Returns '' when the title has no transliterable characters; callers must handle that
     * (DerivableSlug validation rejects an un-derivable slug before it reaches persistence).
     */
    public function slugify(string $title): string
    {
        // AsciiSlugger transliterates to ASCII (locale-independent), replaces every run of
        // non-alphanumeric characters with a single dash and trims them from the ends.
        return $this->truncate($this->slugger->slug($title)->lower()->toString(), self::MAX_LENGTH);
    }

    /**
     * Resolves the manager from the registry on every call (rather than holding a repository),
     * so the lookup keeps working after a ManagerRegistry::resetManager() during a retry.
     */
    private function slugExists(string $slug, ?int $excludeId): bool
    {
        $manager = $this->registry->getManagerForClass(ContentPage::class);
        \assert($manager instanceof EntityManagerInterface);

        $qb = $manager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ContentPage::class, 'p')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug);

        if (null !== $excludeId) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Cuts $slug to at most $max characters without leaving a trailing dash.
     */
    private function truncate(string $slug, int $max): string
    {
        if (\strlen($slug) <= $max) {
            return $slug;
        }

        return rtrim(substr($slug, 0, $max), '-');
    }
}
