<?php

namespace App\Models;

use App\Jobs\DeleteFromDisk;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MoodboardPhoto extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'size' => 'integer',
        ];
    }

    public function moodboard()
    {
        return $this->belongsTo(Moodboard::class);
    }

    public function deleteFromDisk()
    {
        if ($this->path) {
            DeleteFromDisk::dispatch($this->path, $this->diskOrDefault());
        }

        return $this;
    }

    protected function url(): Attribute
    {
        return Attribute::get(function () {
            return Storage::disk($this->diskOrDefault())->url($this->path);
        });
    }

    protected function diskOrDefault(): string
    {
        return $this->disk ?? 'public';
    }

    public function isOnPublicDisk()
    {
        return $this->diskOrDefault() === 'public';
    }

    public function isImage(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'tiff']);
    }

    public function isVideo(): bool
    {
        $ext = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));

        return in_array($ext, ['mp4', 'webm', 'ogg']);
    }
}
