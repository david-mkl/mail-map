<?php

namespace MailMap;

use MailMap\Contracts\Mail as MailContract;

class Mail implements MailContract
{
    /**
     * Uid of email
     *
     * @var int
     */
    public $id;

    /**
     * The imap stream
     *
     * @var resource
     */
    protected $stream;

    /**
     * Email headers
     *
     * @var array
     */
    protected $headers;

    /**
     * Email body
     *
     * @var string
     */
    protected $body;

    /**
     * Message number
     *
     * @var int
     */
    public $msgNo;

    /**
     * Email subject
     *
     * @var string
     */
    public $subject;

    /**
     * Date in Unix
     *
     * @var int
     */
    public $date;

    /**
     * List of addresses sent to
     *
     * @var array
     */
    public $to;

    /**
     * List of addresses cc'ed
     *
     * @var array
     */
    public $cc;

    /**
     * List of addresses bcc'ed
     *
     * @var array
     */
    public $bcc;

    /**
     * List of addresses from Sender line
     *
     * @var array
     */
    public $sender;

    /**
     * List of addresses from From line
     *
     * @var array
     */
    public $from;

    /**
     * List of addresses to reply to
     *
     * @var array
     */
    public $replyTo;

    /**
     * Recent flag.
     * R if recent and seen, N if recent and not seen, ' ' if not recent.
     *
     * @var string
     */
    public $recent;

    /**
     * Unseen flag.
     * U if not seen AND not recent, ' ' if seen OR not seen and recent
     *
     * @var string
     */
    public $unseen;

    /**
     * Recent flag.
     * F if flagged, ' ' if not flagged
     *
     * @var string
     */
    public $flagged;

    /**
     * Recent flag.
     * A if answered, ' ' if unanswered
     *
     * @var string
     */
    public $answered;

    /**
     * Recent flag.
     * D if deleted, ' ' if not deleted
     *
     * @var string
     */
    public $deleted;

    /**
     * Recent flag.
     * X if draft, ' ' if not draft
     *
     * @var string
     */
    public $draft;

    /**
     * Create new Imap Mail container
     *
     * @param int $uid
     * @param resource $stream
     * @param array $mailHeader
     * @param array $headers
     * @param string $body
     */
    public function __construct($uid, $stream, array $mailHeader, array $headers, array $body)
    {
        $this->id = $uid;
        $this->stream = $stream;
        $this->headers = $headers;
        $this->body = $body;

        $this->setHeaderAttributes($mailHeader);
    }

    /**
     * Set attributes on email from header
     *
     * @param array $mailHeader
     * @return void
     */
    private function setHeaderAttributes(array $mailHeader)
    {
        $mailHeader = array_filter($mailHeader, function ($property) {
            return property_exists($this, $property);
        }, ARRAY_FILTER_USE_KEY);

        foreach ($mailHeader as $property => $header) {
            $this->{$property} = $header;
        }
    }

    /**
     * Get the underlying Imap stream
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Get a header value. Return given default if none found
     *
     * @param  string $key
     * @param  mixed $default
     * @return mixed
     */
    public function header($key, $default = null)
    {
        if (array_key_exists($key, $this->headers)) {
            return $this->headers[$key];
        }

        return $default;
    }

    /**
     * Render the email body as the given mime-type
     *
     * @param  string $mimeType
     * @return string
     */
    public function body($mimeType = 'html')
    {
        $body = [];

        foreach ($this->body as $part) {
            if ($part->mime_type === $mimeType) {
                $body[] = $part->body;
            }
        }

        return implode(static::partSeparator($mimeType), $body);
    }

    /**
     * Get the full body with all parts
     *
     * @return array
     */
    public function fullBody()
    {
        return $this->body;
    }

    /**
     * Move this message to another mailbox
     *
     * @param  string $mailbox
     * @return bool
     */
    public function move($mailbox)
    {
        return imap_mail_move($this->stream, $this->id, $mailbox, CP_UID);
    }

    /**
     * Get the part separator for the type of email being parsed
     *
     * @param  string $mimeType
     * @return string
     */
    protected static function partSeparator($mimeType)
    {
        switch (strtolower($mimeType)) {
            case 'plain':
                return str_repeat(PHP_EOL, 2);
            case 'html':
                return str_repeat('<br>', 2);
            default:
                return '';
        }
    }
}
