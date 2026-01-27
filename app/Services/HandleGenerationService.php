<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Str;

class HandleGenerationService
{
    public function generateFromName(string $name): string
    {
        return Str::slug($name, '');
    }

    public function generateUniqueHandle(string $name): string
    {
        $baseHandle = $this->generateFromName($name);
        $handle = $baseHandle;
        $counter = 1;

        while (Team::where('handle', $handle)->exists()) {
            $handle = $baseHandle.$counter;
            $counter++;
        }

        return $handle;
    }
}
