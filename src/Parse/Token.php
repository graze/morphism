<?php
namespace Graze\Morphism\Parse;

/**
 * Represents a lexical token parsed from an SQL input stream.
 */

class Token
{
    /**
     * @var string  'symbol' | 'identifier' | 'number' | 'bin' | 'hex' |
     *              'whitespace' | 'comment' | 'conditional-start' |
     *              'conditional-end' | 'EOF'
     */
    public $type;

    /** @var string  the textual content of the token */
    public $text;

    private static $_quoteNames = false;

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
        return $this->type === 'EOF';
    }

    /**
     * Returns true if the token has the specified type and text content
     * (case insensitive).
     *
     * @param string $type  e.g. 'symbol' | 'identifier' | ...
     * @param string $text  content of token
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
     * @param string $string   string to parse
     * @param char $quoteChar  character that was used as the quote delimiter
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
                    case '0': $ch = chr(0); break;
                    case 'b': $ch = chr(8); break;
                    case 'n': $ch = chr(10); break;
                    case 'r': $ch = chr(13); break;
                    case 't': $ch = chr(9); break;
                    case 'z': $ch = chr(26); break;
                }
            }
            $text .= $ch;
        }
        return new self('string', $text);
    }

    /**
     * Creates an identifier token by parsing the given string.
     * The supplied string should exclude any backquote delimiters.
     *
     * @param string $string   string to parse
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
     * @param string|null $value - value to convert
     * @return string
     */
    public static function escapeString($value)
    {
        if (is_null($value)) {
            return 'NULL';
        }
        $value = strtr($value, [
            "'"     => "''",
            chr(0)  => "\\0",
            chr(10) => "\\n",
            chr(13) => "\\r",
            "\\"    => "\\\\",
        ]);
        return "'$value'";
    }

    /**
     * Returns an identifier suitable for use as in SQL.
     *
     * If setQuoteNames(true) has previously been called, the identifier
     * will be delimited with backquotes, otherwise no delimiters will be
     * added.
     *
     * @param string $string  text to use as the identifier name
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
     * stream * will be returned as 'ABCD'.
     *
     * An exception will be thrown for any other token type.
     *
     * @throws \RuntimeException
     * @return string
     */
    public function asString()
    {
        switch ($this->type) {
        case 'string':
            return $this->text;

        case 'number':
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

        case 'hex':
            return pack('H*', $this->text);

        case 'bin':
            $bytes = '';
            for ($text = $this->text; $text !== ''; $text = substr($text, 0, -8)) {
                $bytes = chr(bindec(substr($text, -8))) . $bytes;
            }
            return $bytes;

        default:
            throw new \RuntimeException("expected string");
        }
    }

    /**
     * Returns the
     */
    public function asNumber()
    {
        switch ($this->type) {
        case 'number':
            return 0 + $this->text;

        case 'string':
            // TODO - should check $this->text is actually a valid number
            return 0 + $this->text;

        case 'hex':
            return hexdec($this->text);

        case 'bin':
            return bindec($this->text);

        default:
            throw new \RuntimeException("expected a number");
        }
    }

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
            throw new \RuntimeException("expected a date");
        }
    }

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
            throw new \RuntimeException("expected a time");
        }
    }


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
        throw new \RuntimeException("bad datetime");
    }
}
