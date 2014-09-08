<?php
namespace Graze\Morphism\Parse;

class TokenStream
{
    private $path;
    private $text;
    private $offset = 0;
    private $inConditional = false;
    private $mark = 0;
    private $markInConditional = false;

    private function __construct()
    {
    }

    public static function newFromFile($path)
    {
        $stream = new self;
        $stream->path = $path;
        $stream->text = @file_get_contents($path);
        if ($stream->text === false) {
            throw new \RuntimeException("$path: could not open file");
        }
        return $stream;
    }

    private function nextTokenRaw()
    {
        $text = $this->text;

        list($token, $offset) = $this->_nextTokenRaw($this->text, $this->offset);

        $this->text = $text;
        $this->offset = $offset;

        return $token;
    }

    private function _nextTokenRaw($text, $offset)
    {
        // currently unsupported:
        //
        //      charset prefixed strings, e.g. _utf8'wingwang'
        //      temporal literals, e.g. DATE'2014-07-08'
        //      the null literal, i.e. \N

        // check for false, because substr('blah', strlen('blah')) is FALSE rather
        // than the empty string like any normal, sane human being would expect.
        if ($offset >= strlen($text)) {
            return [
                new Token('EOF'),
                strlen($text)
            ];
        }
        else if (
            preg_match('/\A\s/ms', substr($text, $offset, 1)) &&
            preg_match('/\s+/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token('whitespace', $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (
            substr($text, $offset, 1) === '`'
        ) {
            if (preg_match('/`((?:[^`]|``)*)`/ms', $text, $pregMatch, 0, $offset)) {
                $token = Token::fromIdentifier($pregMatch[1]);
                return [
                    $token,
                    $offset + strlen($pregMatch[0])
                ];
            }
            throw new \RuntimeException("unterminated identifier $quote...$quote");
        }
        // TODO - should probably be a new token type 'variable' for @ and @@
        else if (preg_match('/\A(?:<=|>=|<>|!=|:=|@@|&&|\|\||[=~!@%^&();:,<>])/xms', substr($text, $offset, 2), $pregMatch)) {
            return [
                new Token('symbol', $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (   
            // 10 comes from allowing for the /*! sequence, a MySQL version number, and a space
            preg_match('_\A/\*!([0-9]*)\s_ms', substr($text, $offset, 10)) &&
            preg_match('_/\*!([0-9]*)\s_ms', $text, $pregMatch, 0, $offset)
        ) {
            $this->inConditional = true;
            return [
                new Token('conditional-start', $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (substr($text, $offset, 2) === '*/') {
            if (!$this->inConditional) {
                throw new \RuntimeException("unexpected '*/'");
            }
            $this->inConditional = false;
            return [
                new Token('conditional-end'),
                $offset + 2
            ];
        }
        else if (substr($text, $offset, 2) === '/*') {
            if (($pos = strpos($text, '*/', $offset)) !== FALSE) {
                return [
                    new Token('comment', substr($text, $offset, $pos - $offset + 2)),
                    $pos + 2
                ];
            }
            throw new \RuntimeException("unterminated '/*'");
        }
        else if (preg_match('/\A(?:#|--\s)/ms', substr($text, $offset, 3))) {
            if (($pos = strpos($text, "\n", $offset)) !== FALSE) {
                return [
                    new Token('comment', substr($text, $offset, $pos - $offset)),
                    $pos + 1
                ];
            }
            return [
                new Token('comment', $text),
                strlen($text)
            ];
        }
        else if (
            substr($text, $offset, 1) === "'" ||
            substr($text, $offset, 1) === '"'
        ) {
            $quote = substr($text, $offset, 1);
            if (preg_match(
                '/' . 
                $quote . 
                '(' .
                    '(?:' .
                        '[^\\\\' . $quote . ']' . // not \ or "
                        '|\\\\.' .                // escaped character
                        '|' .  $quote . $quote .  // ""
                    ')*' .
                ')' .
                $quote .
                '/ms', $text, $pregMatch, 0, $offset)
            ) {
                $token = Token::fromString($pregMatch[1], $quote);
                return [
                    $token,
                    $offset + strlen($pregMatch[0])
                ];
            }
            throw new \RuntimeException("unterminated string $quote...$quote");
        }
        else if (
            preg_match('/\A[-+]?[.]?[0-9]/ms', substr($text, $offset, 3)) &&
            preg_match('/[-+]?(?:[0-9]+(?:[.][0-9]*)?|[.][0-9]+)(?:[eE][-+]?[0-9]+)?/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token('number', $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (
            preg_match('/\Ax\'[0-9a-f\']/ims', substr($text, $offset, 3)) &&
            preg_match('/x\'([0-9a-f]*)\'/ims', $text, $pregMatch, 0, $offset)
        ) {
            if (strlen($pregMatch[1]) % 2 != 0) {
                throw new \RuntimeException("invalid hex literal");
            }
            return [
                new Token('hex', $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (
            preg_match('/\Ab\'[01\']/ms', substr($text, $offset, 3)) &&
            preg_match('/b\'([01]*)\'/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token('bin', $pregMatch[1]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (
            preg_match('/\A[a-zA-Z0-9$_]/ms', substr($text, $offset, 1)) &&
            preg_match('/[a-zA-Z0-9$_]+/ms', $text, $pregMatch, 0, $offset)
        ) {
            return [
                new Token('identifier', $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }
        else if (preg_match('/\A(?:[-+*.\/])/xms', substr($text, $offset, 2), $pregMatch)) {
            return [
                new Token('symbol', $pregMatch[0]),
                $offset + strlen($pregMatch[0])
            ];
        }

        throw new \LogicException("lexer is confused!");
    }

    public function nextToken()
    {
        while(true) {
            $token = $this->nextTokenRaw();
            if (!in_array($token->type, [
                'conditional-start',
                'conditional-end',
                'comment',
                'whitespace',
            ])) {
                return $token;
            }
        }
    }

    public function setMark()
    {
        $this->mark = $this->offset;
        $this->markInConditional = $this->inConditional;
    }

    public function rewindToMark()
    {
        $this->offset = $this->mark;
        $this->inConditional = $this->markInConditional;
    }

    // warning! tramples mark
    public function consume($spec)
    {
        return $this->_consume($spec, false);
    }

    public function peek($spec)
    {
        return $this->_consume($spec, true);
    }

    private function _consume($spec, $peek)
    {
        if (is_string($spec)) {
            $matches = array_map(
                function($e) { return ['identifier', $e]; },
                explode(' ', $spec)
            );
        }
        else {
            $matches = $spec;
        }

        $this->setMark();

        foreach($matches as $match) {
            list($type, $text) = $match;
            $token = $this->nextToken();
            if (!$token->eq($type, $text)) {
                $this->rewindToMark();
                return false;
            }
        }

        if ($peek) {
            $this->rewindToMark();
        }

        return true;
    }

    public function expect($type, $text = null)
    {
        $token = $this->nextToken();
        if (!$token->eq($type, $text)) {
            throw new \RuntimeException("expected '$text'");
        }
    }

    public function expectName()
    {
        $token = $this->nextToken();
        if ($token->type !== 'identifier') {
            throw new \RuntimeException("expected identifier");
        }
        return $token->text;
    }

    public function expectOpenParen()
    {
        $this->expect('symbol', '(');
    }

    public function expectCloseParen()
    {
        $this->expect('symbol', ')');
    }

    public function expectNumber()
    {
        $token = $this->nextToken();
        if ($token->type !== 'number') {
            throw new \RuntimeException("expected number");
        }
        return 0 + $token->text;
    }

    public function expectString()
    {
        $token = $this->nextToken();
        if ($token->type !== 'string') {
            throw new \RuntimeException("expected string");
        }
        return $token->text;
    }

    public function expectStringExtended()
    {
        $token = $this->nextToken();
        switch($token->type) {
        case 'string':
            return $token->text;
        case 'hex':
            return $token->hexToString();
        case 'bin':
            return $token->binToString();
        default:
            throw new \RuntimeException("expected string");
        }
    }

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
        $lines = explode("\n",  $this->text);

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
        foreach($contextLines as $i => $line) {
            $context .= sprintf("\n%{$width}d: %s", $i + 1, $line);
        }

        return sprintf("%s, line %d: %s%s", $this->path, $lineNo + 1, $message, $context);
    }
}
