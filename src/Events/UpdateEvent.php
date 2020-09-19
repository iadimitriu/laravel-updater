<?php

namespace Iadimitriu\LaravelUpdater\Events;

use Iadimitriu\LaravelUpdater\Contracts\UpdateEvent as UpdateEventContract;
use Iadimitriu\LaravelUpdater\Update;
use Illuminate\Database\Migrations\Migration;

abstract class UpdateEvent implements UpdateEventContract
{
    /**
     * An migration instance.
     *
     * @var Migration
     */
    public $migration;

    /**
     * The migration method that was called.
     *
     * @var string
     */
    public $method;

    /**
     * Create a new event instance.
     *
     * @param Update $migration
     * @param string $method
     */
    public function __construct(Update $migration, string $method)
    {
        $this->method = $method;
        $this->migration = $migration;
    }
}
