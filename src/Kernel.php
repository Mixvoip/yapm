<?php

namespace App;

use App\Domain\AppConstants;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @inheritDoc
     */
    public function boot(): void
    {
        parent::boot();

        $container = $this->getContainer();
        AppConstants::$apiBaseUri = $container->getParameter('api_base_uri');
        AppConstants::$frontendBaseUri = $container->getParameter('frontend_base_uri');
        AppConstants::$mailerFromAddress = $container->getParameter('mailer_from_address');
    }
}
