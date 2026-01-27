<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResendVerifyEmailCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:resend-verification
                            {--dry-run : Show what would be done without actually sending emails}
                            {--limit= : Limit the number of users to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend email verification notifications to users who haven\'t verified their email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $query = User::whereNull('email_verified_at');

        if ($this->option('limit')) {
            $query->limit($this->option('limit'));
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('No users found with unverified emails.');

            return;
        }

        $this->info("Found {$users->count()} users with unverified emails.");

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No emails will be sent.');
            $users->each(function ($user) {
                $this->line("Would send verification email to: {$user->email}");
            });

            return;
        }

        $this->info('Sending verification emails...');

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        $sent = 0;
        foreach ($users as $user) {
            try {
                $user->sendEmailVerificationNotification();
                $sent++;
            } catch (\Exception $e) {
                $this->error("Failed to send to {$user->email}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Successfully sent verification emails to {$sent} users.");
    }
}
