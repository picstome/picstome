<?php

use App\Models\Moodboard;

use function Laravel\Folio\render;

render(function (Moodboard $moodboard) {
    return to_route('shared-moodboards.show', ['moodboard' => $moodboard, 'slug' => $moodboard->slug]);
}) ?>
