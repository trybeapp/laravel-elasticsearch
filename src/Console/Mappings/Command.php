<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Support\MappingService;
use Illuminate\Console\Command as BaseCommand;

abstract class Command extends BaseCommand
{
    public function __construct(protected MappingService $service)
    {
        parent::__construct();
    }
}
