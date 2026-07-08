<?php

declare(strict_types=1);

namespace Salix\Cms;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SalixCmsBundle extends AbstractBundle
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import(\dirname(__DIR__).'/config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SalixCms' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Salix\Cms\Entity',
                        'alias' => 'SalixCms',
                    ],
                ],
            ],
        ]);

        $builder->prependExtensionConfig('doctrine_migrations', [
            'migrations_paths' => [
                'Salix\Cms\Migrations' => \dirname(__DIR__).'/migrations',
            ],
        ]);

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [\dirname(__DIR__).'/assets' => 'salix-cms'],
            ],
        ]);

        $builder->prependExtensionConfig('framework', [
            'html_sanitizer' => [
                'sanitizers' => [
                    'block_html' => [
                        'allow_relative_links' => true,
                        'allowed_link_schemes' => ['https', 'http', 'mailto'],
                        'max_input_length' => 100000,
                        'allow_elements' => [
                            'p' => ['class'],
                            'span' => ['class'],
                            'br' => [],
                            'strong' => [],
                            'b' => [],
                            'em' => [],
                            'i' => [],
                            'u' => [],
                            's' => [],
                            'del' => [],
                            'ol' => ['class'],
                            'ul' => ['class'],
                            'li' => ['class'],
                            'h2' => ['class'],
                            'h3' => ['class'],
                            'blockquote' => [],
                            'pre' => [],
                            'code' => [],
                            'a' => ['href', 'target', 'rel'],
                        ],
                        'force_attributes' => [
                            'a' => ['rel' => 'noopener noreferrer'],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
