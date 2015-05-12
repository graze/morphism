<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Configuration\ConfigurationParser;
use Graze\Morphism\Connection\ConnectionResolver;
use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Diff\ConfirmableDiffApplier;
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
        $outputHelper = new OutputHelper($output);
        $applyChanges = $input->getOption('apply-changes');

        // setup the config
        $parser = new ConfigurationParser();
        $config = $parser->parse($input->getArgument('config-file'));

        // figure out which connection names we're using
        $connectionNames = $config->getConnectionNames();
        if ($input->getArgument('connection')) {
            $connectionNames = [$input->getArgument('connection')];
        }

        // build the differ
        $differConfig = DifferConfiguration::buildFromInput($input);
        $differ = new Differ($differConfig, $config);

        // diff for each connection
        $connectionResolver = new ConnectionResolver($config);
        foreach ($connectionNames as $connectionName) {
            $outputHelper->title('Connection: ' . $connectionName);

            $connection = $connectionResolver->resolveFromName($connectionName);
            $diff = $differ->diff($connection);

            foreach ($diff->getQueries() as $query) {
                $outputHelper->sql($query);
            }

            // apply the diff to the connection if there is one
            if ($diff && $applyChanges !== 'no') {
                if ($applyChanges === 'confirm') {
                    $output->writeln('');
                    $output->writeln('<comment>-- Confirm changes to ' . $connection->getDatabase() . ':</comment>');

                    $applier = new ConfirmableDiffApplier($input, $output, $this->getHelper('question'));
                    $applier->apply($diff, $connection);
                } else {
                    $applier = new DiffApplier();
                    $applier->apply($diff, $connection);
                }
            }
        }
    }
}