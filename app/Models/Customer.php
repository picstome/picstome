<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'birthdate' => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoots()
    {
        return $this->hasMany(Photoshoot::class);
    }

    protected function formattedBirthdate(): Attribute
    {
        return Attribute::get(function () {
            return $this->birthdate?->format('Y-m-d');
        });
    }

    protected function age(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->birthdate) {
                return null;
            }

            return (int) $this->birthdate->diffInYears(now());
        });
    }

    public function isBirthdaySoon(): bool
    {
        if (! $this->birthdate) {
            return false;
        }

        $now = now()->startOfDay();

        $thisYearBirthday = $this->birthdate->copy()->year($now->year);

        if ($thisYearBirthday->lt($now->startOfDay())) {
            $thisYearBirthday->addYear();
        }

        return $now->diffInDays($thisYearBirthday, false) >= 0
            && $now->diffInDays($thisYearBirthday, false) <= 30;
    }

    protected function formattedWhatsappPhone(): Attribute
    {
        return Attribute::get(function () {
            // Remove all non-digit characters
            return preg_replace('/[^0-9]/', '', $this->phone);
        });
    }

    protected function formattedNotes(): Attribute
    {
        return Attribute::get(function () {
            return Str::markdown($this->notes ?? '');
        });
    }
}
