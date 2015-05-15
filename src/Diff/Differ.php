<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Configuration\Configuration;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Parse\TokenStreamFactory;

class Differ
{
    /**
     * @var DifferConfiguration
     */
    private $differConfig;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var TokenStreamFactory
     */
    private $streamFactory;

    /**
     * @param DifferConfiguration $differConfig
     * @param Configuration $config
     * @param TokenStreamFactory $streamFactory
     */
    public function __construct(DifferConfiguration $differConfig, Configuration $config, TokenStreamFactory $streamFactory)
    {
        $this->differConfig = $differConfig;
        $this->config = $config;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param Connection $connection
     *
     * @return Diff
     */
    public function diff(Connection $connection)
    {
        $currentSchema = $this->getCurrentSchema($connection);
        $targetSchema = $this->getTargetSchema($connection);

        $entry = $this->config->getEntry($connection->getDatabase());
        $matchTables = [
            $connection->getDatabase() => $entry['morphism']['matchTables']
        ];

        $diff = $currentSchema->diff(
            $targetSchema,
            [
                'createDatabase' => false,
                'dropDatabase'   => false,
                'createTable'    => $this->differConfig->isCreateTable(),
                'dropTable'      => $this->differConfig->isDropTable(),
                'alterEngine'    => $this->differConfig->isAlterEngine(),
                'matchTables'    => $matchTables
            ]
        );

        if (count($diff) > 0) {
            return new Diff($diff);
        }
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     */
    private function getCurrentSchema(Connection $connection)
    {
        $stream = $this->streamFactory->buildFromConnection($connection);

        $dump = new MysqlDump();
        $dump->setDefaultDatabase($connection->getDatabase());
        $dump->parse($stream);

        return $dump;
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     */
    private function getTargetSchema(Connection $connection)
    {
        $path = $this->differConfig->getSchemaPath() . '/' . $connection->getDatabase();

        return MysqlDump::parseFromPaths(
            [$path],
            $this->differConfig->getEngine(),
            $this->differConfig->getCollation(),
            $connection->getDatabase()
        );
    }
}
