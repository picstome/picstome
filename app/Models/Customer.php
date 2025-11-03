<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
            return $this->birthdate?->isoFormat('MMM D');
        });
    }

    protected function formattedWhatsappPhone(): Attribute
    {
        return Attribute::get(function () {
            // Remove all non-digit characters
            return preg_replace('/[^0-9]/', '', $this->phone);
        });
    }
}
