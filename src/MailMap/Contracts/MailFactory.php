<?php

namespace MailMap\Contracts;

interface MailFactory
{
    /**
     * Create new email from uid
     *
     * @param  int $uid
     * @param  resource $stream Imap stream from http://php.net/manual/en/function.imap-open.php
     * @return \MailMap\Contracts\Mail
     */
    public function create($uid, $stream);
}
