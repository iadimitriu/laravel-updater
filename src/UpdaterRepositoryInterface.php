<?php

namespace Iadimitriu\LaravelUpdater;

interface UpdaterRepositoryInterface
{
    /**
     * Get the completed migrations.
     *
     * @return array
     */
    public function getRan(): array;

    /**
     * Get list of migrations.
     *
     * @param  int  $steps
     * @return array
     */
    public function getUpdates(int $steps): array;

    /**
     * Get the last migration batch.
     *
     * @return array
     */
    public function getLast(): array;

    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array
     */
    public function getUpdateBatches(): array;

    /**
     * Log that a migration was run.
     *
     * @param  string  $file
     * @param  int  $batch
     * @return void
     */
    public function log(string $file, int $batch): void;

    /**
     * Remove a migration from the log.
     *
     * @param  object  $migration
     * @return void
     */
    public function delete(object $migration): void;

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber(): int;

    /**
     * Create the migration repository data store.
     *
     * @return void
     */
    public function createRepository(): void;

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists(): bool;

    /**
     * Set the information source to gather data.
     *
     * @param string|null $name
     * @return void
     */
    public function setSource(?string $name): void;
}
