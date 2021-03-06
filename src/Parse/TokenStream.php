<?php
namespace Graze\Morphism\Parse;

use LogicException;
use RuntimeException;

class TokenStream
{
    /** @var string */
    private $path;
    /** @var string */
    private $text;
    /** @var int */
    private $len;
    /** @var int */
    private $offset = 0;
    /** @var bool */
    private $inConditional = false;
    /** @var array */
    private $memo = [];

    /** @var array */
    private static $skipTokenTypes = [
        Token::CONDITIONAL_START => true,
        Token::CONDITIONAL_END   => true,
        Token::COMMENT           => true,
        Token::WHITESPACE        => true,
    ];

    private function __construct()
    {
    }

    // TODO - this is a hack that needs to be refactored
    // perhaps supply a LineStream interface that is satisfied
    // by a FileLineStream or ConnectionLineStream for example?
    /**
     * @param string $text
     * @param string $label
     * @return TokenStream
     */
    public static function newFromText($text, $label)
    {
        $stream = new self;
        $stream->path = $label;
        $stream->text = $text;
        $stream->len = strlen($text);
        return $stream;
    }

    /**
     * @param string $path
     * @return TokenStream
     */
    public static function newFromFile($path)
    {
        $text = @file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException("$path: could not open file");
        }
        $stream = new self;
        $stream->path = $path;
        $stream->text = $text;
        $stream->len = strlen($text);
        return $stream;
    }

    /**
     * @return Token|mixed
     */
    private function nextTokenRaw()
    {
        $startOffset = $this->offset;
        if (isset($this->memo[$startOffset])) {
            $entry = $this->memo[$startOffset];
            $this->offset = $entry[0];
            $this->inConditional = $entry[1];
            return $entry[2];
        }

        if ($this->offset >= $this->len) {
            $token = new Token(Token::EOF);
            $this->offset = $this->len;
        } else {
            list($token, $offset) = $this->getNextTokenRaw($this->text, $this->offset);
            $this->offset = $offset;
        }

        $this->memo[$startOffset] = [
            $this->offset,
            $this->inConditional,
            $token,
        ];

        return $token;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getNextTokenRaw($text, $offset)
    {
        // currently unsupported:
        //
        //      charset prefixed strings, e.g. _utf8'wingwang'
        //      temporal literals, e.g. DATE'2014-07-08'
        //      the null literal, i.e. \N

        switch ($text[$offset]) {
            case " ":
            case "\n":
            case "\r":
            case "\t":
                $n = strspn($text, " \n\r\t", $offset);
                return [
                    new Token(Token::WHITESPACE, substr($text, $offset, $n)),
                    $offset + $n
                ];

            case '#':
                return
                    $this->getComment($text, $offset);

            case '.':
            case '+':
                return
                    $this->getNumber($text, $offset) ?:
                    $this->getSymbol($text, $offset);

            case '-':
                return
                    $this->getComment($text, $offset) ?:
                    $this->getNumber($text, $offset) ?:
                    $this->getSymbol($text, $offset);

            case '*':
                return
                    $this->getConditionEnd($text, $offset) ?:
                    $this->getSymbol($text, $offset);

            case '/':
                return
                    $this->getConditionalStart($text, $offset) ?:
                    $this->getMultilineComment($text, $offset) ?:
                    $this->getSymbol($text, $offset);

            case '0':
                // Handle hex if needed
                if (isset($text[$offset+1]) && $text[$offset+1] === 'x') {
                    return
                        $this->getHex($text, $offset) ?:
                        $this->getIdentifier($text, $offset);
                }
                // Handle non-hex leading zero.
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                return
                    $this->getNumber($text, $offset) ?:
                    $this->getIdentifier($text, $offset);

            case '"':
            case "'":
                return
                    $this->getString($text, $offset);

            case '`':
                return
                    $this->getQuotedIdentifier($text, $offset);

            case 'B':
            case 'b':
                return
                    $this->getBin($text, $offset) ?:
                    $this->getIdentifier($text, $offset);

            case 'X':
            case 'x':
                return
                    $this->getHex($text, $offset) ?:
                    $this->getIdentifier($text, $offset);

            case '$':
            case '_':
            case 'A':
            case 'a':
            case 'C':
            case 'c':
            case 'D':
            case 'd':
            case 'E':
            case 'e':
            case 'F':
            case 'f':
            case 'G':
            case 'g':
            case 'H':
            case 'h':
            case 'I':
            case 'i':
            case 'J':
            case 'j':
            case 'K':
            case 'k':
            case 'L':
            case 'l':
            case 'M':
            case 'm':
            case 'N':
            case 'n':
            case 'O':
            case 'o':
            case 'P':
            case 'p':
            case 'Q':
            case 'q':
            case 'R':
            case 'r':
            case 'S':
            case 's':
            case 'T':
            case 't':
            case 'U':
            case 'u':
            case 'V':
            case 'v':
            case 'W':
            case 'w':
            case 'Y':
            case 'y':
            case 'Z':
            case 'z':
                return
                    $this->getIdentifier($text, $offset);

            case '!':
            case '%':
            case '&':
            case '(':
            case ')':
            case ',':
            case ':':
            case ';':
            case '<':
            case '=':
            case '>':
            case '@':
            case '^':
            case '|':
            case '~':
                return
                    $this->getSpecialSymbol($text, $offset);

            case '?':
            case '[':
            case '\\':
            case ']':
            case '{':
            case '}':
            default:
                $ch = $text[$offset];
                throw new LogicException("Lexer is confused by char '$ch' ord " . ord($ch));
        }
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array
     */
    private function getQuotedIdentifier($text, $offset)
    {
        if (preg_match('/`((?:[^`]|``)*)`()/ms', $text, $pregMatch, PREG_OFFSET_CAPTURE, $offset)) {
            $token = Token::fromIdentifier($pregMatch[1][0]);
            return [
                $token,
                $pregMatch[2][1]
            ];
        }
        throw new RuntimeException("Unterminated identifier: $text");
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array
     */
    private function getSpecialSymbol($text, $offset)
    {
        // TODO - should probably be a new token type 'variable' for @ and @@
        preg_match('/\A(?:<=|>=|<>|!=|:=|@@|&&|\|\||[=~!@%^&();:,<>|])()/xms', substr($text, $offset, 2), $pregMatch, PREG_OFFSET_CAPTURE);
        return [
            new Token(Token::SYMBOL, $pregMatch[0][0]),
            $offset + $pregMatch[1][1]
        ];
    }

    /**
     * Get the start of a conditional comment.
     *
     * https://dev.mysql.com/doc/refman/5.7/en/comments.html
     *
     * Does not support optimiser hints. See examples below.
     *
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getConditionalStart($text, $offset)
    {
        // Example conditional comments which can't be displayed in the docblock because they clash:
        // - /*! MySQL-specific code */ (execute the given code)
        // - /*!12345 MySQL-specific code */ (execute the given code only if the version matches)
        // Unsupported:
        // - SELECT /*+ BKA(t1) */ FROM ... ;

        if (// 10 comes from allowing for the /*! sequence, a MySQL version number, and a space
            preg_match('_\A/\*!([0-9]*)\s_ms', substr($text, $offset, 10)) &&
            preg_match('_/\*!([0-9]*)\s_ms', $text, $pregMatch, 0, $offset)
        ) {
            $this->inConditional = true;
            return [
                new Token(Token::CONDITIONAL_START, $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        return null;
    }

    /**
     * Get the end of a conditional comment. See _getConditionStart() for details.
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getConditionEnd($text, $offset)
    {
        if (substr($text, $offset, 2) === '*/') {
            if (!$this->inConditional) {
                throw new RuntimeException("Unexpected '*/'");
            }
            $this->inConditional = false;
            return [
                new Token(Token::CONDITIONAL_END),
                $offset + 2
            ];
        }
        return null;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getMultilineComment($text, $offset)
    {
        if (substr($text, $offset, 2) === '/*') {
            $pos = strpos($text, '*/', $offset);
            if ($pos !== false) {
                return [
                    new Token(Token::COMMENT, substr($text, $offset, $pos - $offset + 2)),
                    $pos + 2
                ];
            }
            throw new RuntimeException("Unterminated '/*'");
        }
        return null;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getComment($text, $offset)
    {
        if (preg_match('/\A(?:#|--\s)/ms', substr($text, $offset, 3))) {
            $pos = strpos($text, "\n", $offset);
            if ($pos !== false) {
                return [
                    new Token(Token::COMMENT, substr($text, $offset, $pos - $offset)),
                    $pos + 1
                ];
            }
            return [
                new Token(Token::COMMENT, $text),
                strlen($text)
            ];
        }
        return null;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array
     */
    private function getString($text, $offset)
    {
        $quote = $text[$offset];
        if (preg_match(
            '/' .
            $quote .
            '(' .
                '(?:' .
                    '[^\\\\' . $quote . ']' .   // not \ or "
                    '|\\\\.' .                  // escaped quotearacter
                    '|' .  $quote . $quote .    // ""
                ')*' .
            ')' .
            $quote .
            '/ms',
            $text,
            $pregMatch,
            0,
            $offset
        )
        ) {
            $token = Token::fromString($pregMatch[1], $quote);
            return [
                $token,
                $offset + strlen($pregMatch[0])
            ];
        }
        throw new RuntimeException("Unterminated string $quote...$quote");
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getNumber($text, $offset)
    {
        if (preg_match('/\A[-+]?[.]?[0-9]/ms', substr($text, $offset, 3)) &&
            preg_match('/[-+]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+)(?:[eE][-+]?[0-9]+)?/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token(Token::NUMBER, $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }
        return null;
    }

    /**
     * Parse a hex string of the form "0x<hex digits>" or "x'<hex digits>'".
     *
     * https://dev.mysql.com/doc/refman/5.7/en/hexadecimal-literals.html
     *
     * - Only an even number of digits is valid.
     * - Case insensitive for hex digits.
     * - Case insensitive 'x' in quoted notation.
     * - Case sensitive 'x' for leading zero notation.
     *
     * Valid examples:
     * - x'BA5EBA11'
     * - x'decea5ed'
     * - X'5eed'
     * - 0xb01dface
     * - 0xBADC0DED
     *
     * Invalid examples
     * - x'00f' (odd number of digits)
     * - x'gg'  (invalid hex character)
     * - 0XFFFF (upper case 'x')
     *
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getHex($text, $offset)
    {
        $pregMatch = [];

        $matchesLeadingZeroNotation = function ($text, $offset, &$pregMatch) {
            return
                preg_match('/\A0x([0-9a-fA-F]*)/ms', $text, $pregMatch, 0, $offset);
        };

        $matchesXQuotedNotation = function ($text, $offset, &$pregMatch) {
            return
                preg_match('/\Ax\'[0-9a-f\']/ims', substr($text, $offset, 3)) &&
                preg_match('/x\'([0-9a-f]*)\'/ims', $text, $pregMatch, 0, $offset);
        };

        if ($matchesLeadingZeroNotation($text, $offset, $pregMatch) ||
            $matchesXQuotedNotation($text, $offset, $pregMatch)) {
            if (strlen($pregMatch[1]) % 2 != 0) {
                throw new RuntimeException("Invalid hex literal");
            }
            return [
                new Token(Token::HEX, $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        return null;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array|null
     */
    private function getBin($text, $offset)
    {
        if (preg_match('/\Ab\'[01\']/ms', substr($text, $offset, 3)) &&
            preg_match('/b\'([01]*)\'/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token(Token::BIN, $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        return null;
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array
     */
    private function getIdentifier($text, $offset)
    {
        preg_match('/[a-zA-Z0-9$_]+()/ms', $text, $pregMatch, PREG_OFFSET_CAPTURE, $offset);
        return [
            new Token(Token::IDENTIFIER, $pregMatch[0][0]),
            $pregMatch[1][1]
        ];
    }

    /**
     * @param string $text
     * @param int $offset
     * @return array
     */
    private function getSymbol($text, $offset)
    {
        if (preg_match('/\A(?:[-+*.\/])/xms', substr($text, $offset, 2), $pregMatch)) {
            return [
                new Token(Token::SYMBOL, $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }

        return [];
    }

    /**
     * @return Token|mixed
     */
    public function nextToken()
    {
        while (true) {
            $token = $this->nextTokenRaw();
            if (!isset(self::$skipTokenTypes[$token->type])) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return object
     */
    public function getMark()
    {
        return (object)[
            'offset'        => $this->offset,
            'inConditional' => $this->inConditional,
        ];
    }

    /**
     * @param mixed $mark
     */
    public function rewind($mark)
    {
        $this->offset        = $mark->offset;
        $this->inConditional = $mark->inConditional;
    }

    /**
     * This function will consume the requested content from the stream without trying to parse and tokenise it.
     * It is used by {@see peek()}.
     *
     * @param mixed $spec
     * @return bool
     */
    public function consume($spec)
    {
        // inline getMark()
        $markOffset        = $this->offset;
        $markInConditional = $this->inConditional;

        if (is_string($spec)) {
            foreach (explode(' ', $spec) as $text) {
                $token = $this->nextToken();
                // inline $token->eq(...)
                if (strcasecmp($token->text, $text) !== 0 ||
                    $token->type !== Token::IDENTIFIER
                ) {
                    // inline rewind()
                    $this->offset        = $markOffset;
                    $this->inConditional = $markInConditional;
                    return false;
                }
            }
        } else {
            foreach ($spec as $match) {
                list($type, $text) = $match;
                $token = $this->nextToken();
                // inline $token->eq(...)
                if (strcasecmp($token->text, $text) !== 0 ||
                    $token->type !== $type
                ) {
                    // inline rewind()
                    $this->offset        = $markOffset;
                    $this->inConditional = $markInConditional;
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param mixed $spec
     * @return bool
     */
    public function peek($spec)
    {
        // inline getMark()
        $markOffset        = $this->offset;
        $markInConditional = $this->inConditional;

        $result = $this->consume($spec);

        // inline rewind()
        $this->offset        = $markOffset;
        $this->inConditional = $markInConditional;

        return $result;
    }

    /**
     * @param string $type
     * @param string $text
     * @return string
     */
    public function expect($type, $text = null)
    {
        $token = $this->nextToken();
        if (!$token->eq($type, $text)) {
            throw new RuntimeException("Expected '$text'");
        }
        return $token->text;
    }

    /**
     * @return string
     */
    public function expectName()
    {
        $token = $this->nextToken();
        if ($token->type !== Token::IDENTIFIER) {
            throw new RuntimeException("Expected identifier");
        }
        return $token->text;
    }

    /**
     * @return string
     */
    public function expectOpenParen()
    {
        return $this->expect(Token::SYMBOL, '(');
    }

    /**
     * @return string
     */
    public function expectCloseParen()
    {
        return $this->expect(Token::SYMBOL, ')');
    }

    /**
     * @return int
     */
    public function expectNumber()
    {
        $token = $this->nextToken();
        if ($token->type !== Token::NUMBER) {
            throw new RuntimeException("Expected number");
        }
        return 1 * $token->text;
    }

    /**
     * @return string
     */
    public function expectString()
    {
        $token = $this->nextToken();
        if ($token->type !== Token::STRING) {
            throw new RuntimeException("Expected string");
        }
        return $token->text;
    }

    /**
     * @return string
     */
    public function expectStringExtended()
    {
        $token = $this->nextToken();
        switch ($token->type) {
            case Token::STRING:
                return $token->text;
            case Token::HEX:
                return $token->asString();
            case Token::BIN:
                return $token->asString();
            default:
                throw new RuntimeException("Expected string");
        }
    }

    /**
     * Provides context for error messages.
     *
     * For example, given this invalid table definition ...
     *
     *     CREATE TABLE `foo` (
     *         `a` bar DEFAULT NULL
     *     );
     *
     * ... this function will produce something like this:
     *
     *     schema/morphism test/foo.sql, line 2: unknown datatype 'bar'
     *     1: CREATE TABLE `foo` (
     *     2:   `a` bar<<HERE>> DEFAULT NULL
     *
     * @param string $message
     * @return string
     */
    public function contextualise($message)
    {
        $preContextLines = 4;
        $postContextLines = 0;

        // get position of eol strictly before offset
        $prevEolPos = strrpos($this->text, "\n", $this->offset - strlen($this->text) - 1);
        $prevEolPos = ($prevEolPos === false) ? -1 : $prevEolPos;

        // get position of eol on or after offset
        $nextEolPos = strpos($this->text, "\n", $this->offset);
        $nextEolPos = ($nextEolPos === false) ? strlen($this->text) : $nextEolPos;

        // count number of newlines up to but not including offset
        $lineNo = substr_count($this->text, "\n", 0, $this->offset);
        $lines = explode("\n", $this->text);

        $contextLines = array_slice(
            $lines,
            max(0, $lineNo - $preContextLines),
            min($lineNo, $preContextLines),
            true // preserve keys
        );
        $contextLines += [
            $lineNo =>
                substr($this->text, $prevEolPos + 1, $this->offset - ($prevEolPos + 1)) .
                "<<HERE>>".
                substr($this->text, $this->offset, $nextEolPos - $this->offset)
        ];
        $contextLines += array_slice(
            $lines,
            $lineNo + 1,
            $postContextLines,
            true // preserve keys
        );

        $context = '';
        $width = strlen($lineNo + 1 + $postContextLines);
        foreach ($contextLines as $i => $line) {
            $context .= sprintf("\n%{$width}d: %s", $i + 1, $line);
        }

        return sprintf("%s, line %d: %s%s", $this->path, $lineNo + 1, $message, $context);
    }
}
