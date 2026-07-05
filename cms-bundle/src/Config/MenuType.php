<?php

declare(strict_types=1);

namespace Salix\Cms\Config;

enum MenuType: string
{
    case MAIN = 'main';
    case FOOTER = 'footer';
}
