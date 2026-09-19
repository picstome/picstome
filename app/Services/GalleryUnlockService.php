<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Gallery;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Hash;

class GalleryUnlockService
{
    public const SESSION_KEY = 'unlocked_gallery_ulids';

    public function __construct(private Store $session) {}

    public function isUnlocked(Gallery $gallery): bool
    {
        return in_array((string) $gallery->ulid, $this->unlockedUlids(), true);
    }

    public function unlock(Gallery $seed, string $password): bool
    {
        if (! $seed->share_password || ! Hash::check($password, $seed->share_password)) {
            return false;
        }

        if ($customer = $seed->photoshoot?->customer) {
            $this->unlockCustomerGalleries($customer, $password);

            return true;
        }

        $this->markUnlocked([$seed->ulid]);

        return true;
    }

    public function unlockCustomerGalleries(Customer $customer, string $password): int
    {
        $locked = $customer->galleries()
            ->whereNotNull('share_password')
            ->whereNotIn('ulid', $this->unlockedUlids())
            ->get();

        $matched = $locked->filter(fn (Gallery $gallery) => Hash::check($password, $gallery->share_password));

        if ($matched->isNotEmpty()) {
            $this->markUnlocked($matched->pluck('ulid')->all());
        }

        return $matched->count();
    }

    /**
     * @return array<int, string>
     */
    public function unlockedUlids(): array
    {
        return $this->session->get(static::SESSION_KEY, []);
    }

    /**
     * @param  array<int, string>  $ulids
     */
    private function markUnlocked(array $ulids): void
    {
        $unlocked = array_map('strval', array_merge($this->unlockedUlids(), $ulids));

        $unlocked = array_values(array_unique($unlocked));

        $this->session->put(static::SESSION_KEY, $unlocked);
    }
}
