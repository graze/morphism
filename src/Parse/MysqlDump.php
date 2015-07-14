<?php

namespace Graze\Morphism\Parse;

/**
 * Represents a dump of one or more databases.
 */
class MysqlDump
{
    /**
     * @var CreateDatabase[]
     *
     * indexed by (string) database name; when enumerated, reflects the order
     * in which the databases declarations were parsed.
     */
    public $databases = [];

    /**
     * @param array $databases
     */
    public function __construct(array $databases)
    {
        $this->databases = $databases;
    }

    /**
     * Returns an array of SQL DDL statements for creating the database schema.
     *
     * @return string[]
     */
    public function getDDL()
    {
        $ddl = [];

        foreach ($this->databases as $database) {
            if ($database->name !== '') {
                $ddl = array_merge($ddl, $database->getDDL());
                $ddl[] = 'USE ' . Token::escapeIdentifier($database->name);
            }
            foreach ($database->tables as $table) {
                $ddl = array_merge($ddl, $table->getDDL());
            }
        }

        return $ddl;
    }
}
