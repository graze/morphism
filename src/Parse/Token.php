<?php
namespace Graze\Morphism\Parse;

use RuntimeException;

/**
 * Represents a lexical token parsed from an SQL input stream.
 */
class Token
{
    const SYMBOL = 'symbol';
    const IDENTIFIER = 'identifier';
    const STRING = 'string';
    const NUMBER = 'number';
    const BIN = 'bin';
    const HEX = 'hex';
    const WHITESPACE = 'whitespace';
    const COMMENT = 'comment';
    const CONDITIONAL_START = 'conditional-start';
    const CONDITIONAL_END = 'conditional-end';
    const EOF = 'EOF';

    /**
     * @var string  'symbol' | 'identifier' | 'string' | 'number' | 'bin' |
     *              'hex' | 'whitespace' | 'comment' | 'conditional-start' |
     *              'conditional-end' | 'EOF'
     */
    public $type;

    /**
     * @var string  the textual content of the token
     */
    public $text;

    /** @var bool */
    static private $_quoteNames = false;

    /**
     * Constructor
     *
     * @param string $type
     * @param string $text
     */
    public function __construct($type, $text = '')
    {
        $this->type = $type;
        $this->text = (string) $text;
    }

    /**
     * Controls the behaviour of escapeIdentifier().
     *
     * If $quoteNames is true, future calls to escapeIdentifier will use
     * backquotes to delimit all identifiers. If false, no quoting will take
     * place.
     *
     * @param bool $quoteNames  whether to quote identifiers
     */
    public static function setQuoteNames($quoteNames)
    {
        self::$_quoteNames = !!$quoteNames;
    }

    /**
     * Returns the current state of behaviour for escapeIdentifier().
     * True indicate that identifiers will be delimited with backquotes;
     * false indicates that no quoting will take place.
     *
     * @return bool
     */
    public static function getQuoteNames()
    {
        return self::$_quoteNames;
    }

    /**
     * Returns a string representation suitable for debug output.
     * e.g. 'identifier[pr_name]'
     *
     * @return string
     */
    public function toDebugString()
    {
        return "{$this->type}[{$this->text}]";
    }

    /**
     * Returns true if this token represents end-of-file.
     *
     * @return bool
     */
    public function isEof()
    {
        return $this->type === self::EOF;
    }

    /**
     * Returns true if the token has the specified type and text content
     * (case insensitive).
     *
     * @param  string $type e.g. 'symbol' | 'identifier' | ...
     * @param  string $text content of token
     * @return bool
     */
    public function eq($type, $text)
    {
        return ($this->type === $type)
            && strcasecmp($this->text, $text) === 0;
    }

    /**
     * Creates a string token by parsing the given string.
     * The supplied string should exclude the quote delimiters.
     *
     * @param  string $string    string to parse
     * @param  string $quoteChar character that was used as the quote delimiter
     * @return Token
     */
    public static function fromString($string, $quoteChar)
    {
        $text = '';
        $n = strlen($string);
        for ($i = 0; $i < $n; ++$i) {
            $ch = $string[$i];
            if ($ch === $quoteChar) {
                ++$i;
            }
            if ($ch === '\\') {
                ++$i;
                $ch = $string[$i];
                switch ($ch) {
                    case '0':
                        $ch = chr(0);
                        break;
                    case 'b':
                        $ch = chr(8);
                        break;
                    case 'n':
                        $ch = chr(10);
                        break;
                    case 'r':
                        $ch = chr(13);
                        break;
                    case 't':
                        $ch = chr(9);
                        break;
                    case 'z':
                        $ch = chr(26);
                        break;
                }
            }
            $text .= $ch;
        }
        return new self(self::STRING, $text);
    }

    /**
     * Creates an identifier token by parsing the given string.
     * The supplied string should exclude any backquote delimiters.
     *
     * @param  string $string string to parse
     * @return Token
     */
    public static function fromIdentifier($string)
    {
        return new self('identifier', str_replace('``', '`', $string));
    }

    /**
     * Returns a quote delimited, escaped string suitable for use in SQL.
     * If $string is null, returns `NULL`.
     *
     * @param  string|null $value - value to convert
     * @return string
     */
    public static function escapeString($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        $value = strtr(
            $value,
            [
            "'"     => "''",
            chr(0)  => "\\0",
            chr(10) => "\\n",
            chr(13) => "\\r",
            "\\"    => "\\\\",
            ]
        );
        return "'$value'";
    }

    /**
     * Returns an identifier suitable for use as in SQL.
     *
     * If setQuoteNames(true) has previously been called, the identifier
     * will be delimited with backquotes, otherwise no delimiters will be
     * added.
     *
     * @param  string $string text to use as the identifier name
     * @return string
     */
    public static function escapeIdentifier($string)
    {
        if (!self::$_quoteNames) {
            return $string;
        }
        $string = strtr($string, [ "`" => "``" ]);
        return "`" . $string . "`";
    }

    /**
     * Returns the string represented by the token.
     *
     * Tokens of type 'string' or 'number' will simply return the parsed text,
     * whereas 'hex' or 'bin' tokens will be reinterpreted as strings. E.g.
     * a 'hex' token generated from the sequence x'41424344' in the token
     * stream will be returned as 'ABCD'.
     *
     * An exception will be thrown for any other token type.
     *
     * @throws RuntimeException
     * @return string
     */
    public function asString()
    {
        switch ($this->type) {
            case self::STRING:
                return $this->text;

            case self::NUMBER:
                preg_match('/^([+-]?)0*(.*)$/', $this->text, $pregMatch);
                list(, $sign, $value) = $pregMatch;
                if ($value == '') {
                    $value = '0';
                } elseif ($value[0] == '.') {
                    $value = '0' . $value;
                }
                if ($sign == '-') {
                    $value = $sign . $value;
                }
                return $value;

            case self::HEX:
                return pack('H*', $this->text);

            case self::BIN:
                $bytes = '';
                for ($text = $this->text; $text !== ''; $text = substr($text, 0, -8)) {
                    $bytes = chr(bindec(substr($text, -8))) . $bytes;
                }
                return $bytes;

            default:
                throw new RuntimeException("Expected string");
        }
    }

    /**
     * Return the token as a number.
     *
     * @return int|number
     */
    public function asNumber()
    {
        switch ($this->type) {
            case self::NUMBER:
                return 0 + $this->text;

            case self::STRING:
                // TODO - should check $this->text is actually a valid number
                return 0 + $this->text;

            case self::HEX:
                return hexdec($this->text);

            case self::BIN:
                return bindec($this->text);

            default:
                throw new RuntimeException("Expected a number");
        }
    }

    /**
     * Return the token value as a date string.
     *
     * @return string
     */
    public function asDate()
    {
        $text = $this->text;
        if ($text === '0') {
            return '0000-00-00';
        }
        // MySQL actually recognises a bunch of other date formats,
        // but YYYY-MM-DD is the only one we're prepared to accept.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text, $pregMatch)) {
            return $text;
        } else {
            throw new RuntimeException("Expected a date");
        }
    }

    /**
     * Return the token as a timestamp string.
     *
     * @return string
     */
    public function asTime()
    {
        $text = $this->text;
        if ($text === '0') {
            return '00:00:00';
        }
        // MySQL actually recognises a bunch of other time formats,
        // but HH:MM:SS is the only one we're prepared to accept.
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $text)) {
            return $text;
        } else {
            throw new RuntimeException("Expected a time");
        }
    }

    /**
     * Return the token as a date and time string.
     *
     * @return string
     */
    public function asDateTime()
    {
        $text = $this->text;
        if ($text === '0') {
            return '0000-00-00 00:00:00';
        }
        // MySQL actually recognises a bunch of other datetime formats,
        // but YYYY-MM-DD and YYYY-MM-DD HH:MM:SS are the only ones we're
        // prepared to accept.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return "$text 00:00:00";
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $text)) {
            return $text;
        }
        throw new RuntimeException("Bad datetime");
    }
}
