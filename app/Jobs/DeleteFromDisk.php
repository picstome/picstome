<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class DeleteFromDisk implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public $path)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Storage::disk('public')->delete($this->path);
    }
}
