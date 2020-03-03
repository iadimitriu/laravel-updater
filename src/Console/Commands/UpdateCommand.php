<?php

namespace Iadimitriu\LaravelUpdater\Console\Commands;

use Iadimitriu\LaravelUpdater\Updater;
use Illuminate\Console\Command;

class UpdateCommand extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update {--database= : The database connection to use}
                {--force : Force the operation to run when in production}
                {--path=* : The path(s) to the migrations files to be executed}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the application update';

    /**
     * The migrator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected $updater;

    /**
     * Create a new migration command instance.
     *
     * @param \Illuminate\Database\Migrations\Updater $updater
     * @return void
     */
    public function __construct(Updater $updater)
    {
        parent::__construct();
        $this->updater = $updater;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {

        $this->prepareDatabase();
        // Next, we will check to see if a path option has been defined. If it has
        // we will use the path relative to the root of this installation folder
        // so that migrations may be run for any path within the applications.

        $this->updater->setOutput($this->output)
            ->run($this->getUpdatePaths(), []);

    }

    /**
     * Prepare the migration database for running.
     *
     * @return void
     */
    protected function prepareDatabase()
    {
        $this->updater->setConnection($this->option('database'));

        if (!$this->updater->repositoryExists()) {
            $this->call('update:install', array_filter([
                '--database' => $this->option('database'),
            ]));
        }
    }

    protected function getUpdatePaths()
    {
        return array_merge([$this->getUpdatePath()], glob($this->getUpdatePath() . '/*', GLOB_ONLYDIR));
    }


    protected function getUpdatePath()
    {
        return $this->laravel->basePath() . DIRECTORY_SEPARATOR . 'updates';
    }
}
