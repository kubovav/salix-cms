<?php

declare(strict_types=1);

namespace App\Install;

/**
 * Validated installation input. The raw HTTP request is sanitised in the
 * controller; this object only carries known-good values and knows how to
 * assemble a Doctrine DATABASE_URL from them.
 */
final readonly class InstallRequest
{
    public function __construct(
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
        private string $dbDriver,
        private string $dbHost,
        private int $dbPort,
        private string $dbName,
        private string $dbUser,
        private string $dbPassword,
        private string $dbServerVersion,
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
