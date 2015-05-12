<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Diff\DiffConfiguration;
use Graze\Morphism\Diff\Differ;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends Command
{
    protected function configure()
    {
        $this->setName('diff')
            ->setDescription('Greet someone')
            ->addArgument(
                'config-file',
                InputArgument::REQUIRED,
                'A YAML file mapping connection names to parameters. See README for details.'
            )
            ->addArgument(
                'connection',
                InputArgument::OPTIONAL,
                'The connection name to perform the diff on (optional, defaults to all)'
            )
            ->addOption(
                'engine',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the default database engine',
                'InnoDB'
            )
            ->addOption(
                'collation',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set the default collation'
            )
            ->addOption(
                'no-quote-names',
                null,
                InputOption::VALUE_NONE,
                'Do not quote names with `...`'
            )
            ->addOption(
                'no-create-table',
                null,
                InputOption::VALUE_NONE,
                'Do not output CREATE TABLE statements'
            )
            ->addOption(
                'no-drop-table',
                null,
                InputOption::VALUE_NONE,
                'Do not output DROP TABLE statements'
            )
            ->addOption(
                'no-alter-engine',
                null,
                InputOption::VALUE_NONE,
                'Do not output ALTER TABLE ... ENGINE=...'
            )
            ->addOption(
                'schema-path',
                null,
                InputOption::VALUE_REQUIRED,
                'Location of schemas',
                './schema'
            )
            ->addOption(
                'apply-changes',
                null,
                InputOption::VALUE_REQUIRED,
                'Apply changes? (yes/no/confirm)',
                'no'
            )
            ->addOption(
                'log-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Log applied changes for each connection to a file in the given directory'
            )
            ->addOption(
                'no-log-skipped',
                null,
                InputOption::VALUE_NONE,
                'Do not log skipped queries (commented out)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = DiffConfiguration::buildFromInput($input);
        $differ = new Differ($config);
        $differ->run();
    }
}
