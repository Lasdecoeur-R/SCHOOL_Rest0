<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * Point d’entrée de l’application Symfony : charge la config, les bundles et les routes (MicroKernelTrait).
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;
}
