<?php

namespace App\Models;

use Database\Factories\ContractTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ContractTemplate extends Model
{
    /** @use HasFactory<ContractTemplateFactory> */
    use HasFactory;

    protected $guarded = [];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * A template without an owning team is a Picstome template shared with
     * every user. Its `locale` determines who sees it.
     */
    public function isPublic(): bool
    {
        return $this->team_id === null;
    }

    /**
     * Team-owned templates plus the Picstome templates for the given locale.
     * Public templates without a locale are legacy rows and stay visible.
     */
    #[Scope]
    protected function availableTo(Builder $query, Team $team, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();

        $query->where(function (Builder $query) use ($team, $locale) {
            $query->whereBelongsTo($team)
                ->orWhere(function (Builder $query) use ($locale) {
                    $query->whereNull('team_id')
                        ->where(function (Builder $query) use ($locale) {
                            $query->where('locale', $locale)->orWhereNull('locale');
                        });
                });
        });
    }

    protected function formattedMarkdownBody(): Attribute
    {
        return Attribute::get(function (): string {
            return (string) Str::of($this->markdown_body)->markdown();
        });
    }

    protected function formattedUpdatedAt(): Attribute
    {
        return Attribute::get(function () {
            return $this->updated_at?->format('Y-m-d');
        });
    }
}
