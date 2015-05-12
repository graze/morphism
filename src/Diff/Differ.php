<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;

class Differ
{
    /**
     * @var DifferConfiguration
     */
    private $config;

    /**
     * @param DifferConfiguration $config
     */
    public function __construct(DifferConfiguration $config)
    {
        $this->config = $config;
    }

    /**
     * @param Connection $connection
     * @param array $matchTables
     *
     * @return Diff
     */
    public function diff(Connection $connection, $matchTables)
    {
        $currentSchema = $this->getCurrentSchema($connection);
        $targetSchema = $this->getTargetSchema($connection);

        $diff = $currentSchema->diff(
            $targetSchema,
            [
                'createDatabase' => false,
                'dropDatabase'   => false,
                'createTable'    => $this->config->isCreateTable(),
                'dropTable'      => $this->config->isDropTable(),
                'alterEngine'    => $this->config->isAlterEngine(),
                'matchTables'    => $matchTables
            ]
        );

        return new Diff($diff);
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     */
    private function getCurrentSchema(Connection $connection)
    {
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$connection->getDatabase()]);
        $extractor->setCreateDatabases(false);
        $extractor->setQuoteNames($this->config->isQuoteNames());

        $text = '';
        foreach($extractor->extract() as $query) {
            $text .= "$query;\n";
        }
        $stream = TokenStream::newFromText($text, '');

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
        $path = $this->config->getSchemaPath() . '/' . $connection->getDatabase();

        return MysqlDump::parseFromPaths(
            [$path],
            $this->config->getEngine(),
            $this->config->getCollation(),
            $connection->getDatabase()
        );
    }
}
