<?php

namespace Graze\Morphism\Console\Output;

use Symfony\Component\Console\Output\OutputInterface;

class OutputHelper
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * @param string $text
     */
    public function title($text)
    {
        $this->writeln('---------------------------------');
        $this->writeLn('    <comment>' . $text . '</comment>');
        $this->writeln('---------------------------------');
        $this->writeln('');
    }

    /**
     * @param string $sql
     */
    public function sql($sql)
    {
        $this->writeln('<info>' . $sql . '</info>');
        $this->writeln('');
    }

    /**
     * @param string $text
     */
    public function writeln($text)
    {
        $this->output->writeln($text);
    }

    /**
     * @param string $text
     */
    public function writelnVerbose($text)
    {
        if ($this->output->getVerbosity() > OutputInterface::VERBOSITY_VERBOSE) {
            $this->writeln($text);
        }
    }
}
