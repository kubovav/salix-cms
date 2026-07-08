<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

final class UserApiTest extends AdminApiTestCase
{
    private const string ADMIN_EMAIL = 'user-api-admin@example.test';
    private const string EMAIL_PREFIX = 'user-api-test-';

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeTestUsers();
        $this->loginAsAdmin(self::ADMIN_EMAIL);
    }

    protected function tearDown(): void
    {
        $this->removeTestUsers();

        parent::tearDown();
    }

    public function testCreateWithoutPasswordReturnsViolation(): void
    {
        $this->client->jsonRequest('POST', '/api/users', [
            'email' => self::EMAIL_PREFIX.'nopass@example.test',
            'name' => 'No Password',
            'roles' => [],
        ]);

        $this->assertViolationPath('plainPassword');
    }

    public function testCreateReturnsUserWithoutPasswordMaterial(): void
    {
        $email = self::EMAIL_PREFIX.'created@example.test';
        $this->client->jsonRequest('POST', '/api/users', [
            'email' => $email,
            'name' => 'Created User',
            'roles' => ['ROLE_ADMIN'],
            'plainPassword' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonResponse();
        self::assertIsInt($body['id']);
        self::assertSame($email, $body['email']);
        self::assertSame('Created User', $body['name']);
        self::assertContains('ROLE_ADMIN', $body['roles']);
        self::assertContains('ROLE_USER', $body['roles']);
        self::assertArrayNotHasKey('password', $body);
        self::assertArrayNotHasKey('plainPassword', $body);

        // The password was hashed by UserPasswordListener and works for login.
        $this->login($email);
    }

    public function testPatchWithoutPasswordKeepsCurrentOne(): void
    {
        $email = self::EMAIL_PREFIX.'patched@example.test';
        $id = $this->createUserViaApi($email, 'Patch Target');

        $this->client->jsonRequest('PATCH', '/api/users/'.$id, ['name' => 'Renamed User']);

        self::assertResponseIsSuccessful();
        $body = $this->jsonResponse();
        self::assertSame('Renamed User', $body['name']);
        self::assertSame($email, $body['email']);

        $this->login($email);
    }

    public function testCreateWithDuplicateEmailReturnsEmailViolation(): void
    {
        $email = self::EMAIL_PREFIX.'dup@example.test';
        $this->createUserViaApi($email, 'Original');

        $this->client->jsonRequest('POST', '/api/users', [
            'email' => $email,
            'name' => 'Impostor',
            'roles' => [],
            'plainPassword' => self::PASSWORD,
        ]);

        $this->assertViolationPath('email');
    }

    public function testListIsOrderedByEmail(): void
    {
        $this->createUserViaApi(self::EMAIL_PREFIX.'b@example.test', 'User B');
        $this->createUserViaApi(self::EMAIL_PREFIX.'a@example.test', 'User A');

        $this->requestJson('GET', '/api/users');

        self::assertResponseIsSuccessful();
        $emails = array_values(array_filter(
            array_column($this->jsonResponse(), 'email'),
            static fn (string $email): bool => str_starts_with($email, self::EMAIL_PREFIX),
        ));
        self::assertSame(
            [self::EMAIL_PREFIX.'a@example.test', self::EMAIL_PREFIX.'b@example.test'],
            $emails,
        );
    }

    private function createUserViaApi(string $email, string $name): int
    {
        $this->client->jsonRequest('POST', '/api/users', [
            'email' => $email,
            'name' => $name,
            'roles' => ['ROLE_ADMIN'],
            'plainPassword' => self::PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonResponse()['id'];
        self::assertIsInt($id);

        return $id;
    }

    private function removeTestUsers(): void
    {
        $this->em()
            ->createQuery('DELETE FROM Salix\Cms\Entity\User u WHERE u.email LIKE :prefix')
            ->setParameter('prefix', self::EMAIL_PREFIX.'%')
            ->execute();
    }
}
