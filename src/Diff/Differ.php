<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Parse\CollationInfo;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\PathParser;
use Graze\Morphism\Parse\StreamParser;
use Graze\Morphism\Parse\TokenStreamFactory;
use Graze\Morphism\Specification\TableSpecification;

class Differ
{
    /**
     * @var DifferConfiguration
     */
    private $differConfig;

    /**
     * @var TokenStreamFactory
     */
    private $streamFactory;

    /**
     * @var PathParser
     */
    private $pathParser;

    /**
     * @param DifferConfiguration $differConfig
     * @param TokenStreamFactory $streamFactory
     * @param PathParser $pathParser
     */
    public function __construct(
        DifferConfiguration $differConfig,
        TokenStreamFactory $streamFactory,
        PathParser $pathParser
    ) {
        $this->differConfig = $differConfig;
        $this->streamFactory = $streamFactory;
        $this->pathParser = $pathParser;
    }

    /**
     * @param Connection $connection
     * @param TableSpecification $tableSpecification
     *
     * @return Diff
     */
    public function diff(Connection $connection, TableSpecification $tableSpecification = null)
    {
        $currentSchema = $this->getCurrentSchema($connection);
        $targetSchema = $this->getTargetSchema($connection);

        $diff = $currentSchema->diff(
            $targetSchema,
            [
                'createDatabase' => false,
                'dropDatabase'   => false,
                'createTable'    => $this->differConfig->isCreateTable(),
                'dropTable'      => $this->differConfig->isDropTable(),
                'alterEngine'    => $this->differConfig->isAlterEngine(),
            ],
            $tableSpecification
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

        $parser = new StreamParser(new CollationInfo(), $connection->getDatabase(), 'InnoDB');
        return $parser->parse($stream);
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     */
    private function getTargetSchema(Connection $connection)
    {
        $path = $this->differConfig->getSchemaPath() . '/' . $connection->getDatabase();

        return $this->pathParser->parse(
            [$path],
            $this->differConfig->getEngine(),
            $this->differConfig->getCollation(),
            $connection->getDatabase()
        );
    }
}
