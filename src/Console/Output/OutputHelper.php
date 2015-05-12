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
        $this->output->writeln('---------------------------------');
        $this->output->writeLn('    <comment>' . $text . '</comment>');
        $this->output->writeln('---------------------------------');
        $this->output->writeln('');
    }

    /**
     * @param string $sql
     */
    public function sql($sql)
    {
        $this->output->writeln('<info>' . $sql . '</info>');
        $this->output->writeln('');
    }
}
