<?php
namespace Graze\Morphism\Parse;

use LogicException;
use RuntimeException;

/**
 * Represents the definition of an index.
 */
class IndexDefinition
{
    /** @var string|null */
    public $constraint = null;

    /** @var string */
    public $type = '';

    /** @var string|null */
    public $name = null;

    /**
     * @var array[] the columns which are indexed.
     *
     * Each entry is an array of the form:
     *
     * 'name'   => string
     * 'length' => int|null
     * 'sort'   => 'ASC'|'DESC'
     */
    public $columns = [];

    /** @var mixed[] Associative array of index options
     *
     * 'USING'          => string 'BTREE' | 'HASH'
     * 'KEY_BLOCK_SIZE' => integer
     * 'WITH PARSER'    => string;
     * 'COMMENT'        => string
     */
    public $options = [];

    /**
     * @var string[] Only present for FOREIGN KEYS
     *
     * 'table'     => string name of foreign table
     * 'columns'   => array('name' => string, 'length' => int|null)[] columns referenced in foreign table
     * 'ON DELETE' => string 'RESTRICT' | 'CASCADE' | 'SET NULL' | 'NO ACTION'
     * 'ON UPDATE' => string 'RESTRICT' | 'CASCADE' | 'SET NULL' | 'NO ACTION'
     */
    public $reference = [];

    /**
     * Parses an index definition from $stream
     *
     * The type of key (PRIMARY KEY, UNIQUE KEY, etc) should already have
     * been parsed from the stream. If the optional preceding CONSTRAINT clause
     * was parsed, you should supply its optional name in $constraint.
     *
     * @param TokenStream $stream
     * @param string $type 'PRIMARY KEY' | 'UNIQUE KEY' | 'KEY' | 'FULLTEXT KEY' | 'FOREIGN KEY'
     * @param string|null $constraint name supplied in optional CONSTRAINT clause
     */
    public function parse(TokenStream $stream, $type, $constraint = null)
    {
        $this->type = $type;

        switch ($type) {
            case 'PRIMARY KEY':
                $this->parseOptionalIndexType($stream);
                $this->parseIndexColumns($stream);
                $this->parseIndexOptions($stream);
                break;

            case 'UNIQUE KEY':
            case 'KEY':
            case 'FULLTEXT KEY':
                $this->parseOptionalIndexType($stream);
                if (!isset($this->options['USING'])) {
                    $this->parseOptionalIndexName($stream);
                    $this->parseOptionalIndexType($stream);
                }
                if (!is_null($constraint)) {
                    $this->name = $constraint;
                }
                $this->parseIndexColumns($stream);
                $this->parseIndexOptions($stream);
                break;

            case 'FOREIGN KEY':
                $this->constraint = $constraint;
                $this->parseOptionalIndexName($stream);
                $this->parseIndexColumns($stream);
                $this->parseReferenceDefinition($stream);
                break;

            default:
                throw new LogicException("Internal error - unknown index type '$type'");
        }
    }

    /**
     * @param TokenStream $stream
     */
    private function parseOptionalIndexName(TokenStream $stream)
    {
        $mark = $stream->getMark();
        $token = $stream->nextToken();
        if ($token->type === 'identifier') {
            $this->name = $token->text;
        } else {
            $stream->rewind($mark);
        }
    }

    /**
     * @param TokenStream $stream
     */
    private function parseOptionalIndexType(TokenStream $stream)
    {
        if ($stream->consume('USING')) {
            $this->parseIndexType($stream);
        }
    }

    /**
     * @param TokenStream $stream
     */
    private function parseIndexType(TokenStream $stream)
    {
        if ($stream->consume('BTREE')) {
            $using = 'BTREE';
        } elseif ($stream->consume('HASH')) {
            $using = 'HASH';
        } else {
            throw new RuntimeException("Expected BTREE or HASH");
        }
        $this->options['USING'] = $using;
    }

    /**
     * @param TokenStream $stream
     */
    private function parseIndexColumns(TokenStream $stream)
    {
        $this->columns = $this->expectIndexColumns($stream);
    }

    /**
     * @param TokenStream $stream
     * @return array
     */
    private function expectIndexColumns(TokenStream $stream)
    {
        $columns = [];
        $stream->expect('symbol', '(');
        while (true) {
            $column = [
                'name'   => $stream->expectName(),
                'length' => null,
                'sort'   => 'ASC',
            ];
            if ($stream->consume([['symbol', '(']])) {
                $column['length'] = $stream->expectNumber();
                $stream->expect('symbol', ')');
            }
            if ($stream->consume('ASC')) {
                $column['sort'] = 'ASC';
            } elseif ($stream->consume('DESC')) {
                $column['sort'] = 'DESC';
            }
            $columns[] = $column;
            if (!$stream->consume([['symbol', ',']])) {
                break;
            }
        }

        $stream->expect('symbol', ')');

        return $columns;
    }

    /**
     * @param TokenStream $stream
     */
    private function parseIndexOptions(TokenStream $stream)
    {
        while (true) {
            if ($stream->consume('KEY_BLOCK_SIZE')) {
                $stream->consume([['symbol', '=']]);
                $this->options['KEY_BLOCK_SIZE'] = $stream->expectNumber();
            } elseif ($stream->consume('WITH PARSER')) {
                $this->options['WITH PARSER'] = $stream->expectName();
            } elseif ($stream->consume('COMMENT')) {
                $this->options['COMMENT'] = $stream->expectString();
            } elseif ($stream->consume('USING')) {
                $this->parseIndexType($stream);
            } else {
                break;
            }
        }
    }

    /**
     * @param TokenStream $stream
     */
    private function parseReferenceDefinition(TokenStream $stream)
    {
        $stream->expect('identifier', 'REFERENCES');

        $tableOrSchema = $stream->expectName();
        if ($stream->consume([['symbol', '.']])) {
            $schema = $tableOrSchema;
            $table = $stream->expectName();
        } else {
            $schema = null;
            $table = $tableOrSchema;
        }

        $this->reference['schema'] = $schema;
        $this->reference['table'] = $table;
        $this->reference['columns'] = $this->expectIndexColumns($stream);
        $this->reference['ON DELETE'] = 'RESTRICT';
        $this->reference['ON UPDATE'] = 'RESTRICT';

        while (true) {
            if ($stream->consume('MATCH')) {
                throw new RuntimeException("MATCH clause is not supported in this tool, or in MySQL itself!");
            } elseif ($stream->consume('ON DELETE')) {
                $this->parseReferenceOption($stream, 'ON DELETE');
            } elseif ($stream->consume('ON UPDATE')) {
                $this->parseReferenceOption($stream, 'ON UPDATE');
            } else {
                break;
            }
        }
    }

    /**
     * @param TokenStream $stream
     * @param string $clause
     */
    private function parseReferenceOption(TokenStream $stream, $clause)
    {
        $availableOptions = ['RESTRICT', 'CASCADE', 'SET NULL', 'NO ACTION'];
        foreach ($availableOptions as $option) {
            if ($stream->consume($option)) {
                $this->reference[$clause] = $option;
                return;
            }
        }
        throw new RuntimeException("Expected one of: " . implode(", ", $availableOptions));
    }

    /**
     * Returns an array of all sequences of columns covered by this index. Includes optional lengths.
     *
     * E.g. the index ```KEY idx (x,y(12),z)``` will give ```[['x', 'y(12)', 'z'], ['x', 'y(12)'], ['x']]```.
     *
     * @return string[][]
     */
    public function getCovers()
    {
        // TODO - we should really have separate IndexPart objects so we can
        // correctly cope with pathological cases like:
        //
        //      CREATE TABLE evil (
        //          `bad(16)` varchar(32) not null,
        //          KEY (`bad(16)`(8))
        //      )
        //
        $covers = [];
        $cover = [];
        foreach ($this->columns as $column) {
            $name = $column['name'];
            if ($column['length']) {
                $name .= '(' . $column['length'] . ')';
            }
            $cover[] = $name;
            $covers[] = $cover;
        }
        return $covers;
    }

    /**
     * Returns the sequence of indexed columns, including optional lengths.
     *
     * E.g. the index ```KEY idx (x, y(12))``` will give ```['x', 'y(12)']```
     *
     * @return string[]
     */
    public function getColumns()
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $name = $column['name'];
            if ($column['length']) {
                $name .= '(' . $column['length'] . ')';
            }
            $columns[] = $name;
        }
        return $columns;
    }

    /**
     * Returns an SQL fragment for declaring this index as part of a table definition.
     *
     * @return string
     */
    public function toString()
    {
        $line = '';
        if ($this->type === 'FOREIGN KEY') {
            $line = "CONSTRAINT " . Token::escapeIdentifier($this->constraint) . " ";
        }
        $line .= $this->type;
        if (!in_array($this->type, ['PRIMARY KEY', 'FOREIGN KEY'])) {
            $line .= " " . Token::escapeIdentifier($this->name);
        }
        $cols = [];
        foreach ($this->columns as $column) {
            $col = Token::escapeIdentifier($column['name']);
            if (!is_null($column['length'])) {
                $col .= "(" . $column['length'] . ")";
            }
            $cols[] = $col;
        }
        $line .= " (" . implode(',', $cols) . ")";

        if (isset($this->options['USING'])) {
            $line .= " USING " . $this->options['USING'];
        }
        if (isset($this->options['KEY_BLOCK_SIZE']) && $this->options['KEY_BLOCK_SIZE'] !== 0) {
            $line .= " KEY_BLOCK_SIZE=" . $this->options['KEY_BLOCK_SIZE'];
        }
        if (isset($this->options['COMMENT']) && $this->options['COMMENT'] !== '') {
            $line .= " COMMENT " . Token::escapeString($this->options['COMMENT']);
        }
        if (isset($this->options['WITH PARSER'])) {
            $line .= " WITH PARSER " . $this->options['WITH PARSER'];
        }

        if ($this->type === 'FOREIGN KEY') {
            $reference = Token::escapeIdentifier($this->reference['table']);
            if (!is_null($this->reference['schema'])) {
                $reference = Token::escapeIdentifier($this->reference['schema']) . '.' . $reference;
            }
            $line .= " REFERENCES $reference";
            $cols = [];
            foreach ($this->reference['columns'] as $column) {
                $col = Token::escapeIdentifier($column['name']);
                if (!is_null($column['length'])) {
                    $col .= "(" . $column['length'] . ")";
                }
                $cols[] = $col;
            }
            $line .= " (" . implode(',', $cols) . ")";
            foreach (['ON DELETE', 'ON UPDATE'] as $clause) {
                $action = $this->reference[$clause];
                if ($action !== 'RESTRICT') {
                    $line .= " $clause $action";
                }
            }
        }
        return $line;
    }
}
