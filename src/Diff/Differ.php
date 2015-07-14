<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Parse\CollationInfo;
use Graze\Morphism\Parse\CreateDatabase;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\PathParser;
use Graze\Morphism\Parse\StreamParser;
use Graze\Morphism\Parse\Token;
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
    public function diffFromConnection(Connection $connection, TableSpecification $tableSpecification = null)
    {
        $currentSchema = $this->getCurrentSchema($connection);
        $targetSchema = $this->getTargetSchema($connection);

        return $this->diff($currentSchema, $targetSchema, $connection->getDatabase(), $tableSpecification);
    }

    /**
     * @param MysqlDump $a
     * @param MysqlDump $b
     * @param $defaultDatabaseName
     * @param TableSpecification $tableSpecification
     *
     * @return Diff
     */
    public function diff(MysqlDump $a, MysqlDump $b, $defaultDatabaseName, TableSpecification $tableSpecification = null)
    {
        $dropDatabase = false;
        $createDatabase = false;

        $thisDatabaseNames = array_keys($a->databases);
        $thatDatabaseNames = array_keys($b->databases);

        $commonDatabaseNames  = array_intersect($thisDatabaseNames, $thatDatabaseNames);
        $droppedDatabaseNames = array_diff($thisDatabaseNames, $thatDatabaseNames);
        $createdDatabaseNames = array_diff($thatDatabaseNames, $thisDatabaseNames);

        $diff = [];

        if ($dropDatabase && count($droppedDatabaseNames) > 0) {
            foreach ($droppedDatabaseNames as $databaseName) {
                $diff[] = 'DROP DATABASE IF EXISTS ' . Token::escapeIdentifier($databaseName);
            }
        }

        if ($createDatabase) {
            foreach ($createdDatabaseNames as $databaseName) {
                /** @var CreateDatabase $thatDatabase */
                $thatDatabase = $b->databases[$databaseName];
                $diff[] = $thatDatabase->getDDL();
                $diff[] = 'USE ' . Token::escapeIdentifier($databaseName);
                foreach ($thatDatabase->tables as $table) {
                    if (is_null($tableSpecification)
                        || ($tableSpecification && $tableSpecification->isSatisfiedBy($table))) {
                        $diff[] = $table->getDDL();
                    }
                }
            }
        }

        foreach ($commonDatabaseNames as $databaseName) {
            $thisDatabase = $a->databases[$databaseName];
            $thatDatabase = $b->databases[$databaseName];
            $databaseDiff = $thisDatabase->diff($thatDatabase, [
                'createTable' => $this->differConfig->isCreateTable(),
                'dropTable'   => $this->differConfig->isDropTable(),
                'alterEngine' => $this->differConfig->isAlterEngine(),
            ], $tableSpecification);

            if ($databaseDiff !== '') {
                if ($databaseName !== $defaultDatabaseName) {
                    $diff[] = 'USE ' . Token::escapeIdentifier($databaseName);
                }
                $diff = array_merge($diff, $databaseDiff);
            }
        }

        return new Diff($diff);
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
