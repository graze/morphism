<?php

namespace Graze\Morphism\Dump\Output;

use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Parse\MysqlDump;

class StdOutOutput implements OutputInterface
{
    /**
     * @var OutputHelper
     */
    private $outputHelper;

    /**
     * @param OutputHelper $outputHelper
     */
    public function __construct(OutputHelper $outputHelper)
    {
        $this->outputHelper = $outputHelper;
    }

    /**
     * @param MysqlDump $dump
     *
     * @return void
     */
    public function output(MysqlDump $dump)
    {
        foreach ($dump->getDDL() as $query) {
            $this->outputHelper->sql($query);
        }
    }
}
