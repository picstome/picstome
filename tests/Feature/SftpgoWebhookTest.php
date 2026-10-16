<?php

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.ftp_uploads.sftpgo_url' => null]);

    $this->account = FtpAccount::factory()->create(['username' => '234816']);

    config(['picstome.ftp_uploads.webhook_token' => 'secret-token']);
});

it('ingests immediately when a valid upload event arrives', function () {
    Queue::fake();

    $this->postJson('/webhooks/sftpgo', [
        'action' => 'upload',
        'username' => '234816',
        'virtual_path' => '/1Z8A0660.JPG',
    ], ['X-PICSTOME-TOKEN' => 'secret-token'])->assertNoContent();

    Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->ftpAccountId === $this->account->id
        && $job->path === $this->account->inboxPath().'/1Z8A0660.JPG');
});

it('rejects requests without the token', function () {
    $this->postJson('/webhooks/sftpgo', ['action' => 'upload', 'username' => '234816'], ['X-PICSTOME-TOKEN' => 'wrong'])
        ->assertUnauthorized();

    $this->postJson('/webhooks/sftpgo', ['action' => 'upload', 'username' => '234816'])
        ->assertUnauthorized();
});

it('rejects requests when no token is configured', function () {
    config(['picstome.ftp_uploads.webhook_token' => null]);

    $this->postJson('/webhooks/sftpgo', ['action' => 'upload', 'username' => '234816'])
        ->assertUnauthorized();
});

it('ignores events other than uploads', function () {
    Queue::fake();

    $this->postJson('/webhooks/sftpgo', [
        'action' => 'mkdir',
        'username' => '234816',
        'virtual_path' => '/test',
    ], ['X-PICSTOME-TOKEN' => 'secret-token'])->assertNoContent();

    Queue::assertNotPushed(IngestFtpUpload::class);
});

it('ignores uploads for unknown usernames', function () {
    Queue::fake();

    $this->postJson('/webhooks/sftpgo', [
        'action' => 'upload',
        'username' => 'nope',
        'virtual_path' => '/x.jpg',
    ], ['X-PICSTOME-TOKEN' => 'secret-token'])->assertNoContent();

    Queue::assertNotPushed(IngestFtpUpload::class);
});
