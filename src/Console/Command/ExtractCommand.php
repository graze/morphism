<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Dump\Dumper;
use Graze\Morphism\Dump\Output\FileOutput;
use Graze\Morphism\Dump\Output\StdOutOutput;
use Graze\Morphism\ExtractorFactory;
use Graze\Morphism\Parse\TokenStreamFactory;
use Graze\Morphism\Specification\TableSpecification;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExtractCommand extends Command
{
    protected function configure()
    {
        $this->setName('extract')
            ->addArgument('mysql-dump-file', InputArgument::REQUIRED, 'The mysql dump file to extract from')
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
        $extractorFactory = new ExtractorFactory();
        $streamFactory = new TokenStreamFactory($extractorFactory, new Filesystem());
        $stream = $streamFactory->buildFromFile($input->getArgument('mysql-dump-file'));

        if ($input->getOption('write')) {
            $dumpOutput = new FileOutput(new Filesystem(), $input->getOption('schema-path'));
        } else {
            $dumpOutput = new StdOutOutput(new OutputHelper($output));
        }

        $dumper = new Dumper(new TableSpecification());
        $dumper->dump($stream, $dumpOutput);
    }
}
