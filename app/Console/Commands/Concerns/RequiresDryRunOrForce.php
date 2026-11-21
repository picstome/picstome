<?php

namespace App\Console\Commands\Concerns;

/**
 * Refuses a destructive command unless --dry-run or --force was provided.
 */
trait RequiresDryRunOrForce
{
    protected function guardDestructiveOperation(string $message, bool $asError = false): ?int
    {
        if ($this->option('dry-run') || $this->option('force')) {
            return null;
        }

        if ($asError) {
            $this->error($message);
        } else {
            $this->warn($message);
        }

        return self::FAILURE;
    }
}
