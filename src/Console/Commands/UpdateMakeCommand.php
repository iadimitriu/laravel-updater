<?php

namespace Iadimitriu\LaravelUpdater\Console\Commands;

use Iadimitriu\LaravelUpdater\UpdateCreator;
use Illuminate\Console\Command;

use Illuminate\Support\Composer;
use Illuminate\Support\Str;

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
     * @var \Illuminate\Database\Migrations\MigrationCreator
     */
    protected $creator;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * Create a new migration install command instance.
     *
     * @param  \Iadimitriu\LaravelUpdater\UpdateCreator  $creator
     * @param  \Illuminate\Support\Composer  $composer
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
     */
    public function handle()
    {
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
     * @param  string  $name
     * @param  string  $table
     * @param  bool  $create
     * @return string
     */
    protected function writeUpdate($name)
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
    protected function getUpdatePath()
    {
        return $this->laravel->basePath().DIRECTORY_SEPARATOR.'updates';
    }

}
