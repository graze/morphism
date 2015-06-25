<?php

namespace Graze\Morphism\Extractor;

use Doctrine\DBAL\Connection;

class ExtractorFactory
{
    /**
     * @param Connection $connection
     *
     * @return Extractor
     */
    public function buildFromConnection(Connection $connection)
    {
        $extractor = new Extractor($connection);
        $extractor->setDatabases([$connection->getDatabase()]);
        return $extractor;
    }
}
