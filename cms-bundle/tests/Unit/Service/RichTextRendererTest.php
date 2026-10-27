<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Salix\Cms\Service\RichTextRenderer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class RichTextRendererTest extends TestCase
{
    private RichTextRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new RichTextRenderer();
    }

    public function testRenderEmptyDelta(): void
    {
        self::assertSame('', $this->renderer->render([]));
    }

    /**
     * @param list<array<string, mixed>> $ops
     */
    #[DataProvider('badgeDeltaProvider')]
    public function testRenderBadge(array $ops, string $expected): void
    {
        self::assertSame($expected, $this->renderer->render(['ops' => $ops]));
    }

    /**
     * @return iterable<string, array{list<array<string, mixed>>, string}>
     */
    public static function badgeDeltaProvider(): iterable
    {
        yield 'inside a paragraph' => [
            [
                ['insert' => 'Ship '],
                ['insert' => 'Beta', 'attributes' => ['badge' => 'success']],
                ['insert' => " today\n"],
            ],
            '<p>Ship <span class="badge text-bg-success">Beta</span> today</p>',
        ];

        yield 'wrapping a character format' => [
            [
                ['insert' => 'Hot', 'attributes' => ['badge' => 'danger', 'bold' => true]],
                ['insert' => "\n"],
            ],
            '<p><span class="badge text-bg-danger"><strong>Hot</strong></span></p>',
        ];

        yield 'inside a heading' => [
            [
                ['insert' => 'Pricing '],
                ['insert' => 'v2', 'attributes' => ['badge' => 'primary']],
                ['insert' => "\n", 'attributes' => ['header' => 2]],
            ],
            '<h2>Pricing <span class="badge text-bg-primary">v2</span></h2>',
        ];
    }

    /**
     * An attribute that no listener claims makes the parser drop the line, so an unusable variant
     * has to keep its text and merely lose the badge.
     *
     * @param mixed $variant
     */
    #[DataProvider('unusableVariantProvider')]
    public function testRenderKeepsTextForUnusableVariant($variant): void
    {
        $html = $this->renderer->render(['ops' => [
            ['insert' => 'Keep '],
            ['insert' => 'this', 'attributes' => ['badge' => $variant]],
            ['insert' => " text\n"],
        ]]);

        self::assertSame('<p>Keep this text</p>', $html);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableVariantProvider(): iterable
    {
        yield 'unknown name' => ['chartreuse'];
        yield 'injection attempt' => ['evil"><script>alert(1)</script>'];
        yield 'null' => [null];
        yield 'non-string' => [42];
    }

    /**
     * The rendered HTML is sanitized before it is stored, so the badge markup has to survive the
     * bundle's `block_html` allowlist (see SalixCmsBundle::prependExtension()).
     */
    public function testBadgeMarkupSurvivesSanitizer(): void
    {
        $html = $this->renderer->render(['ops' => [
            ['insert' => 'Beta', 'attributes' => ['badge' => 'info']],
            ['insert' => "\n"],
        ]]);

        $sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig())
                ->allowElement('p', ['class'])
                ->allowElement('span', ['class']),
        );

        self::assertSame($html, $sanitizer->sanitize($html));
    }
}
