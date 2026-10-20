<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Signature;
use Illuminate\Console\Command;

class ImportCustomerBirthdays extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:customer-birthdays';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import birthdays from signatures to customers by matching email.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Signature::whereNotNull('birthday')
            ->whereNotNull('email')
            ->chunk(100, function ($signatures) {
                foreach ($signatures as $signature) {
                    $contract = $signature->contract;
                    if (! $contract) {
                        continue;
                    }
                    $teamId = $contract->team_id;
                    if (! $teamId) {
                        continue;
                    }
                    $customer = Customer::where('email', $signature->email)
                        ->where('team_id', $teamId)
                        ->first();
                    if ($customer && is_null($customer->birthdate)) {
                        $customer->birthdate = $signature->birthday;
                        $customer->save();
                        $this->info("Imported birthday for customer: {$customer->email} (team_id: {$teamId})");
                        $this->imported++;
                    }
                }
            });

        $this->info("Total birthdays imported: {$this->imported}");

        return 0;
    }

    protected $imported = 0;
}
