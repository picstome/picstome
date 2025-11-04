<?php

namespace App\Console\Commands;

use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateCustomerFromSignatureCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:update-customer-from-signature';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update customer info from contract signature for team_id 3';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $contracts = Contract::where('team_id', 3)->with(['signatures', 'photoshoot.customer'])->get();
        $updated = 0;
        foreach ($contracts as $contract) {
            $signedSignatures = $contract->signatures->whereNotNull('signed_at');
            if ($signedSignatures->count() !== 2) {
                continue;
            }
            $targetSignature = $signedSignatures->first(function ($sig) {
                return ! Str::contains($sig->name, 'Chema', true);
            });
            if (! $targetSignature) {
                continue;
            }
            $photoshoot = $contract->photoshoot;
            if (! $photoshoot || ! $photoshoot->customer) {
                continue;
            }
            $customer = $photoshoot->customer;
            $customer->name = $targetSignature->name;
            $customer->email = $targetSignature->email;
            $customer->birthdate = $targetSignature->birthday;
            $customer->save();
            $updated++;
            $this->info("Updated customer ID {$customer->id} from signature ID {$targetSignature->id}");
        }
        $this->info("Done. Updated {$updated} customers.");
    }
}
