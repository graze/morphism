<?php
namespace Graze\Morphism\Parse;

/**
 * Represents a set of table options - MIN_ROWS, PACK_KEYS, COMMENT, etc.
 */
class TableOptions
{
    /** @var string|null */
    public $engine = null;

    /** @var CollationInfo */
    public $collation = null;

    /**
     * @var array
     * maps option names to values (string|int|null)
     */
    public $options = [];

    /** @var string */
    private $defaultEngine = null;
    /** @var CollationInfo|null */
    private $defaultCollation = null;
    /** @var array */
    private $defaultOptions = [
        'AUTO_INCREMENT'  => null,
        'MIN_ROWS'        => 0,
        'MAX_ROWS'        => 0,
        'AVG_ROW_LENGTH'  => 0,
        'PACK_KEYS'       => 'DEFAULT',
        'CHECKSUM'        => '0',
        'DELAY_KEY_WRITE' => '0',
        'ROW_FORMAT'      => 'DEFAULT',
        'KEY_BLOCK_SIZE'  => 0,
        'COMMENT'         => '',
        'CONNECTION'      => '',
    ];

    /**
     * Constructor
     * @param CollationInfo $databaseCollation
     */
    public function __construct(CollationInfo $databaseCollation)
    {
        $this->collation = new CollationInfo;
        $this->defaultCollation = clone $databaseCollation;
    }

    /**
     * Set the default storage engine for the table to use in case the
     * ENGINE option is not supplied.
     *
     * @param string $engine e.g. 'InnoDB"
     */
    public function setDefaultEngine($engine)
    {
        $this->defaultEngine = self::normaliseEngine($engine);
    }

    /**
     * Parses table options from $stream.
     * @param TokenStream $stream
     */
    public function parse(TokenStream $stream)
    {
        $this->engine = $this->defaultEngine;
        $this->options = $this->defaultOptions;

        while (true) {
            $mark = $stream->getMark();
            $token = $stream->nextToken();
            if ($token->type !== Token::IDENTIFIER) {
                $stream->rewind($mark);
                break;
            }

            if ($token->eq(Token::IDENTIFIER, 'DEFAULT')) {
                $token = $stream->nextToken();
                if (!($token->type === Token::IDENTIFIER &&
                      in_array(strtoupper($token->text), ['CHARSET', 'CHARACTER', 'COLLATE']))
                ) {
                    throw new \RuntimeException("Expected CHARSET, CHARACTER SET or COLLATE");
                }
            }

            $this->parseOption($stream, strtoupper($token->text));
        }

        if (!$this->collation->isSpecified()) {
            $this->collation = clone $this->defaultCollation;
        }
    }

    /**
     * @param TokenStream $stream
     * @param string $option
     */
    private function parseOption(TokenStream $stream, $option)
    {
        switch ($option) {
            case 'ENGINE':
            case 'COLLATE':
            case 'CHARSET':
                $this->parseIdentifier($stream, $option);
                break;

            case 'CHARACTER':
                $stream->expect(Token::IDENTIFIER, 'SET');
                $this->parseIdentifier($stream, 'CHARSET');
                break;

            case 'AUTO_INCREMENT':
            case 'AVG_ROW_LENGTH':
            case 'KEY_BLOCK_SIZE':
            case 'MAX_ROWS':
            case 'MIN_ROWS':
                $this->parseNumber($stream, $option);
                break;

            case 'CHECKSUM':
            case 'DELAY_KEY_WRITE':
                $this->parseEnum($stream, $option, ['0', '1']);
                break;

            case 'PACK_KEYS':
                $this->parseEnum($stream, $option, ['DEFAULT', '0', '1']);
                break;

            case 'DATA':
            case 'INDEX':
                $stream->expect(Token::IDENTIFIER, 'DIRECTORY');
                // fall through //
            case 'COMMENT':
            case 'CONNECTION':
            case 'PASSWORD':
                $this->parseString($stream, $option);
                break;

            case 'INSERT_METHOD':
                $this->parseEnum($stream, $option, ['NO', 'FIRST', 'LAST']);
                throw new \RuntimeException("$option is not currently supported by this tool");

            case 'ROW_FORMAT':
                $this->parseEnum($stream, $option, ['DEFAULT', 'DYNAMIC', 'FIXED', 'COMPRESSED', 'REDUNDANT', 'COMPACT']);
                break;

            case 'PARTITION':
            case 'STATS_AUTO_RECALC':
            case 'STATS_PERSISTENT':
            case 'STATS_SAMPLE_PAGES':
            case 'TABLESPACE':
            case 'UNION':
                throw new \RuntimeException("$option is not currently supported by this tool");

            default:
                throw new \RuntimeException("Unknown table option: $option");
        }
    }

    /**
     * @param string $engine
     * @return string
     */
    private static function normaliseEngine($engine)
    {
        $engine = strtoupper($engine);
        switch ($engine) {
            case 'INNODB':
                return 'InnoDB';
            case 'MYISAM':
                return 'MyISAM';
            default:
                return $engine;
        }
    }

    /**
     * @param string $option
     * @param string $value
     */
    private function setOption($option, $value)
    {
        switch ($option) {
            case 'ENGINE':
                $this->engine = self::normaliseEngine($value);
                break;

            case 'CHARSET':
                if (strtoupper($value) === 'DEFAULT') {
                    $this->collation = new CollationInfo();
                } else {
                    $this->collation->setCharset($value);
                }
                break;

            case 'COLLATE':
                if (strtoupper($value) === 'DEFAULT') {
                    $this->collation = new CollationInfo();
                } else {
                    $this->collation->setCollation($value);
                }
                break;

            default:
                $this->options[$option] = $value;
                break;
        }
    }

    /**
     * @param TokenStream $stream
     * @param string $option
     */
    private function parseIdentifier(TokenStream $stream, $option)
    {
        $stream->consume([[Token::SYMBOL, '=']]);
        $token = $stream->nextToken();
        if ($token->isEof()) {
            throw new \RuntimeException("Unexpected end-of-file");
        }
        if (!in_array($token->type, [Token::IDENTIFIER, Token::STRING])) {
            throw new \RuntimeException("Bad table option value: '$token->text'");
        }
        $this->setOption($option, strtolower($token->text));
    }

    /**
     * @param TokenStream $stream
     * @param string $option
     */
    private function parseNumber(TokenStream $stream, $option)
    {
        $stream->consume([[Token::SYMBOL, '=']]);
        $this->setOption($option, $stream->expectNumber());
    }

    /**
     * @param TokenStream $stream
     * @param string $option
     * @param array $enums
     */
    private function parseEnum(TokenStream $stream, $option, array $enums)
    {
        $stream->consume([[Token::SYMBOL, '=']]);
        $token = $stream->nextToken();
        if (!in_array($token->type, [Token::IDENTIFIER, Token::NUMBER])) {
            throw new \RuntimeException("Bad table option value");
        }
        $value = strtoupper($token->text);
        if (!in_array($value, $enums)) {
            throw new \RuntimeException("Invalid option value, expected " . implode(' | ', $enums));
        }
        $this->setOption($option, $value);
    }

    /**
     * @param TokenStream $stream
     * @param string $option
     */
    private function parseString(TokenStream $stream, $option)
    {
        $stream->consume([[Token::SYMBOL, '=']]);
        $this->setOption($option, $stream->expectString());
    }

    /**
     * Returns an SQL fragment to set the options as part of a CREATE TABLE statement.
     * Note that the AUTO_INCREMENT option is explicitly *not* included in the output.
     */
    public function toString()
    {
        $items = [];

        $items[] = "ENGINE=" . $this->engine;

        // (omit AUTO_INCREMENT)

        $collation = $this->collation;
        if ($collation->isSpecified()) {
            $items[] = "DEFAULT CHARSET=" . $collation->getCharset();
            if (!$collation->isDefaultCollation()) {
                $items[] = "COLLATE=" . $collation->getCollation();
            }
        }

        foreach ([
            'MIN_ROWS',
            'MAX_ROWS',
            'AVG_ROW_LENGTH',
            'PACK_KEYS',
            'CHECKSUM',
            'DELAY_KEY_WRITE',
            'ROW_FORMAT',
            'KEY_BLOCK_SIZE',
            'COMMENT',
            'CONNECTION',
        ] as $option) {
            if ($this->options[$option] !== $this->defaultOptions[$option]) {
                $value = $this->options[$option];
                if (in_array($option, ['COMMENT', 'CONNECTION'])) {
                    $value = Token::escapeString($value);
                }
                $items[] = "$option=$value";
            }
        }

        return implode(' ', $items);
    }

    /**
     * Returns an SQL fragment to transform these table options into those
     * specified by $that as part of an ALTER TABLE statement.
     *
     * The empty string is returned if nothing needs to be done.
     *
     * $flags           |
     * :----------------|
     * 'alterEngine'    | (bool) include 'ALTER TABLE ... ENGINE=' [default: true]
     *
     * @param TableOptions $that
     * @param array $flags
     * @return string
     */
    public function diff(TableOptions $that, array $flags = [])
    {
        $flags += [
            'alterEngine' => true,
        ];

        $alters = [];
        if ($flags['alterEngine']) {
            if (strcasecmp($this->engine, $that->engine) !== 0) {
                $alters[] = "ENGINE=" . $that->engine;
            }
        }

        $thisCollation = $this->collation->isSpecified()
            ? $this->collation->getCollation()
            : null;
        $thatCollation = $that->collation->isSpecified()
            ? $that->collation->getCollation()
            : null;
        if ($thisCollation !== $thatCollation) {
            // TODO - what if !$that->collation->isSpecified()
            if (!is_null($thatCollation)) {
                $alters[] = "DEFAULT CHARSET=" . $that->collation->getCharset();
                if (!$that->collation->isDefaultCollation()) {
                    $alters[] = "COLLATE=" . $thatCollation;
                }
            }
        }

        foreach ([
            'MIN_ROWS',
            'MAX_ROWS',
            'AVG_ROW_LENGTH',
            'PACK_KEYS',
            'CHECKSUM',
            'DELAY_KEY_WRITE',

            // The storage engine may pick a different row format when
            // ROW_FORMAT=DEFAULT (or no ROW_FORMAT)/ is specified, depending
            // on whether any variable length columns are present. Since we
            // don't (currently) explicitly specify ROW_FORMAT in any of our
            // tables, I'm choosing to ignore it for the time being...
        //  'ROW_FORMAT',
            'KEY_BLOCK_SIZE',
            'COMMENT',
            'CONNECTION',
        ] as $option) {
            $thisValue = $this->options[$option];
            $thatValue = $that->options[$option];
            if (in_array($option, ['COMMENT', 'CONNECTION'])) {
                $thisValue = Token::escapeString($thisValue);
                $thatValue = Token::escapeString($thatValue);
            }

            if ($thisValue !== $thatValue) {
                $alters[] = "$option=$thatValue";
            }
        }

        return implode(' ', $alters);
    }
}
