<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Salix\Cms\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiSecurityTest extends WebTestCase
{
    private const string ADMIN_EMAIL = 'api-security-admin@example.test';
    private const string EDITOR_EMAIL = 'api-security-editor@example.test';
    private const string PASSWORD = 'test-password';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->removeTestUsers();
        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $this->createUser(self::EDITOR_EMAIL, []);
    }

    protected function tearDown(): void
    {
        $this->removeTestUsers();

        parent::tearDown();
    }

    public function testAnonymousApiRequestGetsJson401(): void
    {
        $this->requestJson('GET', '/api/articles');

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Authentication required.'], $this->jsonResponse());
    }

    public function testAnonymousAuthMeGets401(): void
    {
        $this->client->request('GET', '/api/auth/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithInvalidCredentialsIsRejected(): void
    {
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => self::ADMIN_EMAIL,
            'password' => 'wrong-password',
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['error' => 'Invalid credentials.'], $this->jsonResponse());

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    public function testAuthenticatedNonAdminGets403(): void
    {
        $this->login(self::EDITOR_EMAIL);

        $this->requestJson('GET', '/api/articles');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminGets403WithBrowserAcceptHeader(): void
    {
        $this->login(self::EDITOR_EMAIL);

        $this->client->request('GET', '/api/articles');

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
    }

    public function testAdminCanLogInAccessApiAndLogOut(): void
    {
        $login = $this->login(self::ADMIN_EMAIL);
        self::assertSame(self::ADMIN_EMAIL, $login['email']);
        self::assertContains('ROLE_ADMIN', $login['roles']);

        $this->requestJson('GET', '/api/articles');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseIsSuccessful();
        self::assertSame(self::ADMIN_EMAIL, $this->jsonResponse()['email']);

        $this->client->request('POST', '/api/auth/logout');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    private function requestJson(string $method, string $uri): void
    {
        $this->client->request($method, $uri, server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * @return array<string, mixed> the JSON login response body
     */
    private function login(string $email): array
    {
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);

        self::assertResponseIsSuccessful();

        return $this->jsonResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, array $roles): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setName('API Security Test User');
        $user->setRoles($roles);
        $user->setPlainPassword(self::PASSWORD);

        $entityManager->persist($user);
        $entityManager->flush();
    }

    private function removeTestUsers(): void
    {
        self::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM Salix\Cms\Entity\User u WHERE u.email IN (:emails)')
            ->setParameter('emails', [self::ADMIN_EMAIL, self::EDITOR_EMAIL])
            ->execute();
    }
}
