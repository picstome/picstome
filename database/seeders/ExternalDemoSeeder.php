<?php

namespace Database\Seeders;

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Creates a self-contained gallery for exercising the external API locally.
 *
 * Run with: php artisan db:seed --class=ExternalDemoSeeder
 * Credentials: desktop@test.com / password
 */
class ExternalDemoSeeder extends Seeder
{
    private const DEMO_EMAIL = 'desktop@test.com';

    private const PHOTO_COUNT = 50;

    /** 1x1 JPEG, small enough to keep the seed fast on a local MinIO bucket. */
    private const PIXEL = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AX//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AX//xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8Qf//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8Qf//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8Qf//Z';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed external API demo data in production.');

            return;
        }

        $user = $this->demoUser();
        $gallery = $this->demoGallery($user);

        $this->seedPhotos($gallery);

        $this->command?->info('External API demo ready.');
        $this->command?->info('User: '.self::DEMO_EMAIL.' / password');
        $this->command?->info('Gallery ID: '.$gallery->ulid);
    }

    protected function demoUser(): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            [
                'name' => 'Picstome Sync Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->personalTeam()) {
            $team = $user->ownedTeams()->create([
                'name' => $user->name."'s Studio",
                'personal_team' => true,
                'handle' => 'picstome-sync',
            ]);

            $user->switchTeam($team);
        } else {
            $user->switchTeam($user->personalTeam());
        }

        return $user;
    }

    protected function demoGallery(User $user): Gallery
    {
        return $user->currentTeam->galleries()->firstOrCreate(
            ['name' => 'Picstome Sync Demo'],
            ['is_public' => false],
        );
    }

    protected function seedPhotos(Gallery $gallery): void
    {
        $disk = config('picstome.disk', 's3');
        $contents = base64_decode(self::PIXEL);
        $hash = hash('sha256', $contents);

        for ($index = 1; $index <= self::PHOTO_COUNT; $index++) {
            $name = sprintf('demo-%03d.jpg', $index);
            $path = "{$gallery->storage_path}/{$name}";

            if (! Storage::disk($disk)->exists($path)) {
                Storage::disk($disk)->put($path, $contents);
            }

            Photo::query()->updateOrCreate(
                ['gallery_id' => $gallery->id, 'name' => $name],
                [
                    'source_name' => $name,
                    'identity_key' => Gallery::identityKey($name),
                    'path' => $path,
                    'disk' => $disk,
                    'size' => strlen($contents),
                    'stored_size' => strlen($contents),
                    'hash' => $hash,
                    'hash_algo' => 'sha256',
                    'width' => 1,
                    'height' => 1,
                    'status' => Photo::STATUS_PROCESSED,
                    'version_count' => 1,
                    'favorited_at' => $index % 10 === 0 ? now() : null,
                    'uploaded_at' => now(),
                ],
            );
        }

        $cover = $gallery->photos()->orderBy('id')->first();

        if ($cover) {
            $gallery->update(['cover_photo_id' => $cover->id]);
        }
    }
}
