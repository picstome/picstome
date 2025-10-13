@if($team->hasSocialLinks())
    <div class="my-14">
        <div class="flex flex-wrap justify-center gap-4">
            @if($team->instagram_url)
                <a href="{{ $team->instagram_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-instagram.svg" />
                </a>
            @endif

            @if($team->youtube_url)
                <a href="{{ $team->youtube_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-youtube.svg" />
                </a>
            @endif

            @if($team->facebook_url)
                <a href="{{ $team->facebook_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-facebook.svg" />
                </a>
            @endif

            @if($team->x_url)
                <a href="{{ $team->x_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-x.svg" />
                </a>
            @endif

            @if($team->tiktok_url)
                <a href="{{ $team->tiktok_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://s.magecdn.com/social/tc-tiktok.svg" />
                </a>
            @endif

            @if($team->twitch_url)
                <a href="{{ $team->twitch_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle>
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286H13.714L22.286 10.857V0H6zm14.571 10.857l-3.429 3.429H13.714l-3 3v-3H6.857V1.714H20.57v9.143z"/>
                        </svg>
                    </flux:avatar>
                </a>
            @endif

            @if($team->website_url)
                <a href="{{ $team->website_url }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://unavatar.io/{{ parse_url($team->website_url, PHP_URL_HOST) }}" />
                </a>
            @endif

            @if($team->other_social_links)
                <a href="{{ $team->other_social_links['url'] }}" target="_blank" rel="noopener noreferrer nofollow">
                    <flux:avatar size="lg" circle src="https://unavatar.io/{{ parse_url($team->other_social_links['url'], PHP_URL_HOST) }}" />
                </a>
            @endif

            @if($team->hasCompletedOnboarding() && $team->show_pay_button)
                <flux:modal.trigger name="generate-payment-link">
                    <flux:avatar icon="credit-card" color="sky" size="lg" class="cursor-pointer" circle />
                </flux:modal.trigger>
            @endif
        </div>
    </div>
@elseif($team->hasCompletedOnboarding() && $team->show_pay_button)
    <div class="my-14">
        <div class="flex flex-wrap justify-center gap-4">
            <flux:modal.trigger name="generate-payment-link">
                <flux:avatar icon="credit-card" color="sky" size="lg" class="cursor-pointer" circle />
            </flux:modal.trigger>
        </div>
    </div>
@endif

@if($team->hasCompletedOnboarding() && $team->show_pay_button)
    <flux:modal name="generate-payment-link" class="w-full sm:max-w-lg">
        <form wire:submit="generatePaymentLink" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Send a Payment to :team', ['team' => $team->name]) }}</flux:heading>
                <flux:subheading>{{ __('Enter the amount and a note for your payment. You’ll be redirected to a secure checkout.') }}</flux:subheading>
                            </div>
                <flux:input wire:model="amount" :label="__('Amount')" required />
                <flux:input wire:model="description" :label="__('Note or Description')" type="text" required />
                <div class="flex">
                    <flux:spacer />
                    <flux:button type="submit" variant="primary">{{ __('Continue to Payment') }}</flux:button>
                </div>
        </form>
    </flux:modal>
@endif
