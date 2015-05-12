<?php

namespace Graze\Morphism\Dump;

use Doctrine\DBAL\Connection;
use Exception;
use Graze\Morphism\Configuration\Configuration;
use Graze\Morphism\Extractor;
use Graze\Morphism\Parse\MysqlDump;
use Graze\Morphism\Parse\TokenStream;
use RuntimeException;

abstract class Dumper implements DumperInterface
{
    /**
     * @var Configuration
     */
    protected $config;

    /**
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

    /**
     * @param Connection $connection
     *
     * @return MysqlDump
     * @throws Exception
     */
    public function dump(Connection $connection)
    {
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$connection->getDatabase()]);
        $extractor->setCreateDatabases(false);

        $text = '';
        foreach ($extractor->extract() as $query) {
            $text .= "$query;\n";
        }
        $stream = TokenStream::newFromText($text, '');

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
