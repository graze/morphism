<?php

namespace Graze\Morphism\Event;

use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Event;

class QueryEvent extends Event
{
    /**
     * @var string
     */
    private $query;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param string $query
     * @param Connection $connection
     */
    public function __construct($query, Connection $connection)
    {
        $this->query = $query;
        $this->connection = $connection;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }
}
