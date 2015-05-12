<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Config;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;

class Differ
{
    /**
     * @var DifferConfiguration
     */
    private $differConfig;

    /**
     * @var Config
     */
    private $config;

    /**
     * @param DifferConfiguration $differConfig
     * @param Config|DifferConfiguration $config
     */
    public function __construct(DifferConfiguration $differConfig, Config $config)
    {
        $this->differConfig = $differConfig;
        $this->config = $config;
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
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$connection->getDatabase()]);
        $extractor->setCreateDatabases(false);
        $extractor->setQuoteNames($this->differConfig->isQuoteNames());

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
        $path = $this->differConfig->getSchemaPath() . '/' . $connection->getDatabase();

        return MysqlDump::parseFromPaths(
            [$path],
            $this->differConfig->getEngine(),
            $this->differConfig->getCollation(),
            $connection->getDatabase()
        );
    }
}
