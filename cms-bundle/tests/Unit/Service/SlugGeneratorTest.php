<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Salix\Cms\Repository\ContentPageRepository;
use Salix\Cms\Service\SlugGenerator;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    private ContentPageRepository&Stub $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createStub(ContentPageRepository::class);

        $this->generator = new SlugGenerator($this->repository, new AsciiSlugger());
    }

    public function testGenerateUniqueSlug(): void
    {
        $this->repository->method('slugExists')->willReturn(false);

        $slug = $this->generator->generateUniqueSlug('Test Page');
        self::assertSame('test-page', $slug);
    }

    public function testGenerateSlugAppendsSuffixWhenBaseTaken(): void
    {
        $taken = ['test-page', 'test-page-1'];
        $this->repository->method('slugExists')->willReturnCallback(
            static fn (string $slug): bool => \in_array($slug, $taken, true)
        );

        $slug = $this->generator->generateUniqueSlug('Test Page');
        self::assertSame('test-page-2', $slug);
    }

    public function testGenerateUniqueSlugTruncatesBaseToFitSuffix(): void
    {
        $this->repository->method('slugExists')->willReturnCallback(
            static fn (string $slug): bool => !str_ends_with($slug, '-1')
        );

        $slug = $this->generator->generateUniqueSlug(str_repeat('a', 200));

        self::assertLessThanOrEqual(180, \strlen($slug));
        self::assertStringEndsWith('-1', $slug);
    }

    public function testSlugifyTruncatesWithoutTrailingDash(): void
    {
        // 40 "word " pairs = alternating word/dash; cut at 180 lands exactly on a separator.
        $title = str_repeat('word ', 40);

        $slug = $this->generator->slugify($title);

        self::assertLessThanOrEqual(180, \strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }

    public function testSlugifyTransliteratesNonLatinScriptsToAscii(): void
    {
        // Exact transliteration is AsciiSlugger/ICU's call, not ours — assert the
        // contract we own (ASCII, lowercase, dash-separated), not a pinned mapping.
        foreach (['тестовая страница', 'テストページ'] as $title) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $this->generator->slugify($title));
        }
    }

    public function testSlugifyReturnsEmptyStringForUntransliterableTitle(): void
    {
        self::assertSame('', $this->generator->slugify('🎉🎊'));
    }

    public function testGenerateUniqueSlugForwardsExcludeIdToRepository(): void
    {
        /** @var ContentPageRepository&MockObject $repository */
        $repository = $this->createMock(ContentPageRepository::class);
        $repository->expects(self::once())
            ->method('slugExists')
            ->with('test-page', 42)
            ->willReturn(false);

        $generator = new SlugGenerator($repository, new AsciiSlugger());

        self::assertSame('test-page', $generator->generateUniqueSlug('Test Page', 42));
    }
}
