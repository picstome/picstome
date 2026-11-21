<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\BelongsToCurrentTeam;
use App\Services\InvoicePlanLimitService;

class InvoicePolicy
{
    use BelongsToCurrentTeam;

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
        return $this->belongsToCurrentTeam($user, $invoice->team);
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
        return $this->belongsToCurrentTeam($user, $invoice->team) && $invoice->isDraft();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentTeam($user, $invoice->team) && $invoice->isDraft();
    }

    /**
     * Determine whether the user can issue the invoice.
     *
     * Rectificative invoices never consume the plan limit, so a team can
     * always correct an invoice it already issued.
     */
    public function issue(User $user, Invoice $invoice): bool
    {
        if (! $this->belongsToCurrentTeam($user, $invoice->team)) {
            return false;
        }

        return $invoice->isRectificative() || $this->limits->canIssue($invoice->team);
    }

    /**
     * Determine whether the user can revoke or regenerate the public link.
     */
    public function managePublicLink(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentTeam($user, $invoice->team);
    }

    /**
     * Determine whether the user can change the internal paid status.
     *
     * Only the invoice in force carries a payment status: an issued invoice or
     * an issued corrective. A rectified invoice was replaced by its corrective,
     * so the payment is recorded on the corrective, never on both.
     */
    public function markAsPaid(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentTeam($user, $invoice->team)
            && $invoice->isIssued();
    }

    /**
     * Determine whether the user can rectify the invoice.
     */
    public function rectify(User $user, Invoice $invoice): bool
    {
        return $this->belongsToCurrentTeam($user, $invoice->team) && $invoice->isIssued();
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
