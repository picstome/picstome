<?php

namespace App\Traits\Queue;

/**
 * Human-readable formatting for queue metrics and scheduled task states.
 */
trait FormatsQueueMetrics
{
    public function formatDuration(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 1) {
            return number_format($seconds * 1000).' ms';
        }

        if ($seconds < 60) {
            return number_format($seconds, 1).' s';
        }

        return gmdate('H:i:s', (int) $seconds);
    }

    public function taskStatusColor(string $status, bool $isStale = false): string
    {
        return match (true) {
            $isStale => 'red',
            $status === 'success' => 'green',
            $status === 'failed' => 'red',
            $status === 'running' => 'amber',
            $status === 'skipped' => 'amber',
            default => 'zinc',
        };
    }

    public function taskStatusLabel(string $status, bool $isStale = false): string
    {
        return match (true) {
            $isStale => __('Stale'),
            $status === 'success' => __('OK'),
            $status === 'failed' => __('Failed'),
            $status === 'running' => __('Running'),
            $status === 'skipped' => __('Skipped'),
            default => __('Never'),
        };
    }
}
