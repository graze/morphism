<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Configuration\ConfigurationParser;
use Graze\Morphism\Connection\ConnectionResolver;
use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Dump\FileDumper;
use Graze\Morphism\Dump\StdOutDumper;
use Graze\Morphism\ExtractorFactory;
use Graze\Morphism\Parse\TokenStreamFactory;
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

            if ($input->getOption('write')) {
                $dumper = new FileDumper($config, $streamFactory, $input->getOption('schema-path'), new Filesystem());
                $dumper->dump($connection);
            } else {
                $dumper = new StdOutDumper($config, $streamFactory, $output);
                $dumper->dump($connection);
            }
        }
    }
}
