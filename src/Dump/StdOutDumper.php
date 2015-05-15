<?php

namespace Graze\Morphism\Dump;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Configuration\Configuration;
use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Parse\TokenStreamFactory;
use Symfony\Component\Console\Output\OutputInterface;

class StdOutDumper extends Dumper
{
    /**
     * @var OutputHelper
     */
    protected $outputHelper;

    /**
     * @param Configuration $config
     * @param TokenStreamFactory $streamFactory
     * @param OutputInterface $output
     */
    public function __construct(Configuration $config, TokenStreamFactory $streamFactory, OutputInterface $output)
    {
        parent::__construct($config, $streamFactory);
        $this->outputHelper = new OutputHelper($output);
    }

    /**
     * {@inheritDoc}
     */
    public function dump(Connection $connection)
    {
        $dump = parent::dump($connection);

        foreach ($dump->getDDL() as $query) {
            $this->outputHelper->sql($query);
        }
    }
}
