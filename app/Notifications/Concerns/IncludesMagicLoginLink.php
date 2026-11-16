<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Services\MagicLoginLink;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Appends a passwordless login link to transactional emails sent to users.
 *
 * Links embedded in emails live longer than the 15-minute login link so they
 * still work when the user opens the message later.
 */
trait IncludesMagicLoginLink
{
    protected function addMagicLoginLink(MailMessage $mail, object $notifiable, ?string $redirect = null): MailMessage
    {
        if (! $notifiable instanceof User) {
            return $mail;
        }

        $url = app(MagicLoginLink::class)->for($notifiable, $redirect, MagicLoginLink::TTL_EMAIL);

        return $mail->line(__('You can also [log in directly](:url) without a password.', ['url' => $url]));
    }
}
