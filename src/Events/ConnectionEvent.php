<?php

namespace Iadimitriu\LaravelUpdater\Events;

use Illuminate\Database\Connection;

abstract class ConnectionEvent
{
    /**
     * The name of the connection.
     *
     * @var string
     */
    public $connectionName;

    /**
     * The database connection instance.
     *
     * @var Connection
     */
    public $connection;

    /**
     * Create a new event instance.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->connectionName = $connection->getName();
    }
}
