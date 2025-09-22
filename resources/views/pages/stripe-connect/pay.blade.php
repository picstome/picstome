<?php

use Facades\App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use function Laravel\Folio\name;
use function Laravel\Folio\render;

name('stripe.connect.refresh');

render(function (Request $request) {
    $team = Auth::user()->currentTeam;
});

?>

<div class="max-w-xl mx-auto mt-10 p-8 bg-white rounded shadow">
    <h2 class="text-2xl font-bold mb-4">Pay via Stripe</h2>
    @if(session('error'))
        <div class="mb-4 text-red-600">{{ session('error') }}</div>
    @endif
    <form method="GET" action="{{ route('stripe.connect.pay') }}" class="space-y-4">
        <div>
            <label for="amount" class="block font-semibold mb-1">Amount (USD)</label>
            <input type="number" name="amount" id="amount" value="1500" min="100" step="100" class="border rounded px-3 py-2 w-full" required>
            <small class="text-gray-500">Enter amount in cents (e.g. 1500 = $15)</small>
        </div>
        <div>
            <label for="description" class="block font-semibold mb-1">Description</label>
            <input type="text" name="description" id="description" value="Single Charge" class="border rounded px-3 py-2 w-full" required>
        </div>
        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded hover:bg-blue-700">Pay</button>
    </form>
</div>
