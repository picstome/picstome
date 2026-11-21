<?php

use App\Support\UploadContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A Livewire temporary upload whose bytes live on a remote (S3-like) disk:
 * `getRealPath()` returns the object key, not a local file.
 */
function remoteTemporaryUpload(string $name, string $contents): TemporaryUploadedFile
{
    Storage::fake('remote-tmp');
    Storage::disk('remote-tmp')->put(FileUploadConfiguration::path($name, false), $contents);

    return new class($name, 'remote-tmp') extends TemporaryUploadedFile
    {
        public function getRealPath(): string
        {
            return FileUploadConfiguration::path($this->getFilename(), false);
        }
    };
}

it('recognises an image stored on a remote temporary disk', function () {
    $jpeg = UploadedFile::fake()->image('photo.jpg', 20, 10)->getContent();

    expect(UploadContent::isReadableImage(remoteTemporaryUpload('photo.jpg', $jpeg)))->toBeTrue();
});

it('rejects a renamed non-image on a remote temporary disk', function () {
    expect(UploadContent::isReadableImage(remoteTemporaryUpload('photo.jpg', '<html>not an image</html>')))->toBeFalse();
});

it('reads the header of a remote video and pdf upload', function () {
    $video = remoteTemporaryUpload('clip.mp4', "\x00\x00\x00\x18ftypmp42".str_repeat("\x00", 32));

    expect(UploadContent::isReadableVideo($video))->toBeTrue()
        ->and(UploadContent::headerBytes(remoteTemporaryUpload('doc.pdf', "%PDF-1.7\n..."), 5))->toBe('%PDF-');
});

it('accepts a local upload as before', function () {
    expect(UploadContent::isReadableImage(UploadedFile::fake()->image('local.png', 5, 5)))->toBeTrue();
});
