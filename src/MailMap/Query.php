<?php

namespace MailMap;

use BadMethodCallException;
use InvalidArgumentException;
use MailMap\Contracts\Connection as ConnectionContract;
use MailMap\Contracts\MailFactory as MailFactoryContract;

class Query
{
    /**
     * Where conditions for this query.
     * See $searchCriteria for options
     *
     * @var array
     */
    protected $where = [];

    /**
     * The sort order of emails.
     * See $orderCriteria for options
     *
     * @var int
     */
    protected $order = SORTDATE;

    /**
     * Direction to sort
     *
     * @var int
     */
    protected $orderDir = 1;

    /**
     * Limit returned results
     *
     * @var int
     */
    protected $limit = 0;

    /**
     * Default max to limit returned emails
     *
     * @var int
     */
    public static $max = 100;

    /**
     * Allowed search criteria.
     *
     * See http://php.net/manual/en/function.imap-search.php
     *
     * @var array
     */
    protected static $searchCriteria = [
        'all',
        'answered',
        'bcc',
        'before',
        'body',
        'cc',
        'deleted',
        'flagged',
        'from',
        'keyword',
        'new',
        'old',
        'on',
        'recent',
        'seen',
        'since',
        'subject',
        'text',
        'to',
        'unanswered',
        'undeleted',
        'unflagged',
        'unkeyword',
        'unseen',
    ];

    /**
     * Allowed flags for sorting
     *
     * See http://php.net/manual/en/function.imap-sort.php
     *
     * @var array
     */
    protected static $orderCriteria = [
        SORTDATE,
        SORTARRIVAL,
        SORTFROM,
        SORTSUBJECT,
        SORTTO,
        SORTCC,
        SORTSIZE
    ];

    /**
     * Execute search query on connection and put
     * results into Mail wrappers using factory
     *
     * @param \MailMap\Contracts\Connection $connection
     * @param \MailMap\Contracts\MailFactory $factory
     * @return array List of Mail wrappers
     */
    public function get(ConnectionContract $connection, MailFactoryContract $factory)
    {
        $uids = $connection->search($this->parseSearch());
        $stream = $connection->getStream();

        $max = $this->limit > 0 ? $this->limit : self::$max;
        $uids = array_slice($uids, 0, $max);

        return array_map(function ($uid) use ($factory, $stream) {
            return $factory->create($uid, $stream);
        }, $uids);
    }

    /**
     * Set result limit
     *
     * @param  int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set where condition.
     *
     * @param  string $flag
     * @param  string $value
     * @return $this
     */
    public function where($flag, $value = null)
    {
        if (!in_array(strtolower($flag), static::$searchCriteria)) {
            throw new InvalidArgumentException("Invalid where criteria '{$flag}'");
        }

        $this->where[$flag] = $value;
        return $this;
    }

    /**
     * Set sorting order.
     *
     * imap_sort only allows one sort condition http://php.net/manual/en/function.imap-sort.php
     * Calling again will overwrite previous
     *
     * @param  int $orderFlag
     * @param  string $dir
     * @return $this
     */
    public function order($orderFlag, $dir = 'desc')
    {
        if (!in_array($orderFlag, static::$orderCriteria)) {
            throw new InvalidArgumentException("Invalid order criteria '{$orderFlag}'");
        }

        $this->order = $orderFlag;
        $this->orderDir = strtolower($dir) === 'asc' ? 0 : 1;

        return $this;
    }

    /**
     * Parse the 'where' conditions for imap_search
     *
     * @return string
     */
    protected function parseSearch()
    {
        return implode(' ', array_map(function ($key, $val) {
            if (is_null($val)) {
                return strtoupper($key);
            }
            return sprintf('%s %s', strtoupper($key), $val);
        }, array_keys($this->where), array_values($this->where)));
    }

    /**
     * Call 'where' magically if is a valid search keyword
     *
     * @param  string $method
     * @param  array $args
     * @return $this
     * @throws \BadMethodCallException
     */
    public function __call($method, $args)
    {
        $method = strtolower($method);

        if (in_array($method, self::$searchCriteria)) {
            return $this->where($method, ...$args);
        }

        throw new BadMethodCallException("No method called '{$method}'");
    }
}
