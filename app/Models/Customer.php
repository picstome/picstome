<?php

namespace App\Models;

use App\Support\SafeMarkdown;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Customer extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function booted()
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->ulid)) {
                $customer->ulid = Str::ulid();
            }
        });
    }

    protected function casts()
    {
        return [
            'birthdate' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function photoshoots()
    {
        return $this->hasMany(Photoshoot::class);
    }

    public function galleries(): HasManyThrough
    {
        return $this->hasManyThrough(Gallery::class, Photoshoot::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    protected function email(): Attribute
    {
        return Attribute::set(function (?string $value) {
            if ($value === null || trim($value) === '') {
                return null;
            }

            return $value;
        });
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

    /**
     * Coarse SQL filter for the next $days of birthdays.
     *
     * It deliberately adds a small margin so the exact `isBirthdaySoon()`
     * check can run on a much smaller set instead of every customer. Month/day
     * comparison keeps it portable between SQLite (tests) and MySQL.
     *
     * `isBirthdaySoon()` moves a 29 February birthday to 1 March in non-leap
     * years, so that day also matches `02-29` when it falls in the window.
     */
    #[Scope]
    protected function birthdaySoon(Builder $query, int $days = 30): void
    {
        $day = now()->startOfDay();
        $end = $day->copy()->addDays($days + 2);
        $monthDays = [];

        for (; $day->lte($end); $day->addDay()) {
            $monthDays[] = $day->format('m-d');

            if ($day->format('m-d') === '03-01' && ! $day->isLeapYear()) {
                $monthDays[] = '02-29';
            }
        }

        $monthDays = array_values(array_unique($monthDays));

        $expression = $query->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%m-%d', birthdate)"
            : "DATE_FORMAT(birthdate, '%m-%d')";

        $query->whereNotNull('birthdate')
            ->whereRaw($expression.' IN ('.implode(', ', array_fill(0, count($monthDays), '?')).')', $monthDays);
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
        return Attribute::get(fn (): string => SafeMarkdown::render($this->notes));
    }
}
