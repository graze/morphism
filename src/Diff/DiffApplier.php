<?php

namespace Graze\Morphism\Diff;

use Doctrine\DBAL\Connection;
use Graze\Morphism\Event\QueryEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DiffApplier
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * @param EventDispatcherInterface $dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @param Diff $diff
     * @param Connection $connection
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function apply(Diff $diff, Connection $connection)
    {
        foreach ($diff->getQueries() as $query) {
            if ($this->shouldApply($query)) {
                $connection->executeQuery($query);
                $this->dispatcher->dispatch('query.applied', new QueryEvent($query, $connection));
            } else {
                $this->dispatcher->dispatch('query.skipped', new QueryEvent($query, $connection));
            }
        }
    }

    /**
     * @param string $query
     *
     * @return bool
     */
    protected function shouldApply($query)
    {
        return true;
    }
}
