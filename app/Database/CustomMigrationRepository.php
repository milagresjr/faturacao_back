<?php

namespace App\Database;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Support\Facades\DB;

class CustomMigrationRepository extends DatabaseMigrationRepository
{
    /**
     * Get the completed migrations.
     *
     * Deduplica por nome de migration para coincidirem com as
     * chaves de getMigrationBatches() (evita "Undefined array key").
     *
     * @return string[]
     */
    public function getRan()
    {
        $ran = parent::getRan();

        return array_values(array_unique($ran));
    }

    /**
     * Get the completed migrations with their batch numbers.
     *
     * @return array<string, int>
     */
    public function getMigrationBatches()
    {
        $batches = parent::getMigrationBatches();

        // Normaliza chaves: garante que todos os valores sejam int
        foreach ($batches as $migration => $batch) {
            $batches[$migration] = (int) $batch;
        }

        return $batches;
    }

    /**
     * Get the last migration batch number.
     *
     * @return int
     */
    public function getLastBatchNumber()
    {
        return (int) parent::getLastBatchNumber();
    }

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber()
    {
        return $this->getLastBatchNumber() + 1;
    }
}