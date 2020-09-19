<?php

namespace Iadimitriu\LaravelUpdater\Console\Commands;

use Exception;
use Iadimitriu\LaravelUpdater\Console\UpdateCreator;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use InvalidArgumentException;

class UpdateMakeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:update {name : The name of the update}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new update file';

    /**
     * The migration creator instance.
     *
     * @var MigrationCreator
     */
    protected $creator;

    /**
     * The Composer instance.
     *
     * @var Composer
     */
    protected $composer;

    /**
     * Create a new migration install command instance.
     *
     * @param UpdateCreator $creator
     * @param Composer $composer
     * @return void
     */
    public function __construct(UpdateCreator $creator, Composer $composer)
    {
        parent::__construct();

        $this->creator = $creator;
        $this->composer = $composer;
    }

    /**
     * Execute the console command.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        if (is_null($this->laravel['config']['update.path'])) {
            throw new InvalidArgumentException("Invalid path. Make sure the config file is published.");
        }

        // It's possible for the developer to specify the tables to modify in this
        // schema operation. The developer may also specify if this table needs
        // to be freshly created so we can create the appropriate migrations.
        $name = Str::snake(trim($this->input->getArgument('name')));

        // Now we are ready to write the migration out to disk. Once we've written
        // the migration out, we will dump-autoload for the entire framework to
        // make sure that the migrations are registered by the class loaders.
        $this->writeUpdate($name);

        $this->composer->dumpAutoloads();
    }

    /**
     * Write the migration file to disk.
     *
     * @param string $name
     * @return void
     * @throws Exception
     */
    protected function writeUpdate(string $name): void
    {
        $file = $this->creator->create(
            $name, $this->getUpdatePath());

        $file = pathinfo($file, PATHINFO_FILENAME);

        $this->line("<info>Created Update:</info> {$file}");
    }

    /**
     * Get migration path (either specified by '--path' option or default location).
     *
     * @return string
     */
    protected function getUpdatePath(): string
    {
        return $this->laravel->basePath() . DIRECTORY_SEPARATOR . $this->laravel['config']['update.path'];
    }

}
