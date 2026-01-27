<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\HandleGenerationService;
use Illuminate\Console\Command;

class BackfillTeamHandlesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:backfill-handles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill handles for existing teams that don\'t have one';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Backfilling handles for existing teams...');

        $teamsWithoutHandles = Team::whereNull('handle')->get();

        if ($teamsWithoutHandles->isEmpty()) {
            $this->info('No teams found that need handles');
            $this->info('Backfill completed successfully');

            return;
        }

        $handleGenerator = new HandleGenerationService;
        $processed = 0;

        foreach ($teamsWithoutHandles as $team) {
            // Generate handle from team name
            $team->update(['handle' => $handleGenerator->generateUniqueHandle($team->name)]);
            $processed++;
        }

        $this->info("Processed {$processed} teams");
        $this->info('Backfill completed successfully');
    }
}
