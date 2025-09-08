<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gallery>
 */
class GalleryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => 'Just a Gallery',
        ];
    }

    public function shared(): Factory
    {
        return $this->state(function () {
            return [
                'is_shared' => true,
            ];
        });
    }

    public function unshared(): Factory
    {
        return $this->state(function () {
            return [
                'is_shared' => false,
            ];
        });
    }

    public function selectable($limit = null): Factory
    {
        return $this->state(function () use ($limit) {
            return [
                'is_share_selectable' => true,
                'share_selection_limit' => $limit,
            ];
        });
    }

    public function unselectable(): Factory
    {
        return $this->state(function () {
            return [
                'is_share_selectable' => false,
            ];
        });
    }

    public function downloadable(): Factory
    {
        return $this->state(function () {
            return [
                'is_share_downloadable' => true,
            ];
        });
    }

    public function undownloadable(): Factory
    {
        return $this->state(function () {
            return [
                'is_share_downloadable' => false,
            ];
        });
    }

    public function protected($password = null): Factory
    {
        return $this->state(function () use ($password) {
            return [
                'share_password' => Hash::make($password ?? 'secret'),
            ];
        });
    }

    public function unprotected(): Factory
    {
        return $this->state(function () {
            return [
                'share_password' => null,
            ];
        });
    }

    public function watermarked(): Factory
    {
        return $this->state(function () {
            return [
                'is_share_watermarked' => true,
            ];
        });
    }

    public function unwatermarked(): Factory
    {
        return $this->state(function () {
            return [
                'is_share_watermarked' => false,
            ];
        });
    }

    public function withExpirationDate($days = 30): Factory
    {
        return $this->state(function () use ($days) {
            return [
                'expiration_date' => now()->addDays($days),
            ];
        });
    }
}
