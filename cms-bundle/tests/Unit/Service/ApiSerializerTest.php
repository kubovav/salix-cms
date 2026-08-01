<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Salix\Cms\Config\BlockType;
use Salix\Cms\Entity\ContentBlock;
use Salix\Cms\Entity\ContentPage;
use Salix\Cms\Entity\MenuItem;
use Salix\Cms\Entity\User;
use Salix\Cms\Service\ApiSerializer;

final class ApiSerializerTest extends TestCase
{
    private ApiSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new ApiSerializer();
    }

    public function testArticleSerializesScalarFieldsAndBlocks(): void
    {
        $page = (new ContentPage())
            ->setSlug('about-us')
            ->setTitle('About Us')
            ->setMetaDescription('Meta')
            ->setPublished(true);
        $page->refreshUpdatedAt();

        $block = (new ContentBlock())->setType(BlockType::RICH_TEXT->value);
        $page->addBlock($block);

        $result = $this->serializer->article($page);

        self::assertNull($result['id']);
        self::assertSame('about-us', $result['slug']);
        self::assertSame('About Us', $result['title']);
        self::assertSame('Meta', $result['metaDescription']);
        self::assertTrue($result['published']);
        self::assertSame($page->getUpdatedAt()?->format(\DateTimeInterface::ATOM), $result['updatedAt']);
        self::assertSame(1, $result['blockCount']);
        self::assertCount(1, $result['blocks']);
        self::assertSame(BlockType::RICH_TEXT->value, $result['blocks'][0]['type']);
    }

    public function testArticleWithoutUpdatedAtSerializesNull(): void
    {
        $page = (new ContentPage())->setSlug('draft')->setTitle('Draft');

        $result = $this->serializer->article($page);

        self::assertNull($result['updatedAt']);
        self::assertSame([], $result['blocks']);
    }

    public function testBlockSerializesDataAndImageUrl(): void
    {
        $page = (new ContentPage())->setSlug('page')->setTitle('Page');
        $block = (new ContentBlock())
            ->setType(BlockType::IMAGE->value)
            ->setName('Hero')
            ->setPosition(2)
            ->setData(['filename' => 'hero.jpg'])
            ->setRenderedHtml('<img>')
            ->setAnchor('hero');
        $page->addBlock($block);

        $result = $this->serializer->block($block);

        self::assertSame('Hero', $result['name']);
        self::assertSame(BlockType::IMAGE->value, $result['type']);
        self::assertSame(2, $result['position']);
        self::assertSame(['filename' => 'hero.jpg'], $result['data']);
        self::assertSame('<img>', $result['renderedHtml']);
        self::assertSame('/uploads/images/hero.jpg', $result['imageUrl']);
        self::assertSame('hero', $result['anchor']);
        self::assertNull($result['page']);
    }

    public function testBlockWithoutFilenameHasNullImageUrl(): void
    {
        $block = (new ContentBlock())->setType(BlockType::RICH_TEXT->value);

        $result = $this->serializer->block($block);

        self::assertNull($result['imageUrl']);
    }

    public function testMenuItemSerializesRelationsAsIds(): void
    {
        $page = (new ContentPage())->setSlug('home')->setTitle('Home');
        $parent = (new MenuItem())->setLabel('Parent');
        $child = (new MenuItem())->setLabel('Child')->setPage($page)->setParent($parent);

        $result = $this->serializer->menuItem($child);

        self::assertSame('Child', $result['label']);
        self::assertNull($result['page']);
        self::assertNull($result['parent']);
        self::assertSame([], $result['children']);
        self::assertTrue($result['enabled']);
    }

    public function testUserSerializesRolesAndOmitsPassword(): void
    {
        $user = (new User())
            ->setEmail('admin@example.test')
            ->setName('Admin')
            ->setRoles(['ROLE_ADMIN'])
            ->setPassword('hashed-secret');

        $result = $this->serializer->user($user);

        self::assertSame('admin@example.test', $result['email']);
        self::assertSame('Admin', $result['name']);
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $result['roles']);
        self::assertArrayNotHasKey('password', $result);
    }
}
