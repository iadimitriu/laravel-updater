<?php

namespace Iadimitriu\LaravelUpdater;

use Iadimitriu\LaravelUpdater\Console\Commands\InstallCommand;
use Iadimitriu\LaravelUpdater\Console\Commands\UpdateCommand;
use Iadimitriu\LaravelUpdater\Console\Commands\UpdateMakeCommand;
use Iadimitriu\LaravelUpdater\Console\UpdateCreator;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class UpdaterServiceProvider extends ServiceProvider implements DeferrableProvider
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
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPublishables();
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->registerRepository();

        $this->registerUpdater();

        $this->registerCreator();

        $this->registerCommands($this->commands);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/update.php' => config_path('update.php'),
        ], 'config');
    }

    /**
     * Register the migration repository service.
     *
     * @return void
     */
    protected function registerRepository(): void
    {
        $this->app->singleton('update.repository', function ($app) {
            return new DatabaseUpdateRepository($app['db'], $app['config']['update.migrations']);
        });
    }

    /**
     * Register the migrator service.
     *
     * @return void
     */
    protected function registerUpdater(): void
    {
        // The migrator is responsible for actually running and rollback the migration
        // files in the application. We'll pass in our database connection resolver
        // so the migrator can resolve any of these connections when it needs to.
        $this->app->singleton('updater', function ($app) {
            return new Updater($app['update.repository'], $app['db'], $app['files'], $app['events']);
        });
    }

    /**
     * Register the migration creator.
     *
     * @return void
     */
    protected function registerCreator(): void
    {
        $this->app->singleton('updater.creator', function ($app) {
            return new UpdateCreator($app['files']);
        });
    }

    /**
     * Register the given commands.
     *
     * @param array $commands
     * @return void
     */
    protected function registerCommands(array $commands): void
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
    protected function registerUpdateCommand(): void
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
    protected function registerUpdateMakeCommand(): void
    {
        $this->app->singleton('command.update.make', function ($app) {
            // Once we have the migration creator registered, we will create the command
            // and inject the creator. The creator is responsible for the actual file
            // creation of the migrations, and may be extended by these developers.
            return new UpdateMakeCommand($app['updater.creator'], $app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpdateInstallCommand(): void
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
    public function provides(): array
    {
        return array_merge([
            'updater', 'update.repository', 'update.creator',
        ], array_values($this->commands));
    }

}
