<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class DiffApplier
{
    private $input;
    private $output;

    /**
     * @var QuestionHelper
     */
    private $question;

    public function __construct(InputInterface $input, OutputInterface $output, QuestionHelper $question)
    {
        $this->input = $input;
        $this->output = $output;
        $this->question = $question;
    }

    /**
     * @param array $diff
     * @param Connection $connection
     * @param string $applyChanges
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function apply($diff, Connection $connection, $applyChanges)
    {
        if (count($diff) === 0) {
            return;
        }

        if ($applyChanges === 'no') {
            return;
        }

        $confirm = $applyChanges === 'confirm';
        $logHandle = null;
//        $logDir = $diffConfig->getLogDir();

//        if ($logDir !== null) {
//            $logFile = "{$logDir}/{$connection->getDatabase()}.sql";
//            $logHandle = fopen($logFile, 'w');
//            if ($logHandle === false) {
//                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
//                exit(1);
//            }
//        }

        if (count($diff) > 0 && $confirm) {
            $this->output->writeln('');
            $this->output->writeln('<comment>-- Confirm changes to ' . $connection->getDatabase() . ':</comment>');
        }

        $defaultResponse = 'yes';

        foreach($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                $this->output->writeln('');
                $this->output->writeln('<info>' . $query . '</info>');
                $this->output->writeln('');

                $question = new ChoiceQuestion(
                    '-- Apply this change?',
                    ['y' => 'yes', 'n' => 'no', 'a' => 'all', 'q' => 'quit']
                );
                $question->setErrorMessage('Unrecognised option');

                $response = $this->question->ask($this->input, $this->output, $question);
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
            }
//            elseif ($logHandle && $diffConfig->isLogSkipped()) {
//                fwrite($logHandle,
//                    "-- [SKIPPED]\n" .
//                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
//                    "\n"
//                );
//            }
        }
    }
}
