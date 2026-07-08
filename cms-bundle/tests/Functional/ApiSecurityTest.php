<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

final class ApiSecurityTest extends AdminApiTestCase
{
    private const string ADMIN_EMAIL = 'api-security-admin@example.test';
    private const string EDITOR_EMAIL = 'api-security-editor@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUser(self::ADMIN_EMAIL, ['ROLE_ADMIN']);
        $this->createUser(self::EDITOR_EMAIL, []);
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

    public function testNonAdminGets403JsonWithBrowserAcceptHeader(): void
    {
        $this->login(self::EDITOR_EMAIL);

        $this->client->request('GET', '/api/articles');

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertSame(['error' => 'Access Denied.'], $this->jsonResponse());
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
}
