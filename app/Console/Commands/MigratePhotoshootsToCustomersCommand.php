<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Photoshoot;
use Illuminate\Console\Command;

class MigratePhotoshootsToCustomersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photoshoots:migrate-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate existing photoshoots to create and link customers using customer_name and customer_email.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Migrating photoshoots to customers...');

        $photoshoots = Photoshoot::whereNull('customer_id')->get();
        $processed = 0;
        $skipped = 0;

        foreach ($photoshoots as $photoshoot) {
            $teamId = $photoshoot->team_id;
            $name = $photoshoot->customer_name;
            $email = $photoshoot->customer_email;

            if (! $name && ! $email) {
                $this->warn("Skipping photoshoot ID {$photoshoot->id}: no customer name or email.");
                $skipped++;

                continue;
            }

            // Find or create customer by team_id and email (unique constraint)
            $customer = null;
            if ($email) {
                $customer = Customer::where('team_id', $teamId)
                    ->where('email', $email)
                    ->first();
            }
            if (! $customer) {
                $customer = Customer::create([
                    'team_id' => $teamId,
                    'name' => $name ?? 'Unknown',
                    'email' => $email,
                ]);
            }

            $photoshoot->customer_id = $customer->id;
            $photoshoot->save();
            $processed++;
        }

        $this->info("Processed {$processed} photoshoots. Skipped {$skipped}.");
        $this->info('Migration completed.');
    }
}
