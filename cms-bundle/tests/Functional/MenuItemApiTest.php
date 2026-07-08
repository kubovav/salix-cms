<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

final class MenuItemApiTest extends AdminApiTestCase
{
    private const string ADMIN_EMAIL = 'menu-api-admin@example.test';
    private const string LABEL_PREFIX = 'MenuApiTest';
    private const string SLUG_PREFIX = 'menu-api-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeTestData();
        $this->loginAsAdmin(self::ADMIN_EMAIL);
    }

    protected function tearDown(): void
    {
        $this->removeTestData();

        parent::tearDown();
    }

    public function testCreateWithPageAndParentIds(): void
    {
        $articleId = $this->createArticle();
        $parentId = $this->createMenuItem(self::LABEL_PREFIX.' Parent', 'main', 0);

        $this->client->jsonRequest('POST', '/api/menu_items', [
            'label' => self::LABEL_PREFIX.' Child',
            'menuName' => 'main',
            'position' => 1,
            'page' => $articleId,
            'parent' => $parentId,
        ]);

        self::assertResponseStatusCodeSame(201);
        $child = $this->jsonResponse();
        self::assertSame($articleId, $child['page']);
        self::assertSame($parentId, $child['parent']);
        self::assertTrue($child['enabled']);

        $this->requestJson('GET', '/api/menu_items/'.$parentId);
        self::assertResponseIsSuccessful();
        self::assertSame([$child['id']], $this->jsonResponse()['children']);
    }

    public function testCreateWithUnknownPageReturnsPageViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/menu_items', [
            'label' => self::LABEL_PREFIX.' Broken',
            'menuName' => 'main',
            'position' => 0,
            'page' => 99999999,
        ]);

        $this->assertViolationPath('page');
    }

    public function testListIsOrderedByMenuNameThenPosition(): void
    {
        $this->createMenuItem(self::LABEL_PREFIX.' Main B', 'main', 1);
        $this->createMenuItem(self::LABEL_PREFIX.' Footer A', 'footer', 0);
        $this->createMenuItem(self::LABEL_PREFIX.' Main A', 'main', 0);

        $this->requestJson('GET', '/api/menu_items');

        self::assertResponseIsSuccessful();
        $labels = array_values(array_filter(
            array_column($this->jsonResponse(), 'label'),
            static fn (string $label): bool => str_starts_with($label, self::LABEL_PREFIX),
        ));
        self::assertSame(
            [self::LABEL_PREFIX.' Footer A', self::LABEL_PREFIX.' Main A', self::LABEL_PREFIX.' Main B'],
            $labels,
        );
    }

    public function testFooterItemWithParentIsRejected(): void
    {
        $parentId = $this->createMenuItem(self::LABEL_PREFIX.' Main Parent', 'main', 0);

        $this->client->jsonRequest('POST', '/api/menu_items', [
            'label' => self::LABEL_PREFIX.' Footer Child',
            'menuName' => 'footer',
            'position' => 0,
            'parent' => $parentId,
        ]);

        $this->assertViolationPath('parent');
    }

    public function testPatchCanClearParent(): void
    {
        $parentId = $this->createMenuItem(self::LABEL_PREFIX.' Parent', 'main', 0);
        $childId = $this->createMenuItem(self::LABEL_PREFIX.' Child', 'main', 1, parent: $parentId);

        $this->client->jsonRequest('PATCH', '/api/menu_items/'.$childId, ['parent' => null]);

        self::assertResponseIsSuccessful();
        self::assertNull($this->jsonResponse()['parent']);
    }

    private function createArticle(): int
    {
        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => 'Menu Api Test Article',
            'slug' => self::SLUG_PREFIX.'-page',
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function createMenuItem(string $label, string $menuName, int $position, ?int $parent = null): int
    {
        $payload = [
            'label' => $label,
            'menuName' => $menuName,
            'position' => $position,
        ];
        if (null !== $parent) {
            $payload['parent'] = $parent;
        }

        $this->client->jsonRequest('POST', '/api/menu_items', $payload);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function removeTestData(): void
    {
        $entityManager = $this->em();
        $entityManager
            ->createQuery('DELETE FROM Salix\Cms\Entity\MenuItem m WHERE m.label LIKE :prefix')
            ->setParameter('prefix', self::LABEL_PREFIX.'%')
            ->execute();
        $entityManager
            ->createQuery('DELETE FROM Salix\Cms\Entity\ContentPage p WHERE p.slug LIKE :prefix')
            ->setParameter('prefix', self::SLUG_PREFIX.'%')
            ->execute();
    }
}
