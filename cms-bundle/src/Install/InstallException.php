<?php

declare(strict_types=1);

namespace Salix\Cms\Install;

/**
 * Thrown for any recoverable installation failure. The message is safe to show
 * back to the operator running the installer.
 */
final class InstallException extends \RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
