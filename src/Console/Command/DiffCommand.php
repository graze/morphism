<?php

namespace Graze\Morphism\Console\Command;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Config;
use Graze\Morphism\Diff\Differ;
use Graze\Morphism\Diff\DifferConfiguration;
use Graze\Morphism\Parse\Token;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

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
        $diffConfig = DifferConfiguration::buildFromInput($input);
        $differ = new Differ($diffConfig);

        $config = new Config($diffConfig->getConfigFile());
        $config->parse();

        $connectionNames =
            (count($diffConfig->getConnectionNames()) > 0)
                ? $diffConfig->getConnectionNames()
                : $config->getConnectionNames();

        Token::setQuoteNames($diffConfig->isQuoteNames());

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
            $connection = $config->getConnection($connectionName);
            $entry = $config->getEntry($connectionName);
            $matchTables = [
                $connection->getDatabase() => $entry['morphism']['matchTables']
            ];

            $diff = $differ->diff($connection, $matchTables);

            foreach ($diff as $query) {
                $output->writeln('<info>' . $query . '</info>');
                $output->writeln('');
            }

            $this->applyChanges($connection, $diffConfig, $output, $input, $diff);
        }
    }

    protected function applyChanges(Connection $connection, DifferConfiguration $diffConfig, OutputInterface $output, InputInterface $input,  $diff)
    {
        if (count($diff) === 0) {
            return;
        }

        if ($diffConfig->getApplyChanges() === 'no') {
            return;
        }

        $confirm = $diffConfig->getApplyChanges() === 'confirm';
        $logHandle = null;
        $logDir = $diffConfig->getLogDir();

//        if ($logDir !== null) {
//            $logFile = "{$logDir}/{$connection->getDatabase()}.sql";
//            $logHandle = fopen($logFile, 'w');
//            if ($logHandle === false) {
//                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
//                exit(1);
//            }
//        }

        if (count($diff) > 0 && $confirm) {
            $output->writeln('');
            $output->writeln('<comment>-- Confirm changes to ' . $connection->getDatabase() . ':</comment>');
        }

        $defaultResponse = 'yes';

        foreach($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                $output->writeln('');
                $output->writeln('<info>' . $query . '</info>');
                $output->writeln('');

                $helper = $this->getHelper('question');
                $question = new ChoiceQuestion(
                    '-- Apply this change?',
                    ['y' => 'yes', 'n' => 'no', 'a' => 'all', 'q' => 'quit']
                );
                $question->setErrorMessage('Unrecognised option');

                $response = $helper->ask($input, $output, $question);
            }

            switch($response) {
                case 'yes':
                    $apply = true;
                    break;

                case 'no':
                    $apply = false;
                    break;

                case 'all':
                    $apply = true;
                    $confirm = false;
                    $defaultResponse = 'yes';
                    break;

                case 'quit':
                    $apply = false;
                    $confirm = false;
                    $defaultResponse = 'no';
                    break;
            }

            if ($apply) {
//                if ($logHandle) {
//                    fwrite($logHandle, "$query;\n\n");
//                }
                $connection->executeQuery($query);
            } elseif ($logHandle && $diffConfig->isLogSkipped()) {
//                fwrite($logHandle,
//                    "-- [SKIPPED]\n" .
//                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
//                    "\n"
//                );
            }
        }
    }
}
