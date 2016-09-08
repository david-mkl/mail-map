<?php

namespace MailMap;

use InvalidArgumentException;
use MailMap\Contracts\MailFactory as FactoryContract;
use MailMap\Contracts\Mail as MailContract;
use MailMap\Mail;

class MailFactory implements FactoryContract
{
    /**
     * Default charset
     *
     * @var string
     */
    protected static $charset = 'UTF-8';

    /**
     * Flags to set when fetching headers/structure etc
     *
     * @var int Bit-mask
     */
    protected static $fetchFlags = FT_UID;

    /**
     * Flags to set when fetching parts of mail body
     *
     * @var int Bit-mask
     */
    protected static $bodyFlags = FT_UID | FT_PEEK;

    /**
     * The type of Mail wrapper this factory creates.
     *
     * Must be an implementation of \MailMap\Contracts\Mail
     *
     * @var string
     */
    private $mailClass;

    /**
     * Set the Mail wrapper class. Defaults to
     * the provided Mail implementation
     *
     * @param string $mailClass
     * @throws \InvalidArgumentException
     */
     public function __construct($mailClass = Mail::class)
     {
         if (!is_subclass_of($mailClass, MailContract::class)) {
             throw new InvalidArgumentException(sprintf('%s must implement %s', $mailClass, MailContract::class));
         }

         $this->mailClass = $mailClass;
     }

    /**
     * Create new mail from uid
     *
     * @param  int $uid
     * @param  resource $stream
     * @return \MailMap\Contracts\Mail
     */
    public function create($uid, $stream)
    {
        return new $this->mailClass(
            $uid,
            $stream,
            $this->parseMailHeader($stream, $uid),
            $this->parseHeaders($stream, $uid),
            $this->parseBody($stream, $uid)
        );
    }

    /**
     * Parse the mail header
     *
     * @param  resource $stream
     * @param  int $uid
     * @return array
     */
    protected function parseMailHeader($stream, $uid)
    {
        $header = imap_headerinfo($stream, imap_msgno($stream, $uid));

        return [
            'msgNo' => (int) $header->Msgno,
            'subject' => $this->parseSubject($header->subject),
            'date' => $header->udate,
            'to' => isset($header->to)
                ? $this->parseAddress($header->to) : null,
            'cc' => isset($header->cc)
                ? $this->parseAddress($header->cc) : null,
            'bcc' => isset($header->bcc)
                ? $this->parseAddress($header->bcc) : null,
            'sender' => isset($header->sender)
                ? $this->parseAddress($header->sender) : null,
            'from' => isset($header->from)
                ? $this->parseAddress($header->from) : null,
            'replyTo' => isset($header->reply_to)
                ? $this->parseAddress($header->reply_to) : null,
            'recent' => $this->parseFlag('R', $header->Recent),
            'unseen' => $this->parseFlag('U', $header->Unseen),
            'flagged' => $this->parseFlag('F', $header->Flagged),
            'answered' => $this->parseFlag('A', $header->Answered),
            'deleted' => $this->parseFlag('D', $header->Deleted),
            'draft' => $this->parseFlag('X', $header->Draft)
        ];
    }

    /**
     * Parse email addresses from header
     *
     * @param  array $addresses
     * @return array
     */
    protected function parseAddress(array $addresses)
    {
        return array_map(function ($address) {
            return (object) [
                'address' => $address->mailbox.'@'.$address->host,
                'name' => isset($address->personal)
                    ? static::convertBodyEncoding($address->personal)
                    : null
            ];
        }, array_filter($addresses, function ($address) {
            return property_exists($address, 'mailbox') && strtolower($address->mailbox) !== 'undisclosed-recipients';
        }));
    }

    /**
     * Check if given flag is set
     *
     * @param  string $flagChar
     * @param  string $flag
     * @return bool
     */
    protected function parseFlag($flagChar, $flag)
    {
        return strtoupper($flagChar) === strtoupper($flag);
    }

    /**
     * Parse the subject of the email
     *
     * @param  string $subject
     * @return string
     */
    protected function parseSubject($subject)
    {
        $subject = imap_mime_header_decode($subject);

        $subjectParts = array_map(function ($subj) {
            if (strtolower($subj->charset) === 'default') {
                return $subj->text;
            }
            return iconv($subj->charset, static::$charset, $subj->text);
        }, $subject);

        return implode('', $subjectParts);
    }

    /**
     * Parse the email headers into array structure
     *
     * @param  resource $stream
     * @param  int $uid
     * @return array
     */
    protected function parseHeaders($stream, $uid)
    {
        $rawHeaders = imap_fetchheader($stream, $uid, static::$fetchFlags);

        $headers = [];
        $key = null;

        foreach (explode("\n", $rawHeaders) as $line) {
            // Test if continutation
            if ($line !== '' && !preg_match('/^\s/', $line)) {
                list($key, $val) = explode(':', $line, 2);
                $headers[$key] = trim($val);
            } elseif (!is_null($key) && ($trimmed = trim($line)) !== '') {
                $headers[$key] = $headers[$key].$trimmed;
            }
        }

        foreach($headers as $headerKey => $headerValue) {
            if (preg_match('/(\w+)=(\S+);(?:\s|$)/', $headerValue)) {
                $headers[$headerKey] = $this->parseNestedKeyPairHeader($headerValue);
            }
        }

        return $headers;
    }

    /**
     * Parse the any nested key-pair values in the header value
     *
     * @param  string $nestedPairs
     * @return array
     */
    protected function parseNestedKeyPairHeader($nestedPairs)
    {
        return array_reduce(explode(';', $nestedPairs), function($result, $pair) {
            $keyVal = explode('=', $pair, 2);
            $key = trim(array_shift($keyVal));
            $val = trim(implode('=', $keyVal));
            if ($key !== '' && $val !== '') {
                $result[$key] = $val;
            }
            return $result;
        }, []);
    }

    /**
     * Parse the email body and its parts
     *
     * @param  resource $stream
     * @param  int $uid
     * @return array
     */
    protected function parseBody($stream, $uid)
    {
        $struct = imap_fetchstructure($stream, $uid, static::$fetchFlags);

        if (!isset($struct->parts)) {
            return array_filter([$this->parsePart($stream, $uid, $struct)]);
        }

        $parsedParts = [];
        foreach ($this->flattenParts($struct->parts) as $partNo => $part) {
            $parsedParts[] = $this->parsePart($stream, $uid, $part, $partNo);
        }
        return array_filter($parsedParts);
    }

    /**
     * Flatten email structure recursively.
     *
     * Parts re-keyed as 1, 1.1, 2, 2.1, 2.1.1 etc
     *
     * @param  array $parts
     * @param  array $flattened
     * @param  string $pre
     * @param  int $idx
     * @param  bool $full
     * @return array
     */
    protected function flattenParts(array $parts, array $flattened = [], $pre = '', $idx = 1, $full = true)
    {
        foreach ($parts as $part) {
            $key = $pre.$idx;
            $flattened[$key] = $part;
            if (isset($part->parts)) {
    			if ($part->type == TYPEMULTIPART) {
    				$flattened = $this->flattenParts($part->parts, $flattened, $key.'.', 1, false);
    			} elseif ($full) {
    				$flattened = $this->flattenParts($part->parts, $flattened, $key.'.');
    			} else {
    				$flattened = $this->flattenParts($part->parts, $flattened, $pre);
    			}
    			unset($flattened[$key]->parts);
    		}
    		$idx++;
        }

        return $flattened;
    }

    /**
     * Parse individual part of the structure
     *
     * @param  resource $stream
     * @param  int $uid
     * @param  stdClass $part
     * @param  int $partNo
     * @return stdClass | null
     */
    protected function parsePart($stream, $uid, $part, $partNo = 1)
    {
        if (!in_array($part->type, [TYPETEXT, TYPEMULTIPART])) {
            return null;
        }

        $body = imap_fetchbody($stream, $uid, $partNo, static::$bodyFlags);

        $body = static::decode($body, $part->encoding);
        if ('' !== ($charset = $this->findCharset($part))) {
            $body = static::convertBodyEncoding($body, $charset, $part->encoding);
        }

        return (object) [
            'body' => $body,
            'mime_type' => strtolower($part->subtype)
        ];
    }

    /**
     * Decode the body text
     *
     * @param  string $body
     * @param  int $encoding
     * @return string
     */
    protected static function decode($body, $encoding)
    {
        if (ENCQUOTEDPRINTABLE === $encoding) {
            return quoted_printable_decode($body);
        }

        if (ENCBASE64 === $encoding) {
            return base64_decode($body);
        }

        return $body;
    }

    /**
     * Convert body encoding from given coding to default
     *
     * @param  string $body
     * @param  string $charset
     * @param  int $encoding
     * @return string
     */
    protected static function convertBodyEncoding($body, $charset = '', $encoding = -1)
    {
        if ('' === $charset) {
            $charset = static::$charset;
        }

        if (!mb_check_encoding($body, $charset)) {
            $charset = mb_detect_encoding($body);
        }

        if ($charset === static::$charset) {
            return $body;
        }

        if (!in_array($charset, mb_list_encodings())) {
            $charset = $encoding === ENC7BIT ? 'US-ASCII' : static::$charset;
        }

        return mb_convert_encoding($body, static::$charset, $charset);
    }

    /**
     * Locate the charset within the struct
     *
     * @param  stdClass $struct
     * @return string
     */
    protected function findCharset($struct)
    {
        if (isset($struct->parameters)) {
            foreach ($struct->parameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    return strtoupper($param->value);
                }
            }
        }
        if (isset($struct->dparameters)) {
            foreach ($struct->dparameters as $param) {
                if (strtolower($param->attribute) === 'charset') {
                    return strtoupper($param->value);
                }
            }
        }

        return '';
    }
}
