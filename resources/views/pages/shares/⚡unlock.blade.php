<?php

use App\Models\Gallery;
use App\Services\GalleryUnlockService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public Gallery $gallery;

    public $password;

    public function mount(Gallery $gallery)
    {
        if (! $gallery->share_password) {
            return $this->redirect(route('shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]));
        }
    }

    public function unlock()
    {
        $this->ensureIsNotRateLimited();

        $validated = $this->validate(['password' => ['required', 'string']]);

        if (! app(GalleryUnlockService::class)->unlock($this->gallery, $validated['password'])) {
            RateLimiter::hit($this->throttleKey(), 60);

            throw ValidationException::withMessages([
                'password' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        return $this->redirect("/shares/{$this->gallery->ulid}/{$this->gallery->slug}");
    }

    /**
     * Limit password guesses per visitor and gallery.
     */
    protected function ensureIsNotRateLimited(): void
    {
        $key = $this->throttleKey();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }
    }

    protected function throttleKey(): string
    {
        return 'gallery-unlock:'.request()->ip().':'.$this->gallery->ulid;
    }
}; ?>

<div class="mx-auto mt-4 w-full md:w-96 lg:mt-8">
    <form wire:submit="unlock" class="space-y-6">
        <div>
            <x-ts-heading size="lg">{{ __('Protected gallery') }}</x-ts-heading>
            <x-ts-subheading>{{ __('Enter the gallery password to unlock it.') }}</x-ts-subheading>
        </div>
        <x-ts-input wire:model="password" :label="__('Password')" type="text" />
        <div class="flex">
            <x-ts-spacer />
         <x-ts-button type="submit" variant="primary">{{ __('Unlock') }}</x-ts-button>
        </div>
    </form>
</div>
