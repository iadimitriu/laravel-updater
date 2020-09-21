<?php

namespace Iadimitriu\LaravelUpdater;

use Iadimitriu\LaravelUpdater\Contracts\UpdateEvent;
use Iadimitriu\LaravelUpdater\Events\UpdateEnded;
use Iadimitriu\LaravelUpdater\Events\UpdatesEnded;
use Iadimitriu\LaravelUpdater\Events\UpdatesStarted;
use Iadimitriu\LaravelUpdater\Events\UpdateStarted;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface as Resolver;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class Updater
{
    /**
     * The event dispatcher instance.
     *
     * @var Dispatcher
     */
    protected $events;

    /**
     * The migration repository implementation.
     *
     * @var MigrationRepositoryInterface
     */
    protected $repository;

    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The connection resolver instance.
     *
     * @var Resolver
     */
    protected $resolver;

    /**
     * The name of the default connection.
     *
     * @var string
     */
    protected $connection;

    /**
     * The paths to all of the migration files.
     *
     * @var array
     */
    protected $paths = [];

    /**
     * The output interface implementation.
     *
     * @var OutputStyle
     */
    protected $output;

    /**
     * Create a new migrator instance.
     *
     * @param UpdaterRepositoryInterface $repository
     * @param Resolver $resolver
     * @param Filesystem $files
     * @param Dispatcher|null $dispatcher
     */
    public function __construct(UpdaterRepositoryInterface $repository,
                                Resolver $resolver,
                                Filesystem $files,
                                Dispatcher $dispatcher = null)
    {
        $this->files = $files;
        $this->events = $dispatcher;
        $this->resolver = $resolver;
        $this->repository = $repository;
    }

    /**
     * Run the pending migrations at a given path.
     *
     * @param array|string $paths
     * @param array $options
     * @return array
     * @throws Throwable
     */
    public function run($paths = [], array $options = []): array
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->getUpdaterFiles($paths);

        $this->requireFiles($updates = $this->pendingUpdates(
            $files, $this->repository->getRan()
        ));

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($updates, $options);

        return $updates;
    }

    /**
     * Get the migration files that have not yet run.
     *
     * @param array $files
     * @param array $ran
     * @return array
     */
    protected function pendingUpdates(array $files, array $ran): array
    {
        return Collection::make($files)
            ->reject(function ($file) use ($ran) {
                return in_array($this->getUpdateName($file), $ran, true);
            })
            ->values()
            ->all();
    }

    /**
     * Run an array of migrations.
     *
     * @param array $updates
     * @param array $options
     * @return void
     * @throws Throwable
     */
    public function runPending(array $updates, array $options = []): void
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($updates) === 0) {
            $this->note('<info>Nothing to update.</info>');

            return;
        }

        // Next, we will get the next batch number for the migrations so we can insert
        // correct batch number in the database migrations repository when we store
        // each migration's execution. We will also extract a few of the options.
        $batch = $this->repository->getNextBatchNumber();

        $pretend = $options['pretend'] ?? false;

        $step = $options['step'] ?? false;

        $this->fireUpdateEvent(new UpdatesStarted);

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.

        foreach ($updates as $file) {
            $this->runUp($file, $batch, $pretend);

            if ($step) {
                $batch++;
            }
        }

        $this->fireUpdateEvent(new UpdatesEnded);
    }

    /**
     * Run "up" a migration instance.
     *
     * @param string $file
     * @param int $batch
     * @param bool $pretend
     * @return void
     * @throws Throwable
     */
    protected function runUp(string $file, int $batch, bool $pretend): void
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve(
            $name = $this->getUpdateName($file)
        );

        if ($pretend) {
            $this->pretendToRun($migration, 'up');

            return;
        }

        $this->note("<comment>Updating:</comment> {$name}");

        $startTime = microtime(true);

        $this->runUpdate($migration, 'run');

        $runTime = round(microtime(true) - $startTime, 2);

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($name, $batch);

        $this->note("<info>Updated:</info>  {$name} ({$runTime} seconds)");
    }

    /**
     * Run a migration inside a transaction if the database supports it.
     *
     * @param $update
     * @param string $method
     * @return void
     * @throws Throwable
     */
    protected function runUpdate($update, string $method): void
    {
        $connection = $this->resolveConnection(
            $update->getConnection()
        );

        $callback = function () use ($update, $method) {
            if (method_exists($update, $method)) {
                $this->fireUpdateEvent(new UpdateStarted($update, $method));

                $update->{$method}();

                $this->fireUpdateEvent(new UpdateEnded($update, $method));
            }
        };

        $this->getSchemaGrammar($connection)->supportsSchemaTransactions() && $update->withinTransaction
            ? $connection->transaction($callback)
            : $callback();
    }

    /**
     * Pretend to run the migrations.
     *
     * @param object $migration
     * @param string $method
     * @return void
     */
    protected function pretendToRun(object $migration, string $method): void
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->note("<info>{$name}:</info> {$query['query']}");
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     *
     * @param object $migration
     * @param string $method
     * @return array
     */
    protected function getQueries(object $migration, string $method): array
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection($migration->getConnection());

        return $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param string $file
     * @return object
     */
    public function resolve(string $file)
    {
        $class = Str::studly(implode('_', array_slice(explode('_', $file), 4)));

        return new $class;
    }

    /**
     * Get all of the migration files in a given path.
     *
     * @param string|array $paths
     * @return array
     */
    public function getUpdaterFiles($paths): array
    {
        return Collection::make($paths)->flatMap(function ($path) {
            return Str::endsWith($path, '.php') ? [$path] : $this->files->glob($path . '/*_*.php');
        })
            ->filter()
            ->values()
            ->keyBy(function ($file) {
                return $this->getUpdateName($file);
            })->sortBy(function ($file, $key) {
                return $key;
            })->all();
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param array $files
     * @return void
     */
    public function requireFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->files->requireOnce($file);
        }
    }

    /**
     * Get the name of the migration.
     *
     * @param string $path
     * @return string
     */
    public function getUpdateName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Register a custom migration path.
     *
     * @param string $path
     * @return void
     */
    public function path(string $path): void
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom migration paths.
     *
     * @return array
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Get the default connection name.
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Set the default connection name.
     *
     * @param string|null $name
     * @return void
     */
    public function setConnection(?string $name): void
    {
        if (!is_null($name)) {
            $this->resolver->setDefaultConnection($name);
        }

        $this->repository->setSource($name);

        $this->connection = $name;
    }

    /**
     * Resolve the database connection instance.
     *
     * @param string|null $connection
     * @return ConnectionInterface
     */
    public function resolveConnection(?string $connection): ConnectionInterface
    {
        return $this->resolver->connection($connection ?: $this->connection);
    }

    /**
     * Get the schema grammar out of a migration connection.
     *
     * @param ConnectionInterface $connection
     * @return Grammar
     */
    protected function getSchemaGrammar(ConnectionInterface $connection): Grammar
    {
        if (is_null($grammar = $connection->getSchemaGrammar())) {
            $connection->useDefaultSchemaGrammar();

            $grammar = $connection->getSchemaGrammar();
        }

        return $grammar;
    }

    /**
     * Get the migration repository instance.
     *
     * @return MigrationRepositoryInterface
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Get the file system instance.
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->files;
    }

    /**
     * Set the output implementation that should be used by the console.
     *
     * @param OutputStyle $output
     * @return $this
     */
    public function setOutput(OutputStyle $output): self
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Write a note to the console's output.
     *
     * @param string $message
     * @return void
     */
    protected function note(string $message): void
    {
        if ($this->output) {
            $this->output->writeln($message);
        }
    }

    /**
     * Fire the given event for the migration.
     *
     * @param UpdateEvent $event
     * @return void
     */
    public function fireUpdateEvent(UpdateEvent $event): void
    {
        if ($this->events) {
            $this->events->dispatch($event);
        }
    }
}
