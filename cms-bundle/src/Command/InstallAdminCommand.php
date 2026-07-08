<?php

declare(strict_types=1);

namespace Salix\Cms\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'salix:admin:install',
    description: 'Install the prebuilt admin UI shipped with the CMS bundle into public/admin',
)]
class InstallAdminCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Resolves inside the bundle wherever it is installed (cms-bundle/ in
        // the monorepo, vendor/salix/cms-bundle/ in a site).
        $source = \dirname(__DIR__, 2).'/public/admin';
        $target = $this->projectDir.'/public/admin';

        if (!is_file($source.'/index.html')) {
            $io->warning(sprintf(
                'The bundle contains no built admin UI (%s). Nothing installed. In the CMS monorepo, run `npm run build` first.',
                $source,
            ));

            return Command::SUCCESS;
        }

        if (realpath($source) === realpath($target)) {
            $io->success('Admin UI already in place.');

            return Command::SUCCESS;
        }

        new Filesystem()->mirror($source, $target, options: ['delete' => true]);

        $io->success(sprintf('Admin UI installed to %s.', $target));

        return Command::SUCCESS;
    }
}
