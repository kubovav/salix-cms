<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Validated installation input. The raw HTTP request is sanitised in the
 * controller; this object only carries known-good values and knows how to
 * assemble a Doctrine DATABASE_URL from them.
 */
final class InstallRequest
{
    public function __construct(
        public readonly string $adminName,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        private readonly string $dbDriver,
        private readonly string $dbHost,
        private readonly int $dbPort,
        private readonly string $dbName,
        private readonly string $dbUser,
        private readonly string $dbPassword,
        private readonly string $dbServerVersion,
    ) {
    }

    public function databaseUrl(): string
    {
        [$scheme, $charset] = match ($this->dbDriver) {
            'postgresql' => ['postgresql', 'utf8'],
            default => ['mysql', 'utf8mb4'],
        };

        return sprintf(
            '%s://%s:%s@%s:%d/%s?serverVersion=%s&charset=%s',
            $scheme,
            rawurlencode($this->dbUser),
            rawurlencode($this->dbPassword),
            $this->dbHost,
            $this->dbPort,
            rawurlencode($this->dbName),
            rawurlencode($this->dbServerVersion),
            $charset,
        );
    }
}
