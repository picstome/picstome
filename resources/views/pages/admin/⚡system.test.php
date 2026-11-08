<?php

use App\Models\User;
use App\Services\SystemInfoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $this->admin = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);
});

it('allows an admin to view the system info page', function () {
    $git = app(SystemInfoService::class)->git();

    actingAs($this->admin)
        ->get('/admin/system')
        ->assertOk()
        ->assertSee($git['branch'])
        ->assertSee(app()->version())
        ->assertSee('System info')
        ->assertSee('Deployment')
        ->assertSee('Framework')
        ->assertSee('Services')
        ->assertSee('Server')
        ->assertSee('Laravel')
        ->assertSee('Uptime')
        ->assertSee('Load average')
        ->assertSee('Memory')
        ->assertSee('Disk')
        ->assertSeeHtml('data-test="branch-row"')
        ->assertSeeHtml('data-test="laravel-row"')
        ->assertSeeHtml('data-test="uptime-card"');
});

it('forbids access to the system info page for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/system')->assertForbidden();
});

it('reports the deployed branch and commit when running from a git checkout', function () {
    $info = app(SystemInfoService::class)->git();

    expect($info['branch'])->toBeString()
        ->and($info['commit'])->toBeString()
        ->and($info['root'])->toBe(base_path());
});

it('reports the framework, runtime and host metrics', function () {
    $service = app(SystemInfoService::class);

    expect($service->application())
        ->laravel->toBe(app()->version())
        ->php->toBe(PHP_VERSION)
        ->environment->toBe(app()->environment())
        ->and($service->runtime())->toHaveKeys(['database', 'cache', 'queue', 'session', 'filesystem'])
        ->and($service->server())->toHaveKeys(['os', 'uptime', 'load', 'cpus', 'memory', 'disk']);
});

it('refreshes the values when the refresh action runs', function () {
    Livewire::actingAs($this->admin)
        ->test('pages::admin.system')
        ->call('refresh')
        ->assertHasNoErrors();
});

it('keeps the system info link in the admin menu', function () {
    expect(route('admin.system'))->toContain('/admin/system');
});
