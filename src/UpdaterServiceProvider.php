<?php

namespace Iadimitriu\LaravelUpdater;

use Iadimitriu\LaravelUpdater\Console\UpdateCreator;
use Iadimitriu\LaravelUpdater\Console\Commands\InstallCommand;
use Iadimitriu\LaravelUpdater\Console\Commands\UpdateCommand;
use Iadimitriu\LaravelUpdater\Console\Commands\UpdateMakeCommand;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider  implements DeferrableProvider
{

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = [
        'Update' => 'command.update',
        'UpdateMake' => 'command.update.make',
        'UpdateInstall' => 'command.update.install',
    ];

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRepository();

        $this->registerUpdater();

        $this->registerCreator();

        $this->registerCommands($this->commands);
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
//    protected function getUpdateFileName(Filesystem $filesystem): string
//    {
//
//        $timestamp = date('Y_m_d_His  ');
//
//        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'updates'.DIRECTORY_SEPARATOR)
//            ->flatMap(function ($path) use ($filesystem) {
//                return $filesystem->glob($path.'*_create_updates_table.php');
//            })->push($this->app->databasePath()."/migrations/{$timestamp}_create_updates_table.php")
//            ->first();
//    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository()
    {
        $this->app->singleton('update.repository', function ($app) {
//            $table = $app['config']['update.migrations']; // TODO: The table should be readed from the config file.
            $table = 'updates';
            return new DatabaseUpdateRepository($app['db'], $table);
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerUpdater()
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('updater', function ($app) {
            $repository = $app['update.repository'];

            return new Updater($repository, $app['db'], $app['files'], $app['events']);
        });
    }

    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function registerCreator()
    {
        $this->app->singleton('updater.creator', function ($app) {
            return new UpdateCreator($app['files']);
        });
    }

    /**
     * Register the given commands.
     *
     * @param  array  $commands
     * @return void
     */
    protected function registerCommands(array $commands)
    {
        foreach (array_keys($commands) as $command) {
            call_user_func_array([$this, "register{$command}Command"], []);
        }

        $this->commands(array_values($commands));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpdateCommand()
    {
        $this->app->singleton('command.update', function ($app) {
            return new UpdateCommand($app['updater']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpdateMakeCommand()
    {
        $this->app->singleton('command.update.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            $creator = $app['updater.creator'];

            $composer = $app['composer'];

            return new UpdateMakeCommand($creator, $composer);
        });
    }


    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpdateInstallCommand()
    {
        $this->app->singleton('command.update.install', function ($app) {
            return new InstallCommand($app['update.repository']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_merge([
            'updater', 'update.repository', 'update.creator',
        ], array_values($this->commands));
    }

}
