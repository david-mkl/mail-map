<?php

namespace MailMap;

use MailMap\Contracts\Connection as ConnectionContract;

class Connection implements ConnectionContract
{
    /**
     * Imap stream from http://php.net/manual/en/function.imap-open.php
     *
     * @var resource
     */
    protected $stream;

    /**
     * Construct connection from imap stream
     *
     * @param resource $stream
     */
    public function __construct($stream)
    {
        $this->stream = $stream;
    }

    /**
     * Get the imap stream on the connection
     *
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Search mailbox for emails
     *
     * @param  string $search Search string as specified in http://php.net/manual/en/function.imap-search.php
     * @param  int $criteria Sort flag from http://php.net/manual/en/function.imap-sort.php
     * @param  int $opt Options flags from http://php.net/manual/en/function.imap-sort.php
     * @param  int $dir Reverse option from http://php.net/manual/en/function.imap-sort.php
     * @return array List of matched uids
     */
    public function search($search = '', $criteria = SORTDATE, $opt = SE_UID, $dir = 1)
    {
        if ('' === ($search = trim($search))) {
            $search = null;
        }

        return imap_sort($this->stream, $criteria, $dir, $opt, $search);
    }

    /**
     * Close the stream.
     *
     * @return bool Success
     */
    public function close()
    {
        return imap_close($this->stream);
    }
}
