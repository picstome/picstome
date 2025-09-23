<?php

use Livewire\Volt\Component;

use function Laravel\Folio\middleware;
use function Laravel\Folio\name;

name('payments');

middleware(['auth', 'verified']);

new class extends Component {

}; ?>

<x-app-layout>
    @volt('pages.payments')
        <div>

        </div>
    @endvolt
</x-app-layout>
