<?php

namespace MailMap;

use MailMap\Contracts\ConnectionFactory as FactoryContract;
use MailMap\Connection;

class ConnectionFactory implements FactoryContract
{
    /**
     * Imap host as 'imap.example.org'
     *
     * @var string
     */
    protected $host;

    /**
     * Username
     *
     * @var string
     */
    protected $user;

    /**
     * Password
     *
     * @var string
     */
    protected $password;

    /**
     * Imap port
     *
     * @var int
     */
    protected $port = 993;

    /**
     * Imap service type
     *
     * @var string
     */
    protected $service = 'imap';

    /**
     * Encryption type
     *
     * @var string
     */
    protected $enc = 'ssl';

    /**
     * Format of connection string for opening connections.
     *
     * {host:port/service(s)/encryption}Mailbox
     *
     * @var string
     */
    protected static $connectionFormat = '{%s:%s/%s/%s}%s';

    /**
     * Create a new Imap connection
     *
     * @param array $config
     *          'host' => required
     *          'user' => required
     *          'password' => required
     *          'port' => 993
     *          'service' => 'imap'
     *          'encryption' => 'ssl'
     */
    public function __construct(array $config)
    {
        $this->host = $config['host'];
        $this->user = $config['user'];
        $this->password = $config['password'];
        $this->port = array_key_exists('port', $config) ? $config['port'] : $this->port;
        $this->service = array_key_exists('service', $config) ? $config['service'] : $this->service;
        $this->enc = array_key_exists('encryption', $config) ? $config['encryption'] : $this->enc;
    }

    /**
     * Create new IMAP connection
     *
     * @param  string $inbox
     * @return \MailMap\Contracts\ConnectionContract
     */
    public function create($inbox = 'INBOX')
    {
        $stream = imap_open($this->connectionString($inbox), $this->user, $this->password);

        return new Connection($stream);
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
        $connectionString = $this->connectionString();
        $stream = imap_open($connectionString, $this->user, $this->password);
        $mailboxes = imap_list($stream, $connectionString, $mailboxSearch);

        if ($withConnection) {
            return $mailboxes;
        }

        return array_map(function ($mailbox) use ($connectionString) {
            return str_replace($connectionString, '', $mailbox);
        }, $mailboxes);
    }

    /**
     * Make the IMAP connection string from configuration
     *
     * @param  string $inbox
     * @return string The connection string
     */
    protected function connectionString($inbox = '')
    {
        return sprintf(static::$connectionFormat, $this->host, $this->port, $this->service, $this->enc, $inbox);
    }
}
