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

    /**
     * Unlock the seed gallery when its own password verifies, plus every
     * same-customer gallery sharing that password. A standalone seed (no
     * photoshoot customer) scopes to itself.
     */
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

    /**
     * The scan is bounded by one customer, never the team, so a reused
     * password can never unlock another client's galleries and the bcrypt
     * cost stays proportional to one client's gallery count.
     *
     * @return int The number of galleries the password matched.
     */
    public function unlockCustomerGalleries(Customer $customer, string $password): int
    {
        $locked = $customer->galleries()
            ->whereNotNull('share_password')
            ->whereNotIn('ulid', $this->unlockedUlids())
            ->get();

        $matched = $locked->filter(fn (Gallery $gallery) => Hash::check($password, $gallery->share_password));

        $this->markUnlocked($matched->pluck('ulid')->all());

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
