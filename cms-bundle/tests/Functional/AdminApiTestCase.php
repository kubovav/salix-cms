<?php

declare(strict_types=1);

namespace Salix\Cms\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Salix\Cms\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AdminApiTestCase extends WebTestCase
{
    protected const string PASSWORD = 'test-password';

    protected KernelBrowser $client;

    /** @var list<string> */
    private array $createdUserEmails = [];

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    protected function tearDown(): void
    {
        $this->removeUsers($this->createdUserEmails);
        $this->createdUserEmails = [];

        parent::tearDown();
    }

    protected function em(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $email, array $roles): void
    {
        $this->removeUsers([$email]);

        $user = new User();
        $user->setEmail($email);
        $user->setName('API Test User');
        $user->setRoles($roles);
        $user->setPlainPassword(self::PASSWORD);

        $entityManager = $this->em();
        $entityManager->persist($user);
        $entityManager->flush();

        $this->createdUserEmails[] = $email;
    }

    /**
     * @return array<string, mixed> the JSON login response body
     */
    protected function loginAsAdmin(string $email): array
    {
        $this->createUser($email, ['ROLE_ADMIN']);

        return $this->login($email);
    }

    /**
     * @return array<string, mixed> the JSON login response body
     */
    protected function login(string $email): array
    {
        $this->client->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);

        self::assertResponseIsSuccessful();

        return $this->jsonResponse();
    }

    protected function requestJson(string $method, string $uri): void
    {
        $this->client->request($method, $uri, server: ['HTTP_ACCEPT' => 'application/json']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    protected function assertViolationPath(string $propertyPath): void
    {
        self::assertResponseStatusCodeSame(422);

        $body = $this->jsonResponse();
        self::assertArrayHasKey('violations', $body);
        self::assertIsArray($body['violations']);
        self::assertContains($propertyPath, array_column($body['violations'], 'propertyPath'));
    }

    /**
     * @param list<string> $emails
     */
    private function removeUsers(array $emails): void
    {
        if ([] === $emails) {
            return;
        }

        $this->em()
            ->createQuery('DELETE FROM Salix\Cms\Entity\User u WHERE u.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->execute();
    }
}
