    <x-confirm-modal
        name="delete-archived"
        :title="__('Delete all archived photos?')"
        :message="__('This deletes every archived photo and its files. This action CANNOT be undone: the photos and their originals are gone for good.')"
        :confirm-label="__('Delete all archived')"
        method="deleteArchived"
    />

    <x-confirm-modal
        name="delete-gallery"
        :title="__('Delete gallery?')"
        :message="__('This action cannot be undone.')"
        method="delete"
    />

    <x-confirm-modal
        name="delete-photo"
        :title="__('Delete photo?')"
        :message="__('This action cannot be undone.')"
        :method="$this->pendingPhotoId ? 'deletePhoto('.$this->pendingPhotoId.')' : null"
    />

    <x-cover-focus-overlay :gallery="$gallery" :photo="$gallery->coverPhoto" :adjusting="$adjustingCover" />
