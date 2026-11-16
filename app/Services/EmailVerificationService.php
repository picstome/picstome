<?php

namespace App\Services;

use App\Jobs\AddToAcumbamailList;
use App\Models\User;
use Illuminate\Auth\Events\Verified;

/**
 * Marks an email address as verified and runs the side effects shared by the
 * signed verification link and the passwordless login link.
 *
 * A magic login link is sent to the address, so opening it proves the same
 * thing as the verification email.
 */
class EmailVerificationService
{
    public function markVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        $listId = app()->getLocale() === 'es'
            ? config('services.acumbamail.list_id_es')
            : config('services.acumbamail.list_id');

        AddToAcumbamailList::dispatch($user->email, $user->name, $listId);
    }
}
