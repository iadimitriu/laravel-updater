<?php

namespace Iadimitriu\LaravelUpdater;

use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class DatabaseUpdateRepository implements UpdaterRepositoryInterface
{
    /**
     * The database connection resolver instance.
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * The name of the migration table.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection;

    /**
     * Create a new database migration repository instance.
     *
     * @param Resolver $resolver
     * @param string|null $table
     */
    public function __construct(Resolver $resolver, ?string $table)
    {
        $this->table = $table;

        $this->resolver = $resolver;
    }

    /**
     * Get the completed migrations.
     *
     * @return array
     */
    public function getRan(): array
    {
        return $this->table()
            ->orderBy('batch', 'asc')
            ->orderBy('migration', 'asc')
            ->pluck('migration')
            ->all();
    }

    /**
     * Get list of migrations.
     *
     * @param int $steps
     * @return array
     */
    public function getUpdates(int $steps): array
    {
        return $this->table()
            ->where('batch', '>=', '1')
            ->orderBy('batch', 'desc')
            ->orderBy('migration', 'desc')
            ->take($steps)
            ->get()
            ->all();
    }

    /**
     * Get the last migration batch.
     *
     * @return array
     */
    public function getLast(): array
    {
        return $this->table()
            ->where('batch', $this->getLastBatchNumber())
            ->orderBy('migration', 'desc')
            ->get()
            ->all();
    }

    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array
     */
    public function getUpdateBatches(): array
    {
        return $this->table()
            ->orderBy('batch', 'asc')
            ->orderBy('migration', 'asc')
            ->pluck('batch', 'migration')
            ->all();
    }

    /**
     * Log that a migration was run.
     *
     * @param string $file
     * @param int $batch
     * @return void
     */
    public function log(string $file, int $batch): void
    {
        $record = [
            'migration' => $file,
            'batch' => $batch,
            'created_at' => now()
        ];

        $this->table()->insert($record);

        Log::info("The file {$file} was executed in the batch number {$batch}");
    }

    /**
     * Remove a migration from the log.
     *
     * @param object $migration
     * @return void
     */
    public function delete(object $migration): void
    {
        $this->table()->where('migration', $migration->migration)->delete();
    }

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number.
     *
     * @return int
     */
    public function getLastBatchNumber(): int
    {
        return $this->table()->max('batch') ?? 0;
    }

    /**
     * Create the migration repository data store.
     *
     * @return void
     */
    public function createRepository(): void
    {
        try {
            $schema = $this->getConnection()->getSchemaBuilder();

            $schema->create($this->table, function ($table) {
                // The migrations table is responsible for keeping track of which of the
                // migrations have actually run for the application. We'll create the
                // table to hold the migration file's path as well as the batch ID.
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
                $table->dateTime('created_at');
            });
        } catch (Exception $exception) {
            throw new InvalidArgumentException("Could not create the updates table. Make sure the config file is published.");
        }
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return $this->getConnection()->getSchemaBuilder()->hasTable($this->table);
    }

    /**
     * Get a query builder for the migration table.
     *
     * @return Builder
     */
    protected function table(): Builder
    {
        return $this->getConnection()->table($this->table)->useWritePdo();
    }

    /**
     * Get the connection resolver instance.
     *
     * @return ConnectionResolverInterface
     */
    public function getConnectionResolver(): ConnectionResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Resolve the database connection instance.
     *
     * @return ConnectionInterface
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->resolver->connection($this->connection);
    }

    /**
     * Set the information source to gather data.
     *
     * @param string|null $name
     * @return void
     */
    public function setSource(?string $name): void
    {
        $this->connection = $name;
    }
}
