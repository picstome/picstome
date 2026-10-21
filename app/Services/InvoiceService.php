<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InvoiceSetting;

/**
 * Builds the immutable snapshots stored in every invoice.
 *
 * The invoice keeps a copy of the issuer and the customer data, so editing
 * the customer or the settings later never changes an issued invoice.
 */
class InvoiceService
{
    /**
     * Tax and payment defaults applied to a new invoice.
     *
     * @return array<string, mixed>
     */
    public function defaults(InvoiceSetting $settings): array
    {
        return [
            'currency' => $settings->currency,
            'vat_percent' => (float) $settings->vat_percent,
            'irpf_percent' => (float) $settings->irpf_percent,
            'apply_irpf' => (bool) $settings->apply_irpf,
            'payment_terms' => $settings->payment_terms,
            'notes' => $settings->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function issuerSnapshot(InvoiceSetting $settings): array
    {
        return [
            'legal_name' => $settings->legal_name,
            'tax_id' => $settings->tax_id,
            'address' => $settings->address,
            'city' => $settings->city,
            'state' => $settings->state,
            'postal_code' => $settings->postal_code,
            'country' => $settings->country,
            'email' => $settings->email,
            'phone' => $settings->phone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function customerSnapshot(?Customer $customer): array
    {
        if (! $customer) {
            return [];
        }

        return [
            'name' => $customer->name,
            'legal_name' => $customer->legal_name ?: $customer->name,
            'tax_id' => $customer->tax_id,
            'address' => $customer->address,
            'city' => $customer->city,
            'state' => $customer->state,
            'postal_code' => $customer->postal_code,
            'country' => $customer->country,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ];
    }

    /**
     * Fiscal fields a complete invoice requires from the customer.
     *
     * @param  array<string, mixed>  $customer
     * @return list<string>
     */
    public function missingCustomerFields(array $customer): array
    {
        $required = ['legal_name', 'tax_id', 'address', 'city', 'postal_code', 'country'];

        return array_values(array_filter(
            $required,
            fn (string $field) => blank($customer[$field] ?? null)
        ));
    }
}
