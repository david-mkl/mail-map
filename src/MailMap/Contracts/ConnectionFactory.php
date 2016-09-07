<?php

namespace MailMap\Contracts;

interface ConnectionFactory
{
    /**
     * Create a new ImapConnection to the mailbox
     *
     * @param  string $inbox Defaults as INBOX
     * @return \MailMap\Contracts\Connection
     */
    public function create($inbox = 'INBOX');
}
