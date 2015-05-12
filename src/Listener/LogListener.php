<?php

namespace Graze\Morphism\Listener;

use Graze\Morphism\Event\QueryEvent;
use Illuminate\Filesystem\Filesystem;

class LogListener
{
    /**
     * @var Filesystem
     */
    private $file;

    /**
     * @var string
     */
    private $logDir;

    /**
     * @param Filesystem $file
     * @param string $logDir
     */
    public function __construct(Filesystem $file, $logDir)
    {
        $this->file = $file;
        $this->logDir = $logDir;
    }

    /**
     * @param QueryEvent $event
     */
    public function onQueryApplied(QueryEvent $event)
    {
        $query = $event->getQuery();
        $connection = $event->getConnection();

        $path = $this->logDir . '/' . $connection->getDatabase() . '.sql';
        $this->file->append($path, $query . PHP_EOL . PHP_EOL);
    }

    /**
     * @param QueryEvent $event
     */
    public function onQuerySkipped(QueryEvent $event)
    {
        $query = $event->getQuery();
        $connection = $event->getConnection();

        $path = $this->logDir . '/' . $connection->getDatabase() . '.sql';
        $this->file->append($path, '-- [SKIPPED]' . PHP_EOL);
        $this->file->append($path, preg_replace('/^/xms', '-- ', $query) . PHP_EOL . PHP_EOL);
    }
}
