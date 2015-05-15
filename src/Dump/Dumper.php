<?php

namespace Graze\Morphism\Dump;

use Doctrine\DBAL\Connection;
use Exception;
use Graze\Morphism\Configuration\Configuration;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStreamFactory;
use RuntimeException;

abstract class Dumper implements DumperInterface
{
    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @var TokenStreamFactory
     */
    private $streamFactory;

    /**
     * @param Configuration $config
     * @param TokenStreamFactory $streamFactory
     */
    public function __construct(Configuration $config, TokenStreamFactory $streamFactory)
    {
        $this->config = $config;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     * @throws Exception
     */
    public function dump(Connection $connection)
    {
        $stream = $this->streamFactory->buildFromConnection($connection);

        $entry = $this->config->getEntry($connection->getDatabase());
        $matchTables = $entry['morphism']['matchTables'];

        $dump = new MysqlDump();
        try {
            $dump->parse($stream, ['matchTables' => $matchTables]);
        } catch(RuntimeException $e) {
            throw new RuntimeException($stream->contextualise($e->getMessage()));
        } catch(Exception $e) {
            throw new Exception($e->getMessage() . "\n\n" . $e->getTraceAsString());
        }

        return $dump;
    }
}
