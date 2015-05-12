<?php

namespace Graze\Morphism\Dump;

use Doctrine\DBAL\Connection;

interface DumperInterface
{
    /**
     * @param Connection $connection
     */
    public function dump(Connection $connection);
}
