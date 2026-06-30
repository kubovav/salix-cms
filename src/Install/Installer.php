<?php

declare(strict_types=1);

namespace App\Install;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\Command\MigrateCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Drives a one-shot, web-based installation for shared hosting where no shell
 * access is available: validates the submitted database, runs the migrations,
 * creates the first admin account, persists the connection to .env.local and
 * then locks itself so the installer can never be replayed.
 */
final class Installer
{
    /**
     * Maps the schemes our installer can produce to DBAL driver names. We keep
     * our own minimal map rather than reaching into doctrine-bundle internals.
     */
    private const array SCHEME_MAP = [
        'mysql' => 'pdo_mysql',
        'postgresql' => 'pdo_pgsql',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * The installer is locked once installation has completed. The lock lives
     * outside the web root and short-circuits both GET and POST so a finished
     * deployment can never be re-installed (which would otherwise let an
     * attacker repoint the database or mint a fresh admin account).
     */
    public function isLocked(): bool
    {
        return is_file($this->lockFile());
    }

    /**
     * Runs the full installation. Each step throws {@see InstallException} on
     * failure; nothing is written to disk (env file or lock) until the database
     * is fully provisioned, so a failed attempt can simply be retried.
     */
    public function install(InstallRequest $request): void
    {
        if ($this->isLocked()) {
            throw new InstallException('The application is already installed.');
        }

        $databaseUrl = $request->databaseUrl();
        $connection = $this->createConnection($databaseUrl);

        try {
            $connection->executeQuery('SELECT 1');
        } catch (\Throwable $e) {
            throw new InstallException('Could not connect to the database: '.$e->getMessage(), previous: $e);
        }

        $this->runMigrations($connection);
        $this->createAdminUser($connection, $request);

        // Persist configuration only after the database is provisioned, so the
        // app never boots against a half-installed database.
        $this->writeEnvLocal($databaseUrl);
        $this->lock($request->adminEmail);

        $connection->close();
    }

    private function runMigrations(Connection $connection): void
    {
        try {
            $dependencyFactory = DependencyFactory::fromConnection(
                new ConfigurationArray([
                    'migrations_paths' => [
                        'DoctrineMigrations' => $this->projectDir.'/migrations',
                    ],
                ]),
                new ExistingConnection($connection),
            );

            // Drive the supported console command rather than the migrator's
            // internal API: it initialises the metadata storage, computes the
            // plan and runs everything non-interactively against our connection.
            $command = new MigrateCommand($dependencyFactory);
            $input = new ArrayInput([
                'version' => 'latest',
                '--allow-no-migration' => true,
            ]);
            $input->setInteractive(false);
            $output = new BufferedOutput();

            if (0 !== $command->run($input, $output)) {
                throw new InstallException('Database migrations failed: '.trim($output->fetch()));
            }
        } catch (InstallException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InstallException('Database migrations failed: '.$e->getMessage(), previous: $e);
        }
    }

    private function createAdminUser(Connection $connection, InstallRequest $request): void
    {
        $userTable = $connection->getDatabasePlatform()->quoteSingleIdentifier('user');

        try {
            $existing = (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM '.$userTable.' WHERE email = ?',
                [$request->adminEmail],
            );
            if ($existing > 0) {
                throw new InstallException(sprintf('A user with the email "%s" already exists.', $request->adminEmail));
            }

            $hasher = $this->passwordHasherFactory->getPasswordHasher(User::class);

            $connection->insert($userTable, [
                'email' => $request->adminEmail,
                'roles' => json_encode(['ROLE_ADMIN'], \JSON_THROW_ON_ERROR),
                'password' => $hasher->hash($request->adminPassword),
                'name' => $request->adminName,
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        } catch (InstallException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InstallException('Could not create the admin account: '.$e->getMessage(), previous: $e);
        }
    }

    private function createConnection(string $databaseUrl): Connection
    {
        $params = (new DsnParser(self::SCHEME_MAP))->parse($databaseUrl);

        return DriverManager::getConnection($params);
    }

    /**
     * Upsert DATABASE_URL (and a generated APP_SECRET, if missing) into
     * .env.local. This file is git-ignored and sits outside the web root, which
     * is the Symfony-recommended place for environment-specific secrets on a
     * deployment without a build step.
     */
    private function writeEnvLocal(string $databaseUrl): void
    {
        $path = $this->projectDir.'/.env.local';
        $content = is_file($path) ? (string) file_get_contents($path) : '';

        $content = $this->upsertEnv($content, 'DATABASE_URL', $databaseUrl);

        if (!$this->envKeyHasValue($content, 'APP_SECRET') && '' === (string) ($_SERVER['APP_SECRET'] ?? '')) {
            $content = $this->upsertEnv($content, 'APP_SECRET', bin2hex(random_bytes(16)));
        }

        if (false === @file_put_contents($path, $content, \LOCK_EX)) {
            throw new InstallException('Could not write configuration to .env.local. Check filesystem permissions.');
        }
    }

    private function upsertEnv(string $content, string $key, string $value): string
    {
        $line = sprintf('%s="%s"', $key, addcslashes($value, '"\\'));
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content)) {
            return (string) preg_replace($pattern, $line, $content);
        }

        return rtrim($content, "\n")."\n".$line."\n";
    }

    private function envKeyHasValue(string $content, string $key): bool
    {
        return 1 === preg_match('/^'.preg_quote($key, '/').'=.+$/m', $content);
    }

    private function lock(string $adminEmail): void
    {
        $payload = json_encode([
            'installed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'admin_email' => $adminEmail,
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

        if (false === @file_put_contents($this->lockFile(), $payload, \LOCK_EX)) {
            throw new InstallException('Installation succeeded but the lock file could not be written. Remove write access to the installer manually.');
        }
    }

    private function lockFile(): string
    {
        return $this->projectDir.'/var/installed.lock';
    }
}
