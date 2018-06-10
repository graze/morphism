<?php

namespace Graze\Morphism\Command;

use GlobIterator;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Lint extends Command
{
    const COMMAND_NAME      = 'lint';

    // Command line arguments
    const ARGUMENT_PATH     = 'path';

    // Command line options
    const OPTION_VERBOSE    = 'verbose';
    const OPTION_NO_VERBOSE = 'no-verbose';

    /** @var bool */
    private $verbose = false;
    /** @var array  */
    private $paths = [];

    protected function configure()
    {
        $this->setName(self::COMMAND_NAME);

        $helpText = sprintf(
            "Usage: %s [OPTIONS] [PATH ...]\n" .
            "Checks all schema files below the specified paths for correctness. If no PATH\n" .
            "is given, checks standard input. By default output is only produced if errors\n" .
            "are detected.\n" .
            "\n" .
            "OPTIONS\n" .
            "  -h, -help, --help   display this message, and exit\n" .
            "  --[no-]verbose      include valid files in output; default: no\n" .
            "\n" .
            "EXIT STATUS\n" .
            "The exit status will be 1 if any errors were detected, or 0 otherwise.\n" .
            "",
            self::COMMAND_NAME
        );
        $this->setHelp($helpText);

        $this->setDescription("Check database schema files for correctness");

        $this->addArgument(
            self::ARGUMENT_PATH,
            InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
            null,
            ['php://stdin']
        );

        $this->addOption(self::OPTION_NO_VERBOSE);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->paths = $input->getArgument(self::ARGUMENT_PATH);

        if ($input->getOption(self::OPTION_VERBOSE)) {
            $this->verbose = true;
        }

        $engine = null;
        $collation = null;

        $errorFiles = [];

        foreach ($this->paths as $path) {
            $dump = new MysqlDump;

            $files = [];
            if (is_dir($path)) {
                if ($this->verbose) {
                    echo "$path\n";
                }
                foreach (new GlobIterator("$path/*.sql") as $fileInfo) {
                    $files[] = $fileInfo->getPathname();
                }
            } else {
                $files[] = $path;
            }

            foreach ($files as $file) {
                $stream = TokenStream::newFromFile($file);
                try {
                    $dump->parse($stream);
                    if ($this->verbose) {
                        echo "OK    $file\n";
                    }
                } catch (RuntimeException $e) {
                    $errorFiles[] = $file;
                    $message = $stream->contextualise($e->getMessage());
                    echo "ERROR $message\n";
                }
            }
        }

        return count($errorFiles);
    }
}
