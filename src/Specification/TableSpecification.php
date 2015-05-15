<?php

namespace Graze\Morphism\Specification;

use Graze\Morphism\Parse\CreateTable;

class TableSpecification
{
    /**
     * @var array
     */
    private $include;

    /**
     * @var array
     */
    private $exclude;

    /**
     * @param array $include
     * @param array $exclude
     */
    public function __construct(array $include = null, array $exclude = null)
    {
        $this->include = $include;
        $this->exclude = $exclude;
    }

    /**
     * @param CreateTable $table
     *
     * @return bool
     */
    public function isSatisfiedBy(CreateTable $table)
    {
        $includeTablesRegex = '';
        $excludeTablesRegex = '';

        if (count($this->include) !== 0) {
            $includeTablesRegex = '/^(' . implode('|', $this->include) . ')$/';
        }

        if (count($this->exclude) !== 0) {
            $excludeTablesRegex = '/^(' . implode('|', $this->exclude) . ')$/';
        }

        return ($includeTablesRegex === '' || preg_match($includeTablesRegex, $table->name)) &&
        ($excludeTablesRegex === '' || !preg_match($excludeTablesRegex, $table->name));
    }
}
