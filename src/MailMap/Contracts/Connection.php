<?php

namespace MailMap\Contracts;

interface Connection
{
    /**
     * Search mailbox for emails
     *
     * @param  string $search Search string as specified in http://php.net/manual/en/function.imap-search.php
     * @param  int $criteria Sort flag from http://php.net/manual/en/function.imap-sort.php
     * @param  int $opt Options flags from http://php.net/manual/en/function.imap-sort.php
     * @param  int $dir Reverse option from http://php.net/manual/en/function.imap-sort.php
     * @return array List of matched uids
     */
    public function search($search, $criteria = SORTDATE, $opt = SE_UID, $dir = 1);

    /**
     * Close the stream.
     *
     * @return bool Success
     */
    public function close();
}
