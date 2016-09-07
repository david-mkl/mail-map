<?php

namespace MailMap\Contracts;

interface Mail
{
    /**
     * Get the underlying Imap stream
     *
     * @return resource
     */
    public function getStream();
}
