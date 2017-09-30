<?php
namespace Graze\Morphism\Parse;

use RuntimeException;

/**
 * Represents a table definition.
 */
class CreateTable
{
    /** @var string */
    public $name = '';

    /** @var ColumnDefinition[] */
    public $columns = [];

    /** @var IndexDefinition[] definitions of non-foreign keys */
    public $indexes = [];

    /** @var IndexDefinition[] definitions of foreign keys */
    public $foreigns = [];

    /** @var TableOptions */
    public $options = null;

    /** @var array */
    private $_covers = [];

    /**
     * Constructor.
     *
     * @param CollationInfo $databaseCollation
     */
    public function __construct(CollationInfo $databaseCollation)
    {
        $this->options = new TableOptions($databaseCollation);
    }

    /**
     * Sets the storage engine the table is assumed to use, unless
     * explicitly overridden via an ENGINE= clause at the end of
     * the table definition.
     *
     * @param string $engine
     * @return void
     */
    public function setDefaultEngine($engine)
    {
        $this->options->setDefaultEngine($engine);
    }

    /**
     * Parses a table definition from $stream.
     *
     * The DDL may be of the form 'CREATE TABLE ...' or 'CREATE TABLE IF NOT EXISTS ...'.
     *
     * An exception will be thrown if a valid CREATE TABLE statement cannot be recognised.
     *
     * @param TokenStream $stream
     */
    public function parse(TokenStream $stream)
    {
        if ($stream->consume('CREATE TABLE')) {
            $stream->consume('IF NOT EXISTS');
        } else {
            throw new RuntimeException("Expected CREATE TABLE");
        }

        $this->name = $stream->expectName();
        $stream->expectOpenParen();

        while (true) {
            $hasConstraintKeyword = $stream->consume('CONSTRAINT');
            if ($stream->consume('PRIMARY KEY')) {
                $this->_parseIndex($stream, 'PRIMARY KEY');
            } elseif ($stream->consume('KEY') ||
                $stream->consume('INDEX')
            ) {
                if ($hasConstraintKeyword) {
                    throw new RuntimeException("Bad CONSTRAINT");
                }
                $this->_parseIndex($stream, 'KEY');
            } elseif ($stream->consume('FULLTEXT')) {
                if ($hasConstraintKeyword) {
                    throw new RuntimeException("Bad CONSTRAINT");
                }
                $stream->consume('KEY') || $stream->consume('INDEX');
                $this->_parseIndex($stream, 'FULLTEXT KEY');
            } elseif ($stream->consume('UNIQUE')) {
                $stream->consume('KEY') || $stream->consume('INDEX');
                $this->_parseIndex($stream, 'UNIQUE KEY');
            } elseif ($stream->consume('FOREIGN KEY')) {
                $this->_parseIndex($stream, 'FOREIGN KEY');
            } elseif ($hasConstraintKeyword) {
                $constraint = $stream->expectName();
                if ($stream->consume('PRIMARY KEY')) {
                    $this->_parseIndex($stream, 'PRIMARY KEY', $constraint);
                } elseif ($stream->consume('UNIQUE')) {
                    $stream->consume('KEY') || $stream->consume('INDEX');
                    $this->_parseIndex($stream, 'UNIQUE KEY', $constraint);
                } elseif ($stream->consume('FOREIGN KEY')) {
                    $this->_parseIndex($stream, 'FOREIGN KEY', $constraint);
                } else {
                    throw new RuntimeException("Bad CONSTRAINT");
                }
            } else {
                $this->_parseColumn($stream);
            }
            $token = $stream->nextToken();
            if ($token->eq('symbol', ',')) {
                continue;
            } elseif ($token->eq('symbol', ')')) {
                break;
            } else {
                throw new RuntimeException("Expected ',' or ')'");
            }
        }

        $this->_processTimestamps();
        $this->_processIndexes();
        $this->_processAutoIncrement();
        $this->_parseTableOptions($stream);
        $this->_processColumnCollations();
    }

    /**
     * Returns the table's collation.
     *
     * @return CollationInfo
     */
    public function getCollation()
    {
        return $this->options->collation;
    }

    /**
     * Returns an array of SQL DDL statements to create the table.
     *
     * @return string
     */
    public function getDDL()
    {
        $lines = [];
        foreach ($this->columns as $column) {
            $lines[] = "  " . $column->toString($this->getCollation());
        }
        foreach ($this->indexes as $index) {
            $lines[] = "  " . $index->toString();
        }
        foreach ($this->foreigns as $foreign) {
            $lines[] = "  " . $foreign->toString();
        }

        $text = "CREATE TABLE " . Token::escapeIdentifier($this->name) . " (\n" .
            implode(",\n", $lines) .
            "\n" .
            ")";

        $options = $this->options->toString();
        if ($options !== '') {
            $text .= " " . $this->options->toString();
        }

        return [$text];
    }

    /**
     * @param TokenStream $stream
     */
    private function _parseColumn(TokenStream $stream)
    {
        $column = new ColumnDefinition();
        $column->parse($stream);
        if (array_key_exists(strtolower($column->name), $this->columns)) {
            throw new RuntimeException("Duplicate column name '" . $column->name . "'");
        }
        $this->columns[strtolower($column->name)] = $column;
        $this->indexes = array_merge(
            $this->indexes,
            $column->indexes
        );
    }

    /**
     * @param TokenStream $stream
     * @param string $type
     * @param string|null $constraint
     */
    private function _parseIndex(TokenStream $stream, $type, $constraint = null)
    {
        $index = new IndexDefinition();
        $index->parse($stream, $type, $constraint);
        $this->indexes[] = $index;
    }

    /**
     * @param TokenStream $stream
     */
    private function _parseTableOptions(TokenStream $stream)
    {
        $this->options->parse($stream);
    }

    private function _processTimestamps()
    {
        // To specify automatic properties, use the DEFAULT CURRENT_TIMESTAMP
        // and ON UPDATE CURRENT_TIMESTAMP clauses. The order of the clauses
        // does not matter. If both are present in a column definition, either
        // can occur first.

        // collect all timestamps
        $ts = [];
        foreach ($this->columns as $column) {
            if ($column->type === 'timestamp') {
                $ts[] = $column;
            }
        }
        if (count($ts) === 0) {
            return;
        }

        // none of NULL, DEFAULT or ON UPDATE CURRENT_TIMESTAMP have been specified
        if (!$ts[0]->nullable && is_null($ts[0]->default) && !$ts[0]->onUpdateCurrentTimestamp) {
            $ts[0]->nullable = false;
            $ts[0]->default = 'CURRENT_TIMESTAMP';
            $ts[0]->onUpdateCurrentTimestamp = true;
        }

        // [[ this restriction no longer exists as of MySQL 5.6.5 and MariaDB 10.0.1 ]]

        // One TIMESTAMP column in a table can have the current timestamp as
        // the default value for initializing the column, as the auto-update
        // value, or both. It is not possible to have the current timestamp
        // be the default value for one column and the auto-update value for
        // another column.

        // $specials = 0;
        // foreach($ts as $column) {
        //     if ($column->default === 'CURRENT_TIMESTAMP' ||
        //         $column->onUpdateCurrentTimestamp
        //     ) {
        //         if (++$specials > 1) {
        //             throw new RuntimeException("There can be only one TIMESTAMP column with CURRENT_TIMESTAMP in DEFAULT or ON UPDATE clause");
        //         }
        //     }
        // }

        foreach ($ts as $column) {
            if (!$column->nullable && is_null($column->default)) {
                $column->default = '0000-00-00 00:00:00';
            }
        }
    }

    private function _processIndexes()
    {
        // check indexes are sane wrt available columns
        foreach ($this->indexes as $index) {
            foreach ($index->columns as $indexColumn) {
                $indexColumnName = $indexColumn['name'];
                if (!array_key_exists(strtolower($indexColumnName), $this->columns)) {
                    throw new RuntimeException("Key column '$indexColumnName' doesn't exist in table");
                }
            }
        }

        // figure out all sequences of columns covered by non-FK indexes
        foreach ($this->indexes as $index) {
            if ($index->type !== 'FOREIGN KEY') {
                foreach ($index->getCovers() as $cover) {
                    $lookup = implode('\0', $cover);
                    $this->_covers[$lookup] = true;
                }
            }
        }

        $indexes = [];
        $foreigns = [];
        $ibfkCounter = 0;

        foreach ($this->indexes as $index) {
            if ($index->type === 'FOREIGN KEY') {
                // TODO - doesn't correctly deal with indexes like foo(10)
                $lookup = implode('\0', $index->getColumns());
                if (!array_key_exists($lookup, $this->_covers)) {
                    $newIndex = new IndexDefinition();
                    $newIndex->type = 'KEY';
                    $newIndex->columns = $index->columns;
                    if (!is_null($index->constraint)) {
                        $newIndex->name = $index->constraint;
                    } elseif (!is_null($index->name)) {
                        $newIndex->name = $index->name;
                    }
                    $indexes[] = $newIndex;
                }
                $foreign = new IndexDefinition();
                if (is_null($index->constraint)) {
                    $foreign->constraint = $this->name . '_ibfk_' . ++$ibfkCounter;
                } else {
                    $foreign->constraint = $index->constraint;
                }
                $foreign->type = 'FOREIGN KEY';
                $foreign->columns = $index->columns;
                $foreign->reference = $index->reference;
                $foreigns[] = $foreign;
            } else {
                $indexes[] = $index;
            }
        }

        // now synthesise names for any unnamed indexes,
        // and collect indexes by type
        $usedName = [];
        $keyTypes = [
            'PRIMARY KEY',
            'UNIQUE KEY',
            'KEY',
            'FULLTEXT KEY',
            'FOREIGN KEY',
        ];
        $indexesByType = array_fill_keys($keyTypes, []);
        foreach ($indexes as $index) {
            $name = $index->name;
            if ($index->type === 'PRIMARY KEY') {
                $name = 'PRIMARY';
            } elseif (is_null($name)) {
                $base = $index->columns[0]['name'];
                $name = $base;
                $i = 1;
                while (isset($usedName[$name])) {
                    $name = $base . '_' . ++$i;
                }
                $index->name = $name;
            } elseif (array_key_exists(strtolower($name), $usedName)) {
                throw new RuntimeException("Duplicate key name '$name'");
            }
            $index->name = $name;
            $usedName[strtolower($name)] = true;

            $indexesByType[$index->type][] = $index;
        }

        if (count($indexesByType['PRIMARY KEY']) > 1) {
            throw new RuntimeException("Multiple PRIMARY KEYs defined");
        }

        foreach ($indexesByType['PRIMARY KEY'] as $pk) {
            foreach ($pk->columns as $indexColumn) {
                $column = $this->columns[strtolower($indexColumn['name'])];
                if ($column->nullable) {
                    $column->nullable = false;
                    if (is_null($column->default)) {
                        $column->default = $column->getUninitialisedValue();
                    }
                }
            }
        }

        $this->indexes = [];
        foreach (array_reduce($indexesByType, 'array_merge', []) as $index) {
            $this->indexes[$index->name] = $index;
        }
        foreach ($foreigns as $foreign) {
            $this->foreigns[] = $foreign;
        }
    }

    private function _processAutoIncrement()
    {
        $count = 0;
        foreach ($this->columns as $column) {
            if ($column->autoIncrement) {
                if (++$count > 1) {
                    throw new RuntimeException("There can be only one AUTO_INCREMENT column");
                }
                if (! array_key_exists($column->name, $this->_covers)) {
                    throw new RuntimeException("AUTO_INCREMENT column must be defined as a key");
                }
            }
        }
    }

    private function _processColumnCollations()
    {
        foreach ($this->columns as $column) {
            $column->applyTableCollation($this->getCollation());
        }
    }

    /**
     * Returns ALTER TABLE statement to transform this table into the one
     * represented by $that. If the tables are already equivalent, just
     * returns the empty string.
     *
     * $flags        |
     * :-------------|----
     * 'alterEngine' | (bool) include ALTER TABLE ... ENGINE= [default: true]
     *
     * @param CreateTable $that
     * @param array $flags
     * @return string[]
     */
    public function diff(CreateTable $that, array $flags = [])
    {
        $flags += [
            'alterEngine' => true
        ];

        $alters = array_merge(
            $this->_diffColumns($that),
            $this->_diffIndexes($that),
            $this->_diffOptions($that, [
                'alterEngine' => $flags['alterEngine']
            ])
        );

        if (count($alters) === 0) {
            return [];
        }

        return ["ALTER TABLE " . Token::escapeIdentifier($this->name) . "\n" . implode(",\n", $alters)];
    }

    /**
     * @param CreateTable $that
     * @return array
     */
    private function _diffColumns(CreateTable $that)
    {
        $alters = [];
        $permutation = [];
        foreach (array_keys($this->columns) as $columnName) {
            if (array_key_exists($columnName, $that->columns)) {
                $permutation[] = $columnName;
            } else {
                $alters[] = "DROP COLUMN " . Token::escapeIdentifier($columnName);
            }
        }

        $prevColumn = null;
        $thatPosition = " FIRST";
        $j = 0;
        foreach ($that->columns as $columnName => $column) {
            if (array_key_exists($columnName, $this->columns)) {
                // An existing column is being changed
                $thisDefinition = $this->columns[$columnName]->toString($this->getCollation());
                $thatDefinition = $that->columns[$columnName]->toString($that->getCollation());

                // about to 'add' $columnName - get its location in the currently
                // permuted state of the tabledef
                $i = array_search($columnName, $permutation);

                // figure out the column it currently sits after, in case we
                // need to change it
                $thisPosition = ($i === 0) ? " FIRST" : " AFTER " . Token::escapeIdentifier($permutation[$i - 1]);

                if ($thisDefinition !== $thatDefinition ||
                    $thisPosition   !== $thatPosition
                ) {
                    $alter = "MODIFY COLUMN " . $thatDefinition;

                    // position has changed
                    if ($thisPosition !== $thatPosition) {
                        $alter .= $thatPosition;

                        // We need to update our permutation to reflect the new position.
                        // Column is being inserted at position $j, and is currently residing at $i.

                        // remove from current location
                        array_splice($permutation, $i, 1, []);

                        // insert at new location
                        array_splice($permutation, $j, 0, $columnName);
                    }

                    $alters[] = $alter;
                }
            } else {
                // A new column is being added
                $alter = "ADD COLUMN " . $column->toString($this->getCollation());
                if ($j < count($permutation)) {
                    $alter .= $thatPosition;
                }
                $alters[] = $alter;

                $i = is_null($prevColumn) ? 0 : 1 + array_search($prevColumn, $permutation);
                array_splice($permutation, $i, 0, [$columnName]);
            }

            $prevColumn = $columnName;
            $thatPosition = " AFTER " . Token::escapeIdentifier($prevColumn);
            $j++;
        }

        return $alters;
    }

    /**
     * @param CreateTable $that
     * @return array
     */
    private function _diffIndexes(CreateTable $that)
    {
        $alters = [];

        foreach ($this->indexes as $indexName => $index) {
            if (!array_key_exists($indexName, $that->indexes) ||
                $index->toString() !== $that->indexes[$indexName]->toString()
            ) {
                switch ($index->type) {
                    case 'PRIMARY KEY':
                        $alter = "DROP PRIMARY KEY";
                        break;

                // TODO - foreign keys???

                    default:
                        $alter = "DROP KEY " . Token::escapeIdentifier($indexName);
                        break;
                }
                $alters[] = $alter;
            }
        }

        foreach ($that->indexes as $indexName => $index) {
            if (!array_key_exists($indexName, $this->indexes) ||
                $index->toString() !== $this->indexes[$indexName]->toString()
            ) {
                $alters[] = "ADD " . $index->toString();
            }
        }

        return $alters;
    }

    /**
     * @param CreateTable $that
     * @param array $flags
     * @return array
     */
    private function _diffOptions(CreateTable $that, array $flags = [])
    {
        $flags += [
            'alterEngine' => true
        ];
        $diff = $this->options->diff($that->options, [
            'alterEngine' => $flags['alterEngine']
        ]);
        return ($diff == '') ? [] : [$diff];
    }
}
