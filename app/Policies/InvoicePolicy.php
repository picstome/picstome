<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoicePlanLimitService;

class InvoicePolicy
{
    public function __construct(private readonly InvoicePlanLimitService $limits) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $invoice->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->team->is($user->currentTeam) && $invoice->isDraft();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->team->is($user->currentTeam) && $invoice->isDraft();
    }

    /**
     * Determine whether the user can issue the invoice.
     *
     * Rectificative invoices never consume the plan limit, so a team can
     * always correct an invoice it already issued.
     */
    public function issue(User $user, Invoice $invoice): bool
    {
        if (! $invoice->team->is($user->currentTeam)) {
            return false;
        }

        return $invoice->isRectificative() || $this->limits->canIssue($invoice->team);
    }

    /**
     * Determine whether the user can revoke or regenerate the public link.
     */
    public function managePublicLink(User $user, Invoice $invoice): bool
    {
        return $invoice->team->is($user->currentTeam);
    }

    /**
     * Determine whether the user can rectify the invoice.
     */
    public function rectify(User $user, Invoice $invoice): bool
    {
        return $invoice->team->is($user->currentTeam) && $invoice->isIssued();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Invoice $invoice): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return false;
    }
}
