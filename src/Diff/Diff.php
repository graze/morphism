<?php

namespace Graze\Morphism\Diff;

class Diff
{
    /**
     * @var array
     */
    private $queries;

    /**
     * @param array $queries
     */
    public function __construct(array $queries)
    {
        $this->queries = $queries;
    }

    /**
     * @return array
     */
    public function getQueries()
    {
        return $this->queries;
    }
}
