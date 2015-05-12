<?php

namespace Graze\Morphism\Lint;

use Graze\Morphism\Console\Output\OutputHelper;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;
use Symfony\Component\Console\Output\OutputInterface;

class Linter
{
    /**
     * @var OutputHelper
     */
    private $outputHelper;

    /**
     * @param OutputInterface $output
     */
    public function __construct(OutputInterface $output)
    {
        $this->outputHelper = new OutputHelper($output);
    }

    /**
     * @param string $path
     *
     * @return int
     */
    public function lint($path)
    {
        $dump = new MysqlDump();

        $files = [];
        if (is_dir($path)) {
            $this->outputHelper->writelnVerbose($path);
            foreach (new \GlobIterator("$path/*.sql") as $fileInfo) {
                $files[] = $fileInfo->getPathname();
            }
        } else {
            $files[] = $path;
        }

        $errorFiles = [];
        foreach ($files as $file) {
            $stream = TokenStream::newFromFile($file);
            try {
                $dump->parse($stream);
                $this->outputHelper->writelnVerbose('<success> OK </success> ' . $file);
            } catch(\RuntimeException $e) {
                $errorFiles[] = $file;
                $message = $stream->contextualise($e->getMessage());
                $this->outputHelper->writeln('<error> ERROR </error> ' . $message);
            }
        }

        return count($errorFiles);
    }
}
