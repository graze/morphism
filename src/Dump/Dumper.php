<?php

namespace Graze\Morphism\Dump;

use Exception;
use Graze\Morphism\Dump\Output\OutputInterface;
use Graze\Morphism\Parse\CollationInfo;
use Graze\Morphism\Parse\StreamParser;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Specification\TableSpecification;
use RuntimeException;

class Dumper implements DumperInterface
{
    private $specification;

    public function __construct(TableSpecification $specification)
    {
        $this->specification = $specification;
    }

    /**
     * @param TokenStream $stream
     * @param OutputInterface $output
     *
     * @return void
     * @throws Exception
     */
    public function dump(TokenStream $stream, OutputInterface $output)
    {
        $streamParser = new StreamParser(new CollationInfo(), '', 'InnoDB');

        try {
            $dump = $streamParser->parse($stream, $this->specification);
            $output->output($dump);
        } catch(RuntimeException $e) {
            throw new RuntimeException($stream->contextualise($e->getMessage()));
        } catch(Exception $e) {
            throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }
    }
}
