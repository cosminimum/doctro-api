<?php

namespace App\Application\Factory;

use App\Application\DataSource\ServiceSourceInterface;
use App\Infrastructure\DataSource\FhirServiceSource;
use App\Infrastructure\DataSource\LocalServiceSource;
use Psr\Container\ContainerInterface;

class ServiceSourceFactory
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly bool $useExternalApi
    ) {
    }

    public function create(): ServiceSourceInterface
    {
        if ($this->useExternalApi) {
            return $this->container->get(FhirServiceSource::class);
        }

        return $this->container->get(LocalServiceSource::class);
    }
}