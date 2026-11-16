<?php

namespace App\Services;

use App\Exceptions\InsufficientStorageException;
use App\Models\Team;

/**
 * Decides whether a team can move to a plan based on the storage it already
 * uses.
 *
 * Downgrades are allowed only when the used bytes fit in the target plan.
 * The same check runs in the UI and in the checkout/schedule services so a
 * stale page cannot bypass it.
 */
class PlanEligibilityService
{
    public function __construct(private readonly PlanCatalog $catalog) {}

    public function usedBytes(Team $team): int
    {
        // Quota decisions must use fresh numbers, not the ten-minute display
        // cache that the sidebar uses.
        return StorageUsageService::forTeamFresh($team);
    }

    public function limitBytes(string $tier): int
    {
        return (int) ($this->catalog->tier($tier)['storage_limit'] ?? 0);
    }

    public function fitsBytes(int $usedBytes, string $tier): bool
    {
        $limit = $this->limitBytes($tier);

        return $limit > 0 && $usedBytes <= $limit;
    }

    public function fits(Team $team, string $tier): bool
    {
        return $this->fitsBytes($this->usedBytes($team), $tier);
    }

    public function assertFits(Team $team, string $tier): void
    {
        $usedBytes = $this->usedBytes($team);

        if ($this->fitsBytes($usedBytes, $tier)) {
            return;
        }

        throw new InsufficientStorageException($this->message($usedBytes, $tier));
    }

    public function message(int $usedBytes, string $tier): string
    {
        return __('You are using :used of storage. Free up space before switching to :plan (:limit).', [
            'used' => DiskSpaceService::formatBytes($usedBytes),
            'plan' => (string) $this->catalog->tier($tier)['name'],
            'limit' => DiskSpaceService::formatBytes($this->limitBytes($tier)),
        ]);
    }
}
