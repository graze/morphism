<?php
namespace Graze\Morphism\Parse;

/**
 * Represents the definition of a single column in a table.
 */
class ColumnDefinition
{
    /** @var string */
    public $name = '';

    /** @var string */
    public $type = '';

    /** @var int|null */
    public $length = null;

    /** @var int */
    public $decimals = 0;

    /** @var bool */
    public $unsigned = false;

    /** @var bool */
    public $zerofill = false;

    /** @var string[] */
    public $elements = [];

    /** @var CollationInfo */
    public $collation = null;

    /** @var bool */
    public $nullable = true;

    /** @var bool */
    public $autoIncrement = false;

    /** @var string|null */
    public $default = null;

    /** @var string|null */
    public $comment = null;

    /** @var bool */
    public $onUpdateCurrentTimestamp = false;

    /** @var IndexDefinition[] */
    public $indexes = [];

    /** @var bool */
    private $primaryKey = false;
    /** @var bool */
    private $uniqueKey = false;

    /** @var array */
    private static $typeInfoMap = [
        //                             format   default  allow   allow   allow   uninitialised
        // datatype       kind         Spec     Lengths  Autoinc Binary  Charset Value
        'bit'        => [ 'bit',       [0,1  ], [1],     false,  false,  false,  0,    ],
        'tinyint'    => [ 'int',       [0,1  ], [3,4],   true,   false,  false,  0,    ],
        'smallint'   => [ 'int',       [0,1  ], [5,6],   true,   false,  false,  0,    ],
        'mediumint'  => [ 'int',       [0,1  ], [8,9],   true,   false,  false,  0,    ],
        'int'        => [ 'int',       [0,1  ], [10,11], true,   false,  false,  0,    ],
        'bigint'     => [ 'int',       [0,1  ], [20,20], true,   false,  false,  0,    ],
        'double'     => [ 'decimal',   [0,  2], null,    true,   false,  false,  0,    ], // prec = 22
        'float'      => [ 'decimal',   [0,  2], null,    true,   false,  false,  0,    ], // prec = 12
        'decimal'    => [ 'decimal',   [0,1,2], [10,10], false,  false,  false,  0,    ],
        'date'       => [ 'date',      [0    ], null,    false,  false,  false,  0,    ],
        'time'       => [ 'time',      [0    ], null,    false,  false,  false,  0,    ],
        'timestamp'  => [ 'datetime',  [0    ], null,    false,  false,  false,  0,    ],
        'datetime'   => [ 'datetime',  [0,1  ], null,    false,  false,  false,  0,    ],
        'year'       => [ 'year',      [0,1  ], [4],     false,  false,  false,  0,    ],
        'char'       => [ 'text',      [0,1  ], [1],     false,  true,   true,   '',   ],
        'varchar'    => [ 'text',      [  1  ], null,    false,  true,   true,   '',   ],
        'binary'     => [ 'binary',    [0,1  ], [1],     false,  false,  false,  '',   ],
        'varbinary'  => [ 'text',      [  1  ], null,    false,  false,  false,  '',   ],
        'tinyblob'   => [ 'blob',      [0    ], null,    false,  false,  false,  null, ],
        'blob'       => [ 'blob',      [0    ], null,    false,  false,  false,  null, ],
        'mediumblob' => [ 'blob',      [0    ], null,    false,  false,  false,  null, ],
        'longblob'   => [ 'blob',      [0    ], null,    false,  false,  false,  null, ],
        'tinytext'   => [ 'blob',      [0    ], null,    false,  true,   true,   null, ],
        'text'       => [ 'blob',      [0    ], null,    false,  true,   true,   null, ],
        'mediumtext' => [ 'blob',      [0    ], null,    false,  true,   true,   null, ],
        'longtext'   => [ 'blob',      [0    ], null,    false,  true,   true,   null, ],
        'enum'       => [ 'enum',      [0    ], null,    false,  false,  true,   0,    ],
        'set'        => [ 'set',       [0    ], null,    false,  false,  true,   '',   ],
    ];
    /** @var array */
    private static $typeInfoCache = [];

    /** @var array */
    private static $aliasMap = [
        'bool'      => 'tinyint',
        'boolean'   => 'tinyint',
        'int1'      => 'tinyint',
        'int2'      => 'smallint',
        'int3'      => 'mediumint',
        'middleint' => 'mediumint',
        'int4'      => 'int',
        'integer'   => 'int',
        'int8'      => 'bigint',
        'dec'       => 'decimal',
        'numeric'   => 'decimal',
        'fixed'     => 'decimal',
        'real'      => 'double',
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->collation = new CollationInfo();
    }

    /**
     * Parse column definition from $stream.
     *
     * An exception will be thrown if a valid column definition cannot be
     * recognised.
     *
     * @param TokenStream $stream
     */
    public function parse(TokenStream $stream)
    {
        $this->name = $stream->expectName();
        $this->parseColumnDatatype($stream);
        $this->parseColumnOptions($stream);

        if ($this->primaryKey) {
            $this->addIndex('PRIMARY KEY');
        }
        if ($this->uniqueKey) {
            $this->addIndex('UNIQUE KEY');
        }
    }

    /**
     * @param string $type
     */
    private function addIndex($type)
    {
        // TODO - crying out for an IndexPart class
        $index = new IndexDefinition();
        $index->type = $type;
        $index->columns[] = [
            'name'   => $this->name,
            'length' => null,
            'sort'   => 'ASC',
        ];
        $this->indexes[] = $index;
    }

    /**
     * @return object|null
     */
    private function getTypeInfo()
    {
        if (array_key_exists($this->type, self::$typeInfoCache)) {
            return self::$typeInfoCache[$this->type];
        }

        if (!array_key_exists($this->type, self::$typeInfoMap)) {
            return null;
        }

        $data = self::$typeInfoMap[$this->type];

        $typeInfo = (object) [
            'kind'               => $data[0],
            'formatSpec'         => array_fill_keys($data[1], true) + array_fill_keys(range(0, 2), false),
            'defaultLengths'     => $data[2],
            'allowAutoIncrement' => $data[3],
            'allowBinary'        => $data[4],
            'allowCharset'       => $data[5],
            'allowDefault'       => !is_null($data[6]),
            'uninitialisedValue' => $data[6],
        ];
        $typeInfo->allowSign = $typeInfo->allowZerofill = in_array($typeInfo->kind, ['int', 'decimal']);
        self::$typeInfoCache[$this->type] = $typeInfo;

        return $typeInfo;
    }

    /**
     * @param TokenStream $stream
     */
    private function parseColumnDatatype(TokenStream $stream)
    {
        $token = $stream->nextToken();
        if ($token->type !== Token::IDENTIFIER) {
            throw new \RuntimeException("expected a datatype");
        }

        // map aliases to concrete type
        $sqlType = strtolower($token->text);
        if (array_key_exists($sqlType, self::$aliasMap)) {
            $type = self::$aliasMap[$sqlType];
        } else {
            switch ($sqlType) {
                case 'serial':
                    // SERIAL is an alias for  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE
                    $this->type = 'bigint';
                    $this->length = 20;
                    $this->unsigned = true;
                    $this->nullable = false;
                    $this->autoIncrement = true;
                    $this->uniqueKey = true;
                    return;

                case 'character':
                    if ($stream->consume('varying')) {
                        $sqlType .= ' varying';
                        $type = 'varchar';
                    } else {
                        $type = 'char';
                    }
                    break;

                case 'double':
                    $stream->consume('PRECISION');
                    $sqlType .= ' PRECISION';
                    $type = 'double';
                    break;

                case 'long':
                    if ($stream->consume('varbinary')) {
                        $sqlType .= ' varbinary';
                        $type = 'mediumblob';
                    } elseif ($stream->consume('varchar')) {
                        $sqlType .= ' varchar';
                        $type = 'mediumtext';
                    } else {
                        $type = 'mediumtext';
                    }
                    break;

                default:
                    $type = $sqlType;
            }
        }

        $this->type = $type;

        $typeInfo = $this->getTypeInfo();
        if (is_null($typeInfo)) {
            throw new \RuntimeException("unknown datatype '$type'");
        }

        $format = [];

        switch ($sqlType) {
            case 'timestamp':
                $this->nullable = false;
                break;

            case 'enum':
            case 'set':
                $stream->expectOpenParen();
                while (true) {
                    $this->elements[] = rtrim($stream->expectStringExtended(), " ");
                    $token = $stream->nextToken();
                    if ($token->eq(Token::SYMBOL, ',')) {
                        continue;
                    }
                    if ($token->eq(Token::SYMBOL, ')')) {
                        break;
                    }
                    throw new \RuntimeException("expected ',' or ')'");
                }
                break;

            case 'bool':
            case 'boolean':
                $format = [1];
                /* Take a copy so that edits don't make it back into the runtime cache. */
                $typeInfo = clone $typeInfo;
                $typeInfo->allowSign = false;
                $typeInfo->allowZerofill = false;
                break;

            default:
                $spec = $typeInfo->formatSpec;
                if ($stream->consume([[Token::SYMBOL, '(']])) {
                    if (!($spec[1] || $spec[2])) {
                        throw new \RuntimeException("unexpected '('");
                    }
                    $format[] = $stream->expectNumber();
                    if ($stream->consume([[Token::SYMBOL, ',']])) {
                        if (!$spec[2]) {
                            throw new \RuntimeException("unexpected ','");
                        }
                        $format[] = $stream->expectNumber();
                    } elseif (!$spec[1]) {
                        $mark = $stream->getMark();
                        $unexpectedToken = $stream->nextToken();
                        $stream->rewind($mark);
                        throw new \RuntimeException("expected ',' but got: '$unexpectedToken->text'");
                    }
                    $stream->expectCloseParen();
                } elseif (!$spec[0]) {
                    throw new \RuntimeException("expected '('");
                }
                break;
        }

        while (true) {
            $mark = $stream->getMark();
            $token1 = $stream->nextToken();
            if ($token1->type !== Token::IDENTIFIER) {
                $stream->rewind($mark);
                break;
            }

            if ($token1->eq(Token::IDENTIFIER, 'ZEROFILL')) {
                if (!$typeInfo->allowZerofill) {
                    throw new \RuntimeException("unexpected ZEROFILL");
                }
                $this->zerofill = true;
            } elseif ($token1->eq(Token::IDENTIFIER, 'UNSIGNED')) {
                if (!$typeInfo->allowSign) {
                    throw new \RuntimeException("unexpected UNSIGNED");
                }
                $this->unsigned = true;
            } elseif ($token1->eq(Token::IDENTIFIER, 'SIGNED')) {
                if (!$typeInfo->allowSign) {
                    throw new \RuntimeException("unexpected SIGNED");
                }
                $this->unsigned = false;
            } else {
                $stream->rewind($mark);
                break;
            }
        }

        if ($this->zerofill) {
            $this->unsigned = true;
        }

        $defaultLengths = $typeInfo->defaultLengths;
        if (!is_null($defaultLengths)) {
            if (count($format) === 0) {
                if (count($defaultLengths) === 1 || $this->unsigned) {
                    $format[0] = $defaultLengths[0];
                } else {
                    $format[0] = $defaultLengths[1];
                }
            }
        }

        if (array_key_exists(0, $format)) {
            $this->length = $format[0];
        }
        if (array_key_exists(1, $format)) {
            $this->decimals = $format[1];
        }

        if ($this->type === 'year' && $this->length !== 4) {
            throw new \RuntimeException("this tool will only accept 4 as a valid width for YEAR columns");
        }

        while (true) {
            $mark = $stream->getMark();
            $token1 = $stream->nextToken();
            if ($token1->type !== Token::IDENTIFIER) {
                $stream->rewind($mark);
                break;
            }

            if ($token1->eq(Token::IDENTIFIER, 'BINARY')) {
                if (!$typeInfo->allowBinary) {
                    throw new \RuntimeException("unexpected BINARY");
                }
                $this->collation->setBinaryCollation();
            } elseif ($token1->eq(Token::IDENTIFIER, 'CHARSET') ||
                $token1->eq(Token::IDENTIFIER, 'CHARACTER') && $stream->consume('SET')
            ) {
                if (!$typeInfo->allowCharset) {
                    throw new \RuntimeException("unexpected CHARSET");
                }
                $charset = $stream->expectName();
                $this->collation->setCharset($charset);
            } else {
                $stream->rewind($mark);
                break;
            }
        }

        if ($stream->consume('COLLATE')) {
            if (!$typeInfo->allowCharset) {
                throw new \RuntimeException("unexpected COLLATE");
            }
            $collation = $stream->expectName();
            $this->collation->setCollation($collation);
        }
    }

    /**
     * @param TokenStream $stream
     */
    private function parseColumnOptions(TokenStream $stream)
    {
        while (true) {
            $mark = $stream->getMark();
            $token1 = $stream->nextToken();
            if ($token1->type !== Token::IDENTIFIER) {
                $stream->rewind($mark);
                break;
            }

            if ($token1->eq(Token::IDENTIFIER, 'NOT') &&
                $stream->consume('NULL')
            ) {
                $this->nullable = false;
            } elseif ($token1->eq(Token::IDENTIFIER, 'NULL')
            ) {
                if (!$this->autoIncrement) {
                    $this->nullable = true;
                }
            } elseif ($token1->eq(Token::IDENTIFIER, 'DEFAULT')
            ) {
                $token2 = $stream->nextToken();

                if ($token2->eq(Token::IDENTIFIER, 'NOW') ||
                    $token2->eq(Token::IDENTIFIER, 'CURRENT_TIMESTAMP') ||
                    $token2->eq(Token::IDENTIFIER, 'LOCALTIME') ||
                    $token2->eq(Token::IDENTIFIER, 'LOCALTIMESTAMP')
                ) {
                    if (!$stream->consume([[Token::SYMBOL, '('], [Token::SYMBOL, ')']]) &&
                        $token2->eq(Token::IDENTIFIER, 'NOW')
                    ) {
                        throw new \RuntimeException("expected () after keyword NOW");
                    }
                    $token2 = new Token(Token::IDENTIFIER, 'CURRENT_TIMESTAMP');
                }

                try {
                    $this->default = $this->defaultValue($token2);
                } catch (Exception $e) {
                    throw new \RuntimeException("invalid DEFAULT for '" . $this->name . "'");
                }
            } elseif ($token1->eq(Token::IDENTIFIER, 'ON') &&
                $stream->consume('UPDATE')
            ) {
                $token2 = $stream->nextToken();
                if ($token2->eq(Token::IDENTIFIER, 'NOW') ||
                    $token2->eq(Token::IDENTIFIER, 'CURRENT_TIMESTAMP') ||
                    $token2->eq(Token::IDENTIFIER, 'LOCALTIME') ||
                    $token2->eq(Token::IDENTIFIER, 'LOCALTIMESTAMP')
                ) {
                    if (!$stream->consume([[Token::SYMBOL, '('], [Token::SYMBOL, ')']]) &&
                        $token2->eq(Token::IDENTIFIER, 'NOW')
                    ) {
                        throw new \RuntimeException("expected () after keyword NOW");
                    }
                    if (!in_array($this->type, ['timestamp', 'datetime'])) {
                        throw new \RuntimeException("ON UPDATE CURRENT_TIMESTAMP only valid for TIMESTAMP and DATETIME columns");
                    }
                    $this->onUpdateCurrentTimestamp = true;
                } else {
                    throw new \RuntimeException("expected CURRENT_TIMESTAMP, NOW, LOCALTIME or LOCALTIMESTAMP");
                }
            } elseif ($token1->eq(Token::IDENTIFIER, 'AUTO_INCREMENT')
            ) {
                if (!$this->getTypeInfo()->allowAutoIncrement) {
                    throw new \RuntimeException("AUTO_INCREMENT not allowed for this datatype");
                }
                $this->autoIncrement = true;
                $this->nullable = false;
            } elseif ($token1->eq(Token::IDENTIFIER, 'UNIQUE')
            ) {
                $stream->consume('KEY');
                $this->uniqueKey = true;
            } elseif ($token1->eq(Token::IDENTIFIER, 'PRIMARY') && $stream->consume('KEY') ||
                $token1->eq(Token::IDENTIFIER, 'KEY')
            ) {
                $this->primaryKey = true;
                $this->nullable = false;
            } elseif ($token1->eq(Token::IDENTIFIER, 'COMMENT')
            ) {
                $this->comment = $stream->expectString();
            } elseif ($token1->eq(Token::IDENTIFIER, 'SERIAL') &&
                $stream->consume('DEFAULT VALUE')
            ) {
                if (!$this->getTypeInfo()->allowAutoIncrement) {
                    throw new \RuntimeException("SERIAL DEFAULT VALUE is not allowed for this datatype");
                }
                $this->uniqueKey = true;
                $this->autoIncrement = true;
                $this->nullable = false;
                $this->default = null;
            } else {
                $stream->rewind($mark);
                break;
            }
        }
    }

    /**
     * Return the uninitialised value for the column.
     *
     * For example, ints will return 0, varchars '', etc. This is independent
     * of any DEFAULT value specified in the column definition. Null will be
     * returned for non-defaultable fields (blobs and texts).
     *
     * @return string|null;
     */
    public function getUninitialisedValue()
    {
        $typeInfo = $this->getTypeInfo();
        switch ($typeInfo->kind) {
            case 'enum':
                return $this->elements[0];

            case 'int':
                if ($this->zerofill) {
                    $length = $this->length;
                    return sprintf("%0{$length}d", 0);
                }
                return '0';

            case 'decimal':
                $decimals = is_null($this->decimals) ? 0 : $this->decimals;
                if ($this->zerofill) {
                    $length = $this->length;
                    return sprintf("%0{$length}.{$decimals}f", 0);
                }
                return sprintf("%.{$decimals}f", 0);

            default:
                return $typeInfo->uninitialisedValue;
        }
    }

    /**
     * Get the default value for the given token.
     *
     * @param Token $token
     * @return string|null
     * @throws \Exception
     */
    private function defaultValue(Token $token)
    {
        if ($token->eq(Token::IDENTIFIER, 'NULL')) {
            if (!$this->nullable) {
                throw new \Exception("Column type cannot have NULL default: $this->type");
            }
            return null;
        }

        if ($token->eq(Token::IDENTIFIER, 'CURRENT_TIMESTAMP')) {
            if (!in_array($this->type, ['timestamp', 'datetime'])) {
                throw new \Exception("Only 'timestamp' and 'datetime' types can have default value of CURRENT_TIMESTAMP");
            }
            return 'CURRENT_TIMESTAMP';
        }

        if (!in_array($token->type, [Token::STRING, Token::HEX, Token::BIN, Token::NUMBER])) {
            throw new \Exception("Invalid token type for default value: $token->type");
        }

        $typeInfo = $this->getTypeInfo();

        switch ($typeInfo->kind) {
            case 'bit':
                return $token->asNumber();

            case 'int':
                if ($this->zerofill) {
                    $length = $this->length;
                    return sprintf("%0{$length}d", $token->asNumber());
                } else {
                    return $token->asNumber();
                }
                // Comment to appease this phpcs rule:
                // PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                // There must be a comment when fall-through is intentional
                // in a non-empty case body

            case 'decimal':
                $decimals = is_null($this->decimals) ? 0 : $this->decimals;
                if ($this->zerofill) {
                    $length = $this->length;
                    return sprintf("%0{$length}.{$decimals}f", $token->asNumber());
                } else {
                    return sprintf("%.{$decimals}f", $token->asNumber());
                }
                // Comment to appease this phpcs rule:
                // PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                // There must be a comment when fall-through is intentional
                // in a non-empty case body

            case 'date':
                return $token->asDate();

            case 'time':
                return $token->asTime();

            case 'datetime':
                return $token->asDateTime();

            case 'year':
                $year = $token->asNumber();
                if ($token->type !== Token::STRING && $year == 0) {
                    return '0000';
                }
                if ($year < 70) {
                    return (string)round($year + 2000);
                } elseif ($year <= 99) {
                    return (string)round($year + 1900);
                } elseif (1901 <= $year && $year <= 2155) {
                    return (string)round($year);
                } else {
                    throw new \Exception("Invalid default year (1901-2155): $year");
                }
                // Comment to appease this phpcs rule:
                // PSR2.ControlStructures.SwitchDeclaration.TerminatingComment
                // There must be a comment when fall-through is intentional
                // in a non-empty case body

            case 'text':
                return $token->asString();

            case 'binary':
                return str_pad($token->asString(), $this->length, "\0");

            case 'enum':
                if ($token->type !== Token::STRING) {
                    throw new \Exception("Invalid data type for default enum value: $token->type");
                }
                foreach ($this->elements as $element) {
                    if (strtolower($token->text) === strtolower($element)) {
                        return $element;
                    }
                }
                throw new \Exception("Default enum value not found in enum: $token->text");

            case 'set':
                if ($token->type !== Token::STRING) {
                    throw new \Exception("Invalid type for default set value: $token->type");
                }
                if ($token->text === '') {
                    return '';
                }
                $defaults = explode(',', strtolower($token->text));
                foreach ($defaults as $default) {
                    $match = null;
                    foreach ($this->elements as $i => $element) {
                        if (strtolower($default) === strtolower($element)) {
                            $match = $i;
                            break;
                        }
                    }
                    if (is_null($match)) {
                        throw new \Exception("Default set value not found in set: $token->text");
                    }
                    $matches[$match] = $this->elements[$match];
                }
                ksort($matches, SORT_NUMERIC);
                return implode(',', $matches);

            default:
                throw new \Exception("This kind of data type cannot have a default value: $typeInfo->kind");
        }
    }

    /**
     * Sets the collation to the specified (table) collation if it was not
     * explicitly specified in the column definition. May modify the column's
     * type if the charset is binary.
     *
     * @param CollationInfo $tableCollation
     * @return void
     */
    public function applyTableCollation(CollationInfo $tableCollation)
    {
        if (!$this->collation->isSpecified() &&
            $tableCollation->isSpecified()
        ) {
            $this->collation->setCollation($tableCollation->getCollation());
        }

        if ($this->collation->isSpecified() &&
            $this->collation->isBinaryCharset()
        ) {
            switch ($this->type) {
                case 'char':
                    $this->type = 'binary';
                    break;
                case 'varchar':
                    $this->type = 'varbinary';
                    break;
                case 'tinytext':
                    $this->type = 'tinyblob';
                    break;
                case 'text':
                    $this->type = 'blob';
                    break;
                case 'mediumtext':
                    $this->type = 'mediumblob';
                    break;
                case 'longtext':
                    $this->type = 'longblob';
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Returns the column definition as an SQL fragment, relative to the
     * specified table collation.
     *
     * @param CollationInfo $tableCollation
     * @return string
     */
    public function toString(CollationInfo $tableCollation)
    {
        $text = Token::escapeIdentifier($this->name) . " " . $this->type;
        $typeInfo = $this->getTypeInfo();

        if ($this->length) {
            $text .= "(" . $this->length;
            if ($typeInfo->kind === 'decimal') {
                $text .= "," . $this->decimals;
            }
            $text .= ")";
        }

        if (count($this->elements) > 0) {
            $text .= "(";
            $text .= implode(',', array_map('Graze\Morphism\Parse\Token::escapeString', $this->elements));
            $text .= ")";
        }

        if ($this->unsigned) {
            $text .= " unsigned";
        }

        if ($this->zerofill) {
            $text .= " zerofill";
        }

        if ($typeInfo->allowCharset) {
            $collation = $this->collation;
            if ($collation->isSpecified()) {
                if (!$tableCollation->isSpecified() ||
                    $tableCollation->getCollation() !== $collation->getCollation()
                ) {
                    $text .= " CHARACTER SET " . $collation->getCharset();
                }
                if (!$collation->isDefaultCollation()) {
                    $text .= " COLLATE " . $collation->getCollation();
                }
            }
        }

        if ($this->nullable) {
            if ($this->type === 'timestamp') {
                $text .= " NULL";
            }
        } else {
            $text .= " NOT NULL";
        }

        if ($this->autoIncrement) {
            $text .= " AUTO_INCREMENT";
        }

        if (is_null($this->default)) {
            if ($this->nullable && $typeInfo->allowDefault) {
                $text .= " DEFAULT NULL";
            }
        } elseif (in_array($this->type, ['timestamp', 'datetime']) &&
            $this->default === 'CURRENT_TIMESTAMP'
        ) {
            $text .= " DEFAULT CURRENT_TIMESTAMP";
        } elseif ($this->type === 'bit') {
            $text .= " DEFAULT b'" . decbin($this->default) . "'";
        } else {
            $text .= " DEFAULT " . Token::escapeString($this->default);
        }

        if ($this->onUpdateCurrentTimestamp) {
            $text .= " ON UPDATE CURRENT_TIMESTAMP";
        }

        if (!is_null($this->comment)) {
            $text .= " COMMENT " . Token::escapeString($this->comment);
        }

        return $text;
    }
}
