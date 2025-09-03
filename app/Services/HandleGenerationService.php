<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Str;

class HandleGenerationService
{
    public function generateFromName(string $name): string
    {
        if (empty(trim($name))) {
            return 'user';
        }

        // Remove special characters and convert to lowercase
        $handle = Str::slug($name, '');

        // If slug is empty after removing special chars, use 'user'
        if (empty($handle)) {
            return 'user';
        }

        return $handle;
    }

    public function generateFromEmail(string $email): string
    {
        // Extract username part from email
        $username = Str::before($email, '@');

        return $this->generateFromName($username);
    }

    public function generateUniqueHandle(string $name): string
    {
        $baseHandle = $this->generateFromName($name);
        $handle = $baseHandle;
        $counter = 1;

        // Keep incrementing counter until we find a unique handle
        while (Team::where('handle', $handle)->exists()) {
            $handle = $baseHandle . $counter;
            $counter++;
        }

        return $handle;
    }
}