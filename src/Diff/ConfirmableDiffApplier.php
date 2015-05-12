<?php

namespace Graze\Morphism\Diff;

use Graze\Morphism\Console\Output\OutputHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ConfirmableDiffApplier extends DiffApplier
{
    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var OutputHelper
     */
    private $outputHelper;

    /**
     * @var QuestionHelper
     */
    private $question;

    /**
     * @var bool
     */
    private $isQuitting = false;

    /**
     * @var bool
     */
    private $applyAll = false;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param QuestionHelper $question
     */
    public function __construct(InputInterface $input, OutputInterface $output, QuestionHelper $question)
    {
        $this->input = $input;
        $this->output = $output;
        $this->outputHelper = new OutputHelper($output);
        $this->question = $question;
    }

    /**
     * @param string $query
     *
     * @return bool
     */
    protected function shouldApply($query)
    {
        if ($this->isQuitting) {
            return false;
        } elseif ($this->applyAll) {
            return true;
        } else {

            $this->outputHelper->sql($query);

            $choice = new ChoiceQuestion(
                '-- Apply this change?',
                ['y' => 'yes', 'n' => 'no', 'a' => 'all', 'q' => 'quit']
            );
            $choice->setErrorMessage('Unrecognised option');

            $response = $this->question->ask($this->input, $this->output, $choice);

            if ($response === 'quit') {
                $this->isQuitting = true;
            } elseif ($response === 'all') {
                $this->applyAll = true;
            }

            return $response === 'yes' || $response === 'all';
        }
    }
}
