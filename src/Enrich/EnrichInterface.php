<?php
namespace GlpiPlugin\Idmefv2\Enrich;

use stdClass;

interface EnrichInterface
{
    public function enrich(stdClass $data): stdClass;
}