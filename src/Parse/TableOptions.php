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

    private $_defaultEngine = null;
    private $_defaultCollation = null;
    private $_defaultOptions = [
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
     */
    public function __construct(CollationInfo $databaseCollation)
    {
        $this->collation = new CollationInfo;
        $this->_defaultCollation = clone $databaseCollation;
    }

    /**
     * Set the default storage engine for the table to use in case the
     * ENGINE option is not supplied.
     *
     * @param string $engine e.g. 'InnoDB"
     */
    public function setDefaultEngine($engine)
    {
        $this->_defaultEngine = self::normaliseEngine($engine);
    }

    /**
     * Parses table options from $stream.
     */
    public function parse(TokenStream $stream)
    {
        $this->engine = $this->_defaultEngine;
        $this->options = $this->_defaultOptions;

        while(true) {
            $mark = $stream->getMark();
            $token = $stream->nextToken();
            if ($token->type !== 'identifier') {
                $stream->rewind($mark);
                break;
            }

            if ($token->eq('identifier', 'DEFAULT')) {
                $token = $stream->nextToken();
                if (!($token->type === 'identifier' &&
                      in_array(strtoupper($token->text), ['CHARSET', 'CHARACTER', 'COLLATE']))
                ) {
                    throw new \RuntimeException("expected CHARSET, CHARACTER SET or COLLATE");
                }
            }

            $this->_parseOption($stream, strtoupper($token->text));
        }

        if (!$this->collation->isSpecified()) {
            $this->collation = clone $this->_defaultCollation;
        }
    }

    private function _parseOption(TokenStream $stream, $option)
    {
        switch($option) {
        case 'ENGINE':
        case 'COLLATE':
        case 'CHARSET':
            $this->_parseIdentifier($stream, $option);
            break;

        case 'CHARACTER':
            $stream->expect('identifier', 'SET');
            $this->_parseIdentifier($stream, 'CHARSET');
            break;

        case 'AUTO_INCREMENT':
        case 'AVG_ROW_LENGTH':
        case 'KEY_BLOCK_SIZE':
        case 'MAX_ROWS':
        case 'MIN_ROWS':
            $this->_parseNumber($stream, $option);
            break;

        case 'CHECKSUM':
        case 'DELAY_KEY_WRITE':
            $this->_parseEnum($stream, $option, ['0', '1']);
            break;

        case 'PACK_KEYS':
            $this->_parseEnum($stream, $option, ['DEFAULT', '0', '1']);
            break;

        case 'DATA':
        case 'INDEX':
            $stream->expect('identifier', 'DIRECTORY');
            // fall through //
        case 'COMMENT':
        case 'CONNECTION':
        case 'PASSWORD':
            $this->_parseString($stream, $option);
            break;

        case 'INSERT_METHOD':
            $this->_parseEnum($stream, $option, ['NO', 'FIRST', 'LAST']);
            throw new \RuntimeException("$option is not currently supported by this tool");

        case 'ROW_FORMAT':
            $this->_parseEnum($stream, $option, ['DEFAULT', 'DYNAMIC', 'FIXED', 'COMPRESSED', 'REDUNDANT', 'COMPACT']);
            break;

        case 'STATS_SAMPLE_PAGES':
        case 'STATS_AUTO_RECALC':
        case 'STATS_PERSISTENT':
        case 'TABLESPACE':
        case 'UNION':
        case 'PARTITION':
            throw new \RuntimeException("$option is not currently supported by this tool");

        default:
            throw new \RuntimeException("unknown table option");
        }
    }

    private static function normaliseEngine($engine)
    {
        $engine = strtoupper($engine);
        switch($engine) {
            case 'INNODB':
                return 'InnoDB';
            case 'MYISAM':
                return 'MyISAM';
            default:
                return $engine;
        }
    }

    private function setOption($option, $value)
    {
        switch($option) {
        case 'ENGINE':
            $this->engine = self::normaliseEngine($value);
            break;

        case 'CHARSET':
            if (strtoupper($value) === 'DEFAULT') {
                $this->collation = new CollationInfo();
            }
            else {
                $this->collation->setCharset($value);
            }
            break;

        case 'COLLATE':
            if (strtoupper($value) === 'DEFAULT') {
                $this->collation = new CollationInfo();
            }
            else {
                $this->collation->setCollation($value);
            }
            break;

        default:
            $this->options[$option] = $value;
            break;
        }
    }

    private function _parseIdentifier(TokenStream $stream, $option)
    {
        $stream->consume([['symbol', '=']]);
        $token = $stream->nextToken();
        if ($token->isEof()) {
            throw new \RuntimeException("unexpected end-of-file");
        }
        if (!in_array($token->type, ['identifier', 'string'])) {
            throw new \RuntimeException("bad table option value");
        }
        $this->setOption($option, strtolower($token->text));
    }

    private function _parseNumber(TokenStream $stream, $option)
    {
        $stream->consume([['symbol', '=']]);
        $this->setOption($option, $stream->expectNumber());
    }

    private function _parseEnum(TokenStream $stream, $option, array $enums)
    {
        $stream->consume([['symbol', '=']]);
        $token = $stream->nextToken();
        if (!in_array($token->type, ['identifier', 'number'])) {
            throw new \RuntimeException("bad table option value");
        }
        $value = strtoupper($token->text);
        if (!in_array($value, $enums)) {
            throw new \RuntimeException("invalid option value, expected " . implode(' | ', $enums));
        }
        $this->setOption($option, $value);
    }

    private function _parseString(TokenStream $stream, $option)
    {
        $stream->consume([['symbol', '=']]);
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

        foreach([
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
            if ($this->options[$option] !== $this->_defaultOptions[$option]) {
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
     * @return string
     */
    public function diff(self $that, $flags = [])
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
                $alters[] = "DEFAULT CHARSET=" . $thatCollation->getCharset();
                if (!$that->collation->isDefaultCollation()) {
                    $alters[] = "COLLATE=" . $thatCollation->getCollation();
                }
            }
        }
                
        foreach([
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
