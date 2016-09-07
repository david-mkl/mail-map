<?php

namespace MailMap;

use MailMap\ConnectionFactory;
use MailMap\MailFactory;
use MailMap\Query;
use MailMap\Contracts\MailFactory as MailFactoryContract;

class MailMap
{
    /**
     * The Connection factory
     *
     * @var \MailMap\Contracts\ConnectionFactory
     */
    protected $factory;

    /**
     * The Mail factory
     *
     * @var \MailMap\Contracts\MailFactory
     */
    protected $mailFactory;

    /**
     * Create new MailMap from configuration
     *
     * @param array $config
     * @param \MailMap\Contracts\MailFactory $mailFactory
     */
    public function __construct(array $config, MailFactoryContract $mailFactory = null)
    {
        if (is_null($mailFactory)) {
            $mailFactory = new MailFactory;
        }

        $this->factory = new ConnectionFactory($config);
        $this->mailFactory = $mailFactory;
    }

    /**
     * Execute query on a new connection and wrap results in Mail wrappers.
     *
     * Provide a callable to set conditions, sorting, and limits on query.
     * The query instance will be provided as the single arguement to the
     * callable.
     *
     * @param  string $inbox
     * @param  callable $queryCall
     * @return array
     */
    public function query($inbox = 'INBOX', callable $queryCall = null)
    {
        $query = new Query;

        if (!is_null($queryCall)) {
            $query = $queryCall($query);
        }

        return $query->get($this->factory->create($inbox), $this->mailFactory);
    }

    /**
     * Get a list of mailboxes on the mail server.
     *
     * @param  string $mailboxSearch Pattern from http://php.net/manual/en/function.imap-list.php
     * @param  bool $withConnection Will strip off connection string from mailboxes by default
     * @return array List of mailboxes
     */
    public function mailboxes($mailboxSearch = '*', $withConnection = false)
    {
        return $this->factory->mailboxes($mailboxSearch, $withConnection);
    }
}
