<?php

use App\Models\FtpAccount;
use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generates camera friendly codes with the expected length and alphabet', function () {
    $code = FtpAccount::generateCode();

    expect(strlen($code))->toBe(8);

    foreach (str_split($code) as $char) {
        expect(str_contains(FtpAccount::CAMERA_FRIENDLY_ALPHABET, $char))->toBeTrue();
    }

    // Ambiguous lookalikes never appear.
    expect($code)->not->toMatch('/[0O1Iil]/');
});

it('generates accounts for a gallery with unique camera friendly usernames', function () {
    $gallery = Gallery::factory()->create();

    $account = FtpAccount::generateForGallery($gallery);
    $otherAccount = FtpAccount::generateForGallery(Gallery::factory()->create());

    expect($account->active)->toBeTrue();
    expect($account->gallery_id)->toBe($gallery->id);
    expect($account->username)->not->toBe($otherAccount->username);
    expect($account->username)->toMatch('/^[A-HJ-NP-Z2-9]{8}$/');
    expect($account->password)->toMatch('/^[A-HJ-NP-Z2-9]{10}$/');
});

it('encrypts passwords at rest', function () {
    $account = FtpAccount::factory()->create(['password' => 'SECRETPW']);

    expect($account->password)->toBe('SECRETPW');

    $rawPassword = \DB::table('ftp_accounts')->where('id', $account->id)->value('password');

    expect($rawPassword)->not->toBe('SECRETPW');
    expect($rawPassword)->not->toContain('SECRETPW');
});
