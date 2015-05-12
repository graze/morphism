<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Parse\TokenStream;
use Graze\Morphism\Parse\Token;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Extractor;
use Graze\Morphism\Config;

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

    /**
     * @param Connection $connection
     * @param array $diff
     *
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Exception
     */
    private function applyChanges(Connection $connection, $diff)
    {
        if (count($diff) === 0) {
            return;
        }

        if ($this->config->getApplyChanges() === 'no') {
            return;
        }

        $confirm = $this->config->getApplyChanges() === 'confirm';
        $defaultResponse = 'y';
        $logHandle = null;
        $logDir = $this->config->getLogDir();

        if ($logDir !== null) {
            $logFile = "{$logDir}/{$connection->getDatabase()}.sql";
            $logHandle = fopen($logFile, 'w');
            if ($logHandle === false) {
                fprintf(STDERR, "Could not open log file for writing: $logFile\n");
                exit(1);
            }
        }

        if (count($diff) > 0 && $confirm) {
            echo "\n";
            echo "-- Confirm changes to {$connection->getDatabase()}:\n";
        }

        foreach($diff as $query) {
            $response = $defaultResponse;
            $apply = false;

            if ($confirm) {
                echo "\n";
                echo "$query;\n\n";
                do {
                    echo '-- Apply this change? [y]es [n]o [a]ll [q]uit: ';
                    $response = fgets(STDIN);
                    if ($response === false) {
                        throw new \Exception('could not read response');
                    }
                    $response = rtrim($response);
                }
                while(!in_array($response, ['y', 'n', 'a', 'q']));
            }

            switch($response) {
                case 'y':
                    $apply = true;
                    break;

                case 'n':
                    $apply = false;
                    break;

                case 'a':
                    $apply = true;
                    $confirm = false;
                    $defaultResponse = 'y';
                    break;

                case 'q':
                    $apply = false;
                    $confirm = false;
                    $defaultResponse = 'n';
                    break;
            }

            if ($apply) {
                if ($logHandle) {
                    fwrite($logHandle, "$query;\n\n");
                }
                $connection->executeQuery($query);
            } elseif ($logHandle && $this->config->isLogSkipped()) {
                fwrite($logHandle,
                    "-- [SKIPPED]\n" .
                    preg_replace('/^/xms', '-- ', $query) .  ";\n" .
                    "\n"
                );
            }
        }
    }

    public function run()
    {
        $config = new Config($this->config->getConfigFile());
        $config->parse();

        $connectionNames =
            (count($this->config->getConnectionNames()) > 0)
                ? $this->config->getConnectionNames()
                : $config->getConnectionNames();

        Token::setQuoteNames($this->config->isQuoteNames());

        $logDir = $this->config->getLogDir();

        if ($logDir !== null
            && ! is_dir($logDir)
            && ! @mkdir($logDir, 0777, true)) {
            fprintf(STDERR, "Could not create log directory: {$logDir}\n");
            exit(1);
        }

        foreach ($connectionNames as $connectionName) {
            echo "-- --------------------------------\n";
            echo "--   Connection: $connectionName\n";
            echo "-- --------------------------------\n";
            $connection = $config->getConnection($connectionName);
            $entry = $config->getEntry($connectionName);
            $dbName = $entry['connection']['dbname'];
            $matchTables = [
                $dbName => $entry['morphism']['matchTables']
            ];

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

            foreach($diff as $query) {
                echo "$query;\n\n";
            }

            $this->applyChanges($connection, $diff);
        }
    }
}
