#Simple documentation
A new documentation will be available later.

## Installation

Install the package via Composer

`composer require iadimitriu/laravel-updater`

## Usage

Publish the config file

```
php artisan vendor:publish --provider="Iadimitriu\LaravelUpdater\UpdaterServiceProvider" --tag="config"
```

Create a new file update

```
php artisan make:update TheUpdateName
```

Run the updates
```
php artisan update
```
