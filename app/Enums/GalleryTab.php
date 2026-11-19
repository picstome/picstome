<?php

namespace App\Enums;

/**
 * Tabs of the owner gallery photo wall.
 */
enum GalleryTab: string
{
    case All = 'all';
    case Favorited = 'favorited';
    case Commented = 'commented';
    case Archived = 'archived';
}
