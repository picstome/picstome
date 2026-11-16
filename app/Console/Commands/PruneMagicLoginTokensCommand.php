<?php

namespace App\Console\Commands;

use App\Services\MagicLoginLink;
use Illuminate\Console\Command;

class PruneMagicLoginTokensCommand extends Command
{
    protected $signature = 'magic-login-tokens:prune';

    protected $description = 'Delete expired and used passwordless login tokens';

    public function handle(MagicLoginLink $links): int
    {
        $deleted = $links->prune();

        $this->info("Deleted {$deleted} magic login token(s).");

        return self::SUCCESS;
    }
}
