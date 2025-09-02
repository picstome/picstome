<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->disk = config('picstome.disk');
    Storage::fake($this->disk);
    $this->user = User::factory()->create();
    $this->team = Team::factory()->for($this->user, 'owner')->create();
    Mail::fake();
});

it('sends warning email 15 days before subscription ends', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'ends_at' => now()->addDays(15),
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    Mail::assertSent(\App\Mail\SubscriptionExpiringSoon::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('sends warning email 7 days before subscription ends', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'ends_at' => now()->addDays(7),
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    Mail::assertSent(\App\Mail\SubscriptionExpiringSoon::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('sends warning email 1 day before subscription ends', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'ends_at' => now()->addDay(),
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    Mail::assertSent(\App\Mail\SubscriptionExpiringSoon::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('sends deletion warning email 1 day after subscription expired', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDay(),
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    Mail::assertSent(\App\Mail\SubscriptionExpiredWarning::class, function ($mail) {
        return $mail->hasTo($this->user->email);
    });
});

it('deletes all galleries when subscription expired more than 30 days ago', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDays(31),
    ]);

    $gallery = Gallery::factory()->for($this->team)->create();
    $file = UploadedFile::fake()->image('photo.jpg');
    $path = $file->store('galleries/photos', ['disk' => $this->disk]);
    $photo = Photo::factory()->for($gallery)->create([
        'path' => $path,
        'disk' => $this->disk,
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    expect(Gallery::find($gallery->id))->toBeNull();
    expect(Photo::find($photo->id))->toBeNull();
    expect(Storage::disk($this->disk)->exists($path))->toBeFalse();
});

it('does not delete galleries when subscription expired less than 30 days ago', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'canceled',
        'ends_at' => now()->subDays(15),
    ]);

    $gallery = Gallery::factory()->for($this->team)->create();
    $file = UploadedFile::fake()->image('photo.jpg');
    $path = $file->store('galleries/photos', ['disk' => $this->disk]);
    $photo = Photo::factory()->for($gallery)->create([
        'path' => $path,
        'disk' => $this->disk,
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    expect(Gallery::find($gallery->id))->not->toBeNull();
    expect(Photo::find($photo->id))->not->toBeNull();
    expect(Storage::disk($this->disk)->exists($path))->toBeTrue();
});

it('does not delete galleries for active subscriptions', function () {
    $this->team->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test',
        'stripe_status' => 'active',
        'ends_at' => null,
    ]);

    $gallery = Gallery::factory()->for($this->team)->create();
    $file = UploadedFile::fake()->image('photo.jpg');
    $path = $file->store('galleries/photos', ['disk' => $this->disk]);
    $photo = Photo::factory()->for($gallery)->create([
        'path' => $path,
        'disk' => $this->disk,
    ]);

    artisan('subscriptions:cleanup')->assertExitCode(0);

    expect(Gallery::find($gallery->id))->not->toBeNull();
    expect(Photo::find($photo->id))->not->toBeNull();
    expect(Storage::disk($this->disk)->exists($path))->toBeTrue();
});