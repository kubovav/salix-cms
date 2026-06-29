<?php

declare(strict_types=1);

namespace App\Controller;

use App\Install\Installer;
use App\Install\InstallException;
use App\Install\InstallRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class InstallController extends AbstractController
{
    private const array DRIVER_DEFAULTS = [
        'mysql' => ['port' => 3306, 'serverVersion' => '8.4'],
        'mariadb' => ['port' => 3306, 'serverVersion' => '10.11.2-MariaDB'],
        'postgresql' => ['port' => 5432, 'serverVersion' => '16'],
    ];

    /**
     * High priority so it wins over the FrontendController `/{slug}` catch-all.
     */
    #[Route('/install', name: 'app_install', methods: ['GET', 'POST'], priority: 10)]
    public function install(Request $request, Installer $installer, ValidatorInterface $validator): Response
    {
        if ($installer->isLocked()) {
            return $this->render('install/locked.html.twig', response: new Response(status: Response::HTTP_FORBIDDEN));
        }

        if (!$request->isMethod('POST')) {
            return $this->render('install/index.html.twig', [
                'errors' => [],
                'values' => $this->defaultValues(),
            ]);
        }

        if (!$this->isCsrfTokenValid('submit', (string) $request->request->get('_token'))) {
            return $this->render('install/index.html.twig', [
                'errors' => ['Invalid security token, please submit the form again.'],
                'values' => $this->submittedValues($request),
            ], new Response(status: Response::HTTP_FORBIDDEN));
        }

        $values = $this->submittedValues($request);
        $errors = $this->validate($values, $validator);

        if ([] === $errors) {
            try {
                $installer->install($this->buildRequest($values));

                return $this->render('install/success.html.twig', [
                    'adminEmail' => $values['adminEmail'],
                ]);
            } catch (InstallException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $this->render('install/index.html.twig', [
            'errors' => $errors,
            'values' => $values,
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * @return array<string, string>
     */
    private function defaultValues(): array
    {
        return [
            'adminName' => '',
            'adminEmail' => '',
            'dbDriver' => 'mysql',
            'dbHost' => 'localhost',
            'dbPort' => (string) self::DRIVER_DEFAULTS['mysql']['port'],
            'dbName' => '',
            'dbUser' => '',
            'dbServerVersion' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function submittedValues(Request $request): array
    {
        $get = static fn (string $key): string => trim((string) $request->request->get($key));

        return [
            'adminName' => $get('adminName'),
            'adminEmail' => $get('adminEmail'),
            'adminPassword' => (string) $request->request->get('adminPassword'),
            'adminPasswordConfirm' => (string) $request->request->get('adminPasswordConfirm'),
            'dbDriver' => $get('dbDriver'),
            'dbHost' => $get('dbHost'),
            'dbPort' => $get('dbPort'),
            'dbName' => $get('dbName'),
            'dbUser' => $get('dbUser'),
            'dbPassword' => (string) $request->request->get('dbPassword'),
            'dbServerVersion' => $get('dbServerVersion'),
        ];
    }

    /**
     * @param array<string, string> $values
     *
     * @return list<string>
     */
    private function validate(array $values, ValidatorInterface $validator): array
    {
        $errors = [];

        $constraints = [
            'adminName' => [new Assert\NotBlank(message: 'Admin name is required.'), new Assert\Length(max: 255)],
            'adminEmail' => [new Assert\NotBlank(message: 'Admin email is required.'), new Assert\Email(message: 'Enter a valid email address.')],
            'adminPassword' => [new Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')],
            'dbDriver' => [new Assert\Choice(choices: ['mysql', 'mariadb', 'postgresql'], message: 'Choose a supported database type.')],
            'dbHost' => [new Assert\NotBlank(message: 'Database host is required.')],
            'dbPort' => [new Assert\Regex(pattern: '/^\d{1,5}$/', message: 'Database port must be a number.')],
            'dbName' => [new Assert\NotBlank(message: 'Database name is required.')],
            'dbUser' => [new Assert\NotBlank(message: 'Database user is required.')],
        ];

        foreach ($constraints as $field => $fieldConstraints) {
            foreach ($validator->validate($values[$field], $fieldConstraints) as $violation) {
                $errors[] = (string) $violation->getMessage();
            }
        }

        if ($values['adminPassword'] !== $values['adminPasswordConfirm']) {
            $errors[] = 'Passwords do not match.';
        }

        $port = (int) $values['dbPort'];
        if ('' !== $values['dbPort'] && ($port < 1 || $port > 65535)) {
            $errors[] = 'Database port must be between 1 and 65535.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $values
     */
    private function buildRequest(array $values): InstallRequest
    {
        $driver = $values['dbDriver'];
        $defaults = self::DRIVER_DEFAULTS[$driver] ?? self::DRIVER_DEFAULTS['mysql'];

        $port = '' !== $values['dbPort'] ? (int) $values['dbPort'] : $defaults['port'];
        $serverVersion = '' !== $values['dbServerVersion'] ? $values['dbServerVersion'] : $defaults['serverVersion'];

        return new InstallRequest(
            adminName: $values['adminName'],
            adminEmail: $values['adminEmail'],
            adminPassword: $values['adminPassword'],
            dbDriver: $driver,
            dbHost: $values['dbHost'],
            dbPort: $port,
            dbName: $values['dbName'],
            dbUser: $values['dbUser'],
            dbPassword: $values['dbPassword'],
            dbServerVersion: $serverVersion,
        );
    }
}
