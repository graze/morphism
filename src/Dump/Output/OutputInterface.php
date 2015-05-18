<?php

namespace Graze\Morphism\Dump\Output;

use Graze\Morphism\Parse\MysqlDump;

interface OutputInterface
{
    /**
     * @param MysqlDump $dump
     *
     * @return void
     */
    public function output(MysqlDump $dump);
}
