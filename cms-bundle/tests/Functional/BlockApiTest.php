<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

final class BlockApiTest extends AdminApiTestCase
{
    private const string ADMIN_EMAIL = 'block-api-admin@example.test';
    private const string SLUG_PREFIX = 'block-api-test';

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

    public function testCreateRichTextBlockRendersHtml(): void
    {
        $articleId = $this->createArticle('one');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => 'rich_text',
            'position' => 0,
            'data' => ['delta' => ['ops' => [['insert' => "Hello block\n"]]]],
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonResponse();
        self::assertIsInt($body['id']);
        self::assertSame($articleId, $body['page']);
        self::assertSame('rich_text', $body['type']);
        self::assertIsString($body['renderedHtml']);
        self::assertStringContainsString('Hello block', $body['renderedHtml']);
        self::assertNull($body['imageUrl']);
    }

    public function testCreateHeroWithoutHeadingReturnsNestedViolationPath(): void
    {
        $articleId = $this->createArticle('hero');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => 'hero',
            'position' => 0,
            'data' => [],
        ]);

        $this->assertViolationPath('data.heading');
    }

    public function testCreateWithUnknownPageReturnsPageViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => 99999999,
            'type' => 'hero',
            'position' => 0,
            'data' => ['heading' => 'Orphan'],
        ]);

        $this->assertViolationPath('page');
    }

    public function testCreateWithoutPageReturnsPageViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'type' => 'hero',
            'position' => 0,
            'data' => ['heading' => 'No owner'],
        ]);

        $this->assertViolationPath('page');
    }

    public function testPatchAppliesOnlySentFields(): void
    {
        $articleId = $this->createArticle('patch');
        $blockId = $this->createHeroBlock($articleId, 'Keep me');

        $this->client->jsonRequest('PATCH', '/api/blocks/'.$blockId, ['name' => 'Named block']);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame('Named block', $body['name']);
        self::assertSame('hero', $body['type']);
        self::assertSame(['heading' => 'Keep me'], $body['data']);
        self::assertSame($articleId, $body['page']);
    }

    public function testDeleteReturns204(): void
    {
        $articleId = $this->createArticle('delete');
        $blockId = $this->createHeroBlock($articleId, 'Doomed');

        $this->client->request('DELETE', '/api/blocks/'.$blockId);
        self::assertResponseStatusCodeSame(204);

        $this->requestJson('GET', '/api/blocks/'.$blockId);
        self::assertResponseStatusCodeSame(404);
    }

    private function createArticle(string $slugSuffix): int
    {
        $this->client->jsonRequest('POST', '/api/articles', [
            'title' => 'Block Api Test Article',
            'slug' => self::SLUG_PREFIX.'-'.$slugSuffix,
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function createHeroBlock(int $articleId, string $heading): int
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => 'hero',
            'position' => 0,
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
