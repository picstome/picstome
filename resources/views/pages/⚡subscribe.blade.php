<?php

use App\Livewire\Concerns\HandlesPricingPage;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    use HandlesPricingPage;

    public function mount(): void
    {
        $this->mountPricingPage();
    }
}; ?>

@include('partials.pricing-page', ['public' => true])
