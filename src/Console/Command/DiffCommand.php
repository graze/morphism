<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Config;
use Graze\Morphism\Connection\ConnectionResolver;
use Graze\Morphism\Diff\DiffApplier;
use Graze\Morphism\Diff\Differ;
use Graze\Morphism\Diff\DifferConfiguration;
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
            ->setDescription('Extracts schema definitions from the named connections, and outputs the necessary statements to transform them into what is defined under the schema path.')
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
        $config = new Config($input->getArgument('config-file'));
        $config->parse();

        $differConfig = DifferConfiguration::buildFromInput($input);
        $differ = new Differ($differConfig, $config);
        $connectionResolver = new ConnectionResolver($config);

        $connectionNames = $config->getConnectionNames();
        if ($input->getArgument('connection')) {
            $connectionNames = [$input->getArgument('connection')];
        }

//        Token::setQuoteNames($diffConfig->isQuoteNames());

//        $logDir = $this->config->getLogDir();
//
//        if ($logDir !== null
//            && ! is_dir($logDir)
//            && ! @mkdir($logDir, 0777, true)) {
//            fprintf(STDERR, "Could not create log directory: {$logDir}\n");
//            exit(1);
//        }

        foreach ($connectionNames as $connectionName) {
            $output->writeln('---------------------------------');
            $output->writeLn('    <comment>Connection: ' . $connectionName . '</comment>');
            $output->writeln('---------------------------------');
            $output->writeln('');

            $connection = $connectionResolver->resolveFromName($connectionName);
            $diff = $differ->diff($connection);

            foreach ($diff->getQueries() as $query) {
                $output->writeln('<info>' . $query . '</info>');
                $output->writeln('');
            }

            if ($diff) {
                $applier = new DiffApplier($input, $output, $this->getHelper('question'));
                $applier->apply($diff, $connection, $input->getOption('apply-changes'));
            }
        }
    }
}
