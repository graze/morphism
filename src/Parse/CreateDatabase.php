<?php
namespace Graze\Morphism\Parse;

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

    private $_collation = null;
    private $_defaultCollation = null;

    /**
     * Constructor.
     */
    public function __construct(CollationInfo $defaultCollation)
    {
        $this->_collation = new CollationInfo();
        $this->_defaultCollation = clone $defaultCollation;
    }

    /**
     * Parses a database declaration from $stream.
     *
     * Declarations must be of the form 'CREATE DATABASE ...' or
     * 'CREATE DATABASE IF NOT EXISTS ...'. Anything else will cause 
     * an exception to be thrown.
     *
     * @throws \RuntimeException
     */
    public function parse(TokenStream $stream)
    {
        if ($stream->consume('CREATE DATABASE')) {
            $stream->consume('IF NOT EXISTS');
        }
        else {
            throw new \RuntimeException("expected CREATE DATABASE");
        }

        $this->name = $stream->expectName();
        while(true) {
            $stream->consume('DEFAULT');
            if ($stream->consume('CHARSET') ||
                $stream->consume('CHARACTER SET')
            ) {
                $stream->consume([['symbol', '=']]);
                $charset = $stream->expectName();
                if (strtoupper($charset) === 'DEFAULT') {
                    $this->_collation = new CollationInfo();
                }
                else {
                    $this->_collation->setCharset($charset);
                }
            }
            else if ($stream->consume('COLLATE')) {
                $stream->consume([['symbol', '=']]);
                $collation = $stream->expectName();
                if (strtoupper($collation) === 'DEFAULT') {
                    $this->_collation = new CollationInfo();
                }
                else {
                    $this->_collation->setCollation($collation);
                }
            }
            else {
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
        return $this->_collation->isSpecified()
            ? $this->_collation
            : $this->_defaultCollation;
    }

    /**
     * Asserts that the table described by $table belongs to this
     * database.
     */
    public function addTable(CreateTable $table)
    {
        $this->tables[$table->name] = $table;
    }

    /**
     * Returns SQL DDL to create the database.
     *
     * The returned DDL only refers to the database itself, it does not include
     * the necessary DDL to create any contained tables. For that you will need
     * to iterate over the $tables property, calling toString() on each element.
     *
     * @return string
     */
    public function toString()
    {
        $text = "CREATE DATABASE IF NOT EXISTS " . Token::escapeIdentifier($this->name);

        $collation = $this->getCollation();
        if ($collation->isSpecified()) {
            $text .= " DEFAULT CHARACTER SET " . $collation->getCharset();
            if (!$collation->isDefaultCollation()) {
                $text .= " COLLATE " . $collation->getCollation();
            }
        }

        return $text;
    }

    /**
     * Returns SQL DDL to transform this database and all contained tables
     * into the database specified by $that.
     *
     * $flags        |
     * :-------------|----
     * 'createTable' | (bool) include 'CREATE TABLE' statements [default: true]
     * 'dropTable'   | (bool) include 'DROP TABLE' statements [default: true]
     * 'alterEngine' | (bool) include ALTER TABLE ... ENGINE= [default: true]
     *
     * @return string
     */
    public function diff(self $that, array $flags = [])
    {
        $flags += [
            'createTable' => true,
            'dropTable'   => true,
            'alterEngine' => true,
        ];

        $thisTableNames = array_keys($this->tables);
        $thatTableNames = array_keys($that->tables);

        $commonTableNames  = array_intersect($thisTableNames, $thatTableNames);
        $droppedTableNames = array_diff($thisTableNames, $thatTableNames);
        $createdTableNames = array_diff($thatTableNames, $thisTableNames);

        $text = '';

        if ($flags['dropTable'] && count($droppedTableNames) > 0) {
            foreach($droppedTableNames as $tableName) {
                $text .= "DROP TABLE IF EXISTS " . Token::escapeIdentifier($tableName) . ";\n";
            }
            $text .= "\n";
        }

        if ($flags['createTable'] && count($createdTableNames) > 0) {
            foreach($createdTableNames as $tableName) {
                $text .= $that->tables[$tableName]->toString();
                $text .= "\n";
            }
        }

        foreach($commonTableNames as $tableName) {
            $thisTable = $this->tables[$tableName];
            $thatTable = $that->tables[$tableName];
            $tableDiff = $thisTable->diff($thatTable, [
                'alterEngine' => $flags['alterEngine'],
            ]);
            if ($tableDiff !== '') {
                $text .= $tableDiff . "\n";
            }
        }

        return $text;
    }
}
