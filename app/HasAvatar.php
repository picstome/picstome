<?php

namespace App;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HasAvatar
{
    public function updateAvatar(UploadedFile $avatar, $storagePath = 'avatars')
    {
        tap($this->avatar_path, function ($previous) use ($avatar, $storagePath) {
            $this->forceFill([
                'avatar_path' => $avatar->storePublicly(
                    $storagePath, ['disk' => $this->avatarDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->avatarDisk())->delete($previous);
            }
        });
    }

    public function deleteAvatar()
    {
        if (is_null($this->avatar_path)) {
            return;
        }

        Storage::disk($this->avatarDisk())->delete($this->avatar_path);

        $this->forceFill([
            'avatar_path' => null,
        ])->save();
    }

    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): string {
            return $this->avatar_path
                    ? Storage::disk($this->avatarDisk())->url($this->avatar_path)
                    : $this->defaultAvatarUrl();
        });
    }

    protected function defaultAvatarUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    protected function avatarDisk()
    {
        return config('app.avatar_disk', 'public');
    }
}
