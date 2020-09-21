<?php

namespace Iadimitriu\LaravelUpdater\Console;

use Closure;
use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateCreator
{
    /**
     * The filesystem instance.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The registered post create hooks.
     *
     * @var array
     */
    protected $postCreate = [];

    /**
     * Create a new migration creator instance.
     *
     * @param Filesystem $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Create a new migration at the given path.
     *
     * @param string $name
     * @param string $path
     * @return string
     *
     * @throws Exception
     */
    public function create(string $name, string $path): string
    {
        $this->ensureUpdateDoesntAlreadyExist($name, $path);

        // First we will get the stub file for the migration, which serves as a type
        // of template for the migration. Once we have those we will populate the
        // various place-holders, save the file, and run the post create event.
        $stub = $this->getStub();

        $this->files->put(
            $path = $this->getPath($name, $path),
            $this->populateStub($name, $stub)
        );

        // Next, we will fire any hooks that are supposed to fire after a migration is
        // created. Once that is done we'll be ready to return the full path to the
        // migration file so it can be used however it's needed by the developer.
        $this->firePostCreateHooks();

        return $path;
    }

    /**
     * Ensure that a migration with the given name doesn't already exist.
     *
     * @param string $name
     * @param null $migrationPath
     * @return void
     *
     * @throws InvalidArgumentException
     */
    protected function ensureUpdateDoesntAlreadyExist(string $name, $migrationPath = null): void
    {
        if (!empty($migrationPath)) {
            if (!$this->files->isDirectory($migrationPath)) {
                $this->files->makeDirectory($migrationPath, 0755, true);
            }

            $migrationFiles = $this->files->glob($migrationPath . '/*.php');

            foreach ($migrationFiles as $migrationFile) {
                $this->files->requireOnce($migrationFile);
            }
        }

        if (class_exists($className = $this->getClassName($name))) {
            throw new InvalidArgumentException("A {$className} class already exists.");
        }
    }

    /**
     * Get the migration stub file.
     *
     * @return string
     * @throws FileNotFoundException
     */
    protected function getStub(): string
    {
        return $this->files->get($this->stubPath() . '/blank.stub');
    }

    /**
     * Populate the place-holders in the migration stub.
     *
     * @param string $name
     * @param string $stub
     * @return string
     */
    protected function populateStub(string $name, string $stub): string
    {
        $stub = str_replace('DummyClass', $this->getClassName($name), $stub);

        return $stub;
    }

    /**
     * Get the class name of a migration name.
     *
     * @param string $name
     * @return string
     */
    protected function getClassName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Get the full path to the migration.
     *
     * @param string $name
     * @param string $path
     * @return string
     */
    protected function getPath(string $name, string $path): string
    {
        return $path . '/' . $this->getDatePrefix() . '_' . $name . '.php';
    }

    /**
     * Fire the registered post create hooks.
     *
     * @return void
     */
    protected function firePostCreateHooks(): void
    {
        foreach ($this->postCreate as $callback) {
            $callback();
        }
    }

    /**
     * Register a post migration create hook.
     *
     * @param Closure $callback
     * @return void
     */
    public function afterCreate(Closure $callback): void
    {
        $this->postCreate[] = $callback;
    }

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    protected function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }

    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function stubPath(): string
    {
        return __DIR__ . '/Commands/stubs';
    }

    /**
     * Get the filesystem instance.
     *
     * @return Filesystem
     */
    public function getFilesystem(): Filesystem
    {
        return $this->files;
    }
}
