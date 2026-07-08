<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

final class ArticleApiTest extends AdminApiTestCase
{
    private const string ADMIN_EMAIL = 'article-api-admin@example.test';
    private const string SLUG_PREFIX = 'article-api-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeTestArticles();
        $this->loginAsAdmin(self::ADMIN_EMAIL);
    }

    protected function tearDown(): void
    {
        $this->removeTestArticles();

        parent::tearDown();
    }

    public function testCreateReturnsReadShape(): void
    {
        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => 'Article Api Test One',
            'slug' => self::SLUG_PREFIX.'-one',
            'metaDescription' => 'A test article.',
            'published' => true,
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonResponse();
        self::assertIsInt($body['id']);
        self::assertSame(self::SLUG_PREFIX.'-one', $body['slug']);
        self::assertSame('Article Api Test One', $body['title']);
        self::assertSame('A test article.', $body['metaDescription']);
        self::assertTrue($body['published']);
        self::assertIsString($body['updatedAt']);
        self::assertSame(0, $body['blockCount']);
        self::assertSame([], $body['blocks']);
    }

    public function testCreateWithoutSlugDerivesSlugFromTitle(): void
    {
        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => 'Article Api Test Derived',
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(self::SLUG_PREFIX.'-derived', $this->jsonResponse()['slug']);
    }

    public function testCreateWithDuplicateSlugReturnsSlugViolation(): void
    {
        $this->createArticle('Article Api Test Dup', self::SLUG_PREFIX.'-dup');

        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => 'Another Title',
            'slug' => self::SLUG_PREFIX.'-dup',
        ]);

        $this->assertViolationPath('slug');
    }

    public function testPatchAppliesOnlySentFields(): void
    {
        $id = $this->createArticle('Article Api Test Patch', self::SLUG_PREFIX.'-patch', published: true);

        $this->client->jsonRequest('PATCH', '/api/articles/'.$id, ['title' => 'Renamed Title']);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame('Renamed Title', $body['title']);
        self::assertSame(self::SLUG_PREFIX.'-patch', $body['slug']);
        self::assertTrue($body['published']);
    }

    public function testPatchWithBlankSlugRederivesFromTitle(): void
    {
        $id = $this->createArticle('Article Api Test Rederive Me', self::SLUG_PREFIX.'-old-slug');

        $this->client->jsonRequest('PATCH', '/api/articles/'.$id, ['slug' => '']);

        self::assertResponseIsSuccessful();
        self::assertSame(self::SLUG_PREFIX.'-rederive-me', $this->jsonResponse()['slug']);
    }

    public function testListIsOrderedByUpdatedAtDesc(): void
    {
        $this->createArticle('Article Api Test Older', self::SLUG_PREFIX.'-older');
        $this->createArticle('Article Api Test Newer', self::SLUG_PREFIX.'-newer');

        // updatedAt has second granularity; push one row into the past to make the order deterministic.
        $this->em()->getConnection()->executeStatement(
            'UPDATE content_page SET updated_at = ? WHERE slug = ?',
            ['2020-01-01 00:00:00', self::SLUG_PREFIX.'-older'],
        );

        $this->requestJson('GET', '/api/articles');

        self::assertResponseIsSuccessful();
        $slugs = array_column($this->jsonResponse(), 'slug');
        $newerAt = array_search(self::SLUG_PREFIX.'-newer', $slugs, true);
        $olderAt = array_search(self::SLUG_PREFIX.'-older', $slugs, true);
        self::assertIsInt($newerAt);
        self::assertIsInt($olderAt);
        self::assertLessThan($olderAt, $newerAt);
    }

    public function testGetEmbedsBlocksInPositionOrder(): void
    {
        $id = $this->createArticle('Article Api Test Blocks', self::SLUG_PREFIX.'-blocks');
        $this->createBlock($id, position: 1, heading: 'Second');
        $this->createBlock($id, position: 0, heading: 'First');

        $this->requestJson('GET', '/api/articles/'.$id);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame(2, $body['blockCount']);
        self::assertSame([0, 1], array_column($body['blocks'], 'position'));
        self::assertSame([$id, $id], array_column($body['blocks'], 'page'));
    }

    public function testDeleteRemovesArticleAndItsBlocks(): void
    {
        $id = $this->createArticle('Article Api Test Delete', self::SLUG_PREFIX.'-delete');
        $blockId = $this->createBlock($id, position: 0, heading: 'Doomed');

        $this->client->request('DELETE', '/api/articles/'.$id);
        self::assertResponseStatusCodeSame(204);

        $this->requestJson('GET', '/api/articles/'.$id);
        self::assertResponseStatusCodeSame(404);

        $this->requestJson('GET', '/api/blocks/'.$blockId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testGetUnknownArticleReturns404Json(): void
    {
        $this->requestJson('GET', '/api/articles/99999999');

        self::assertResponseStatusCodeSame(404);
        self::assertSame(['error' => 'Not found.'], $this->jsonResponse());
    }

    private function createArticle(string $title, string $slug, bool $published = false): int
    {
        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => $title,
            'slug' => $slug,
            'published' => $published,
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function createBlock(int $articleId, int $position, string $heading): int
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => 'hero',
            'position' => $position,
            'data' => ['heading' => $heading],
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function removeTestArticles(): void
    {
        $this->em()
            ->createQuery('DELETE FROM Salix\Cms\Entity\ContentPage p WHERE p.slug LIKE :prefix')
            ->setParameter('prefix', self::SLUG_PREFIX.'%')
            ->execute();
    }
}
