<?php

namespace Graze\Morphism\Console\Command;

use Graze\Morphism\Lint\Linter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LintCommand extends Command
{
    protected function configure()
    {
        $this->setName('lint')
            ->addArgument('path', InputArgument::REQUIRED, 'The path containing the files to check');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new OutputFormatterStyle('white', 'green');
        $output->getFormatter()->setStyle('success', $style);

        $path = $input->getArgument('path');
        $linter = new Linter($output);
        return $linter->lint($path);
    }
}
