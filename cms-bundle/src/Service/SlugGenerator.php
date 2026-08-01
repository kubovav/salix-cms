<?php

declare(strict_types=1);

namespace Salix\Cms\Service;

use Salix\Cms\Repository\ContentPageRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Builds URL-safe, collision-free slugs for content pages.
 */
class SlugGenerator
{
    /** Matches the ContentPage::$slug column length. */
    private const int MAX_LENGTH = 180;

    public function __construct(
        private readonly ContentPageRepository $repository,
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
            $suffix = '-'.$counter++;
            $slug = $this->truncate($base, self::MAX_LENGTH - \strlen($suffix)).$suffix;
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

    private function slugExists(string $slug, ?int $excludeId): bool
    {
        return $this->repository->slugExists($slug, $excludeId);
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
