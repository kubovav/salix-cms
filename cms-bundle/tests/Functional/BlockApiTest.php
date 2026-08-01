<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

use Salix\Cms\Config\BlockType;

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
            'type' => BlockType::RICH_TEXT->value,
            'position' => 0,
            'data' => ['delta' => ['ops' => [['insert' => "Hello block\n"]]]],
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonResponse();
        self::assertIsInt($body['id']);
        self::assertSame($articleId, $body['page']);
        self::assertSame(BlockType::RICH_TEXT->value, $body['type']);
        self::assertIsString($body['renderedHtml']);
        self::assertStringContainsString('Hello block', $body['renderedHtml']);
        self::assertNull($body['imageUrl']);
    }

    public function testCreateHeroWithoutHeadingReturnsNestedViolationPath(): void
    {
        $articleId = $this->createArticle('hero');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::HERO->value,
            'position' => 0,
            'data' => [],
        ]);

        $this->assertViolationPath('data.heading');
    }

    public function testCreateWithUnknownPageReturnsPageViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => 99999999,
            'type' => BlockType::HERO->value,
            'position' => 0,
            'data' => ['heading' => 'Orphan'],
        ]);

        $this->assertViolationPath('page');
    }

    public function testCreateWithoutPageReturnsPageViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/blocks', [
            'type' => BlockType::HERO->value,
            'position' => 0,
            'data' => ['heading' => 'No owner'],
        ]);

        $this->assertViolationPath('page');
    }

    public function testCreateCtaWithJavascriptUrlIsRejected(): void
    {
        $articleId = $this->createArticle('cta-xss');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::CTA->value,
            'position' => 0,
            'data' => ['heading' => 'Click', 'button_text' => 'Go', 'button_url' => 'javascript:alert(1)'],
        ]);

        $this->assertViolationPath('data.button_url');
    }

    public function testCreateCtaWithRelativeUrlIsAccepted(): void
    {
        $articleId = $this->createArticle('cta-ok');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::CTA->value,
            'position' => 0,
            'data' => ['heading' => 'Click', 'button_text' => 'Go', 'button_url' => '/contact'],
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testCreateWithUnknownDataKeyIsRejected(): void
    {
        $articleId = $this->createArticle('unknown-key');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::HERO->value,
            'position' => 0,
            'data' => ['heading' => 'Hi', 'evil' => ['arbitrary' => 'payload']],
        ]);

        $this->assertViolationPath('data.evil');
    }

    public function testCreateImageWithTraversalFilenameIsRejected(): void
    {
        $articleId = $this->createArticle('bad-filename');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::IMAGE->value,
            'position' => 0,
            'data' => ['alt' => 'Alt', 'filename' => '../../evil.php'],
        ]);

        $this->assertViolationPath('data.filename');
    }

    public function testCreatePricingPlanWithJavascriptButtonUrlIsRejected(): void
    {
        $articleId = $this->createArticle('plan-xss');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::PRICING_TABLE->value,
            'position' => 0,
            'data' => ['plans' => [['name' => 'Basic', 'button_text' => 'Buy', 'button_url' => 'javascript:alert(1)']]],
        ]);

        $this->assertViolationPath('data.plans[0].button_url');
    }

    public function testCreateHeroWithWrongTypedOptionalFieldIsRejected(): void
    {
        $articleId = $this->createArticle('bad-type');

        $this->client->jsonRequest('POST', '/api/blocks', [
            'page' => $articleId,
            'type' => BlockType::HERO->value,
            'position' => 0,
            'data' => ['heading' => 'Hi', 'subtext' => ['not' => 'a string']],
        ]);

        $this->assertViolationPath('data.subtext');
    }

    public function testPatchAppliesOnlySentFields(): void
    {
        $articleId = $this->createArticle('patch');
        $blockId = $this->createHeroBlock($articleId, 'Keep me');

        $this->client->jsonRequest('PATCH', '/api/blocks/'.$blockId, ['name' => 'Named block']);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame('Named block', $body['name']);
        self::assertSame(BlockType::HERO->value, $body['type']);
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
            'type' => BlockType::HERO->value,
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
