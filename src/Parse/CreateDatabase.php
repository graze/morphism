<?php
namespace Graze\Morphism\Parse;

use RuntimeException;

/**
 * Represents the definition of a database.
 */
class CreateDatabase
{
    /** @var string */
    public $name = '';

    /**
     * @var CreateTable[]
     *
     * indexed by (string) table name; when enumerated, reflects the order
     * in which the table definitions were parsed.
     */
    public $tables = [];

    /** @var CollationInfo */
    private $collation = null;
    /** @var CollationInfo */
    private $defaultCollation = null;

    /**
     * Constructor.
     *
     * @param CollationInfo $defaultCollation
     */
    public function __construct(CollationInfo $defaultCollation)
    {
        $this->collation = new CollationInfo();
        $this->defaultCollation = clone $defaultCollation;
    }

    /**
     * Parses a database declaration from $stream.
     *
     * Declarations must be of the form 'CREATE DATABASE ...' or
     * 'CREATE DATABASE IF NOT EXISTS ...'. Anything else will cause
     * an exception to be thrown.
     *
     * @param TokenStream $stream
     */
    public function parse(TokenStream $stream)
    {
        if ($stream->consume('CREATE DATABASE')) {
            $stream->consume('IF NOT EXISTS');
        } else {
            throw new RuntimeException("Expected CREATE DATABASE");
        }

        $this->name = $stream->expectName();
        while (true) {
            $stream->consume('DEFAULT');
            if ($stream->consume('CHARSET') ||
                $stream->consume('CHARACTER SET')
            ) {
                $stream->consume([[Token::SYMBOL, '=']]);
                $charset = $stream->expectName();
                if (strtoupper($charset) === 'DEFAULT') {
                    $this->collation = new CollationInfo();
                } else {
                    $this->collation->setCharset($charset);
                }
            } elseif ($stream->consume('COLLATE')) {
                $stream->consume([[Token::SYMBOL, '=']]);
                $collation = $stream->expectName();
                if (strtoupper($collation) === 'DEFAULT') {
                    $this->collation = new CollationInfo();
                } else {
                    $this->collation->setCollation($collation);
                }
            } else {
                break;
            }
        }
    }

    /**
     * Returns the default collation associated with the database.
     *
     * @return CollationInfo
     */
    public function getCollation()
    {
        return $this->collation->isSpecified()
            ? $this->collation
            : $this->defaultCollation;
    }

    /**
     * @param CreateTable $table
     * @throws RuntimeException
     */
    public function addTable(CreateTable $table)
    {
        if ($table->getName()) {
            $this->tables[$table->getName()] = $table;
        } else {
            throw new RuntimeException("No table name in Create Table object");
        }
    }

    /**
     * Returns an array of SQL DDL statements to create the database.
     *
     * The returned DDL only refers to the database itself, it does not include
     * the necessary DDL to create any contained tables. For that you will need
     * to iterate over the $tables property, calling getDDL() on each element.
     *
     * @return array
     * @throws RuntimeException
     */
    public function getDDL()
    {
        if (!$this->name) {
            throw new RuntimeException('No database name specified');
        }

        $text = "CREATE DATABASE IF NOT EXISTS " . Token::escapeIdentifier($this->name);

        $collation = $this->getCollation();
        if ($collation->isSpecified()) {
            $text .= " DEFAULT CHARACTER SET " . $collation->getCharset();
            if (!$collation->isDefaultCollation()) {
                $text .= " COLLATE " . $collation->getCollation();
            }
        }

        return [$text];
    }

    /**
     * Returns an array of SQL DDL statements to transform this database and
     * all contained tables into the database specified by $that.
     *
     * $flags        |
     * :-------------|----
     * 'createTable' | (bool) include 'CREATE TABLE' statements [default: true]
     * 'dropTable'   | (bool) include 'DROP TABLE' statements [default: true]
     * 'alterEngine' | (bool) include ALTER TABLE ... ENGINE= [default: true]
     * 'matchTables' | ['include' => $regex, 'exclude' => $regex] regex of tables to include/exclude
     *
     * @param CreateDatabase $that
     * @param array $flags
     * @return string[]
     */
    public function diff(CreateDatabase $that, array $flags = [])
    {
        $flags += [
            'createTable' => true,
            'dropTable'   => true,
            'alterEngine' => true,
            'matchTables' => [
                'include' => '',
                'exclude' => '',
            ],
        ];

        $thisTableNames = array_keys($this->tables);
        $thatTableNames = array_keys($that->tables);

        $commonTableNames  = array_intersect($thisTableNames, $thatTableNames);
        $droppedTableNames = array_diff($thisTableNames, $thatTableNames);
        $createdTableNames = array_diff($thatTableNames, $thisTableNames);

        $diff = [];

        $includeTablesRegex = $flags['matchTables']['include'];
        $excludeTablesRegex = $flags['matchTables']['exclude'];

        if ($flags['dropTable'] && count($droppedTableNames) > 0) {
            foreach ($droppedTableNames as $tableName) {
                if (($includeTablesRegex == '' || preg_match($includeTablesRegex, $tableName)) &&
                    ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $tableName))
                ) {
                    $diff[] = "DROP TABLE IF EXISTS " . Token::escapeIdentifier($tableName);
                }
            }
        }

        if ($flags['createTable'] && count($createdTableNames) > 0) {
            foreach ($createdTableNames as $tableName) {
                if (($includeTablesRegex == '' || preg_match($includeTablesRegex, $tableName)) &&
                    ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $tableName))
                ) {
                    $diff = array_merge($diff, $that->tables[$tableName]->getDDL());
                }
            }
        }

        foreach ($commonTableNames as $tableName) {
            if (($includeTablesRegex == '' || preg_match($includeTablesRegex, $tableName)) &&
                ($excludeTablesRegex == '' || !preg_match($excludeTablesRegex, $tableName))
            ) {
                $thisTable = $this->tables[$tableName];
                $thatTable = $that->tables[$tableName];
                $tableDiff = $thisTable->diff($thatTable, [
                    'alterEngine' => $flags['alterEngine'],
                ]);
                if (! empty($tableDiff)) {
                    $diff = array_merge($diff, $tableDiff);
                }
            }
        }

        return $diff;
    }
}
