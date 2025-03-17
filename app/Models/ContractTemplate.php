<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractTemplate extends Model
{
    /** @use HasFactory<\Database\Factories\ContractTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    protected function formattedMarkdownBody(): Attribute
    {
        return Attribute::get(function () {
            return Str::of($this->markdown_body)->markdown();
        });
    }

    protected function formattedUpdatedAt(): Attribute
    {
        return Attribute::get(function () {
            return $this->updated_at->isoFormat('MMM D, YYYY');
        });
    }
}
