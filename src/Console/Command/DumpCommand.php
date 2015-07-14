<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Configuration\ConfigurationParser;
use Graze\Morphism\Connection\ConnectionResolver;
use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Dump\Output\FileOutput;
use Graze\Morphism\Dump\Output\StdOutOutput;
use Graze\Morphism\Extractor\ExtractorFactory;
use Graze\Morphism\Parse\CollationInfo;
use Graze\Morphism\Parse\StreamParser;
use Graze\Morphism\Parse\TokenStreamFactory;
use Graze\Morphism\Specification\TableSpecification;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends Command
{
    protected function configure()
    {
        $this->setName('dump')
            ->addArgument('config-file', InputArgument::REQUIRED, 'The config file.')
            ->addArgument('connection', InputArgument::OPTIONAL, 'The name of the connection to dump.')
            ->addOption('schema-path', null, InputOption::VALUE_REQUIRED, 'The schema path to dump schemas to', './schema')
            ->addOption('write', null, InputOption::VALUE_NONE, 'Write the files to the schema-path.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $outputHelper = new OutputHelper($output);

        $parser = new ConfigurationParser();
        $config = $parser->parse($input->getArgument('config-file'));

        // figure out which connection names we're using
        $connectionNames = $config->getConnectionNames();
        if ($input->getArgument('connection')) {
            $connectionNames = [$input->getArgument('connection')];
        }

        $connectionResolver = new ConnectionResolver($config);
        $streamFactory = new TokenStreamFactory(new ExtractorFactory(), new Filesystem());
        foreach ($connectionNames as $connectionName) {
            $outputHelper->title('Connection: ' . $connectionName);

            $connection = $connectionResolver->resolveFromName($connectionName);
            $stream = $streamFactory->buildFromConnection($connection);

            $dumpOutput = null;
            if ($input->getOption('write')) {
                $dumpOutput = new FileOutput(new Filesystem(), $input->getOption('schema-path'));
            } else {
                $dumpOutput = new StdOutOutput(new OutputHelper($output));
            }

            $entry = $config->getEntry($connectionName);
            $streamParser = new StreamParser(new CollationInfo(), '', 'InnoDB');
            $dump = $streamParser->parse($stream, new TableSpecification($entry['morphism']['include'], $entry['morphism']['exclude']));

            $dumpOutput->output($dump);
        }
    }
}
