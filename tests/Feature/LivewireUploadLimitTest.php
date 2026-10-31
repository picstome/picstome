<?php

it('allows large temporary uploads in local and testing', function () {
    expect(config('livewire.temporary_file_upload.rules'))
        ->toContain('max:102400');
});
