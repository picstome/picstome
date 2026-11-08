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

    $response = actingAs($this->admin)
        ->get('/admin/system')
        ->assertOk()
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

    if ($git['branch'] !== null) {
        $response->assertSee($git['branch']);
    }
});

it('forbids access to the system info page for non-admins', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    actingAs($user)->get('/admin/system')->assertForbidden();
});

it('reads the branch and commit of a git checkout', function () {
    $root = systemInfoFixtureRepo(fn () => [
        'HEAD' => "ref: refs/heads/main\n",
        'refs/heads/main' => "0123456789abcdef0123456789abcdef01234567\n",
    ]);

    $info = app(SystemInfoService::class)->git($root);

    expect($info['branch'])->toBe('main')
        ->and($info['commit'])->toBe('0123456789')
        ->and($info['root'])->toBe($root);
});

it('falls back to the detached commit when the checkout has no branch', function () {
    $root = systemInfoFixtureRepo(fn () => [
        'HEAD' => "abcdef0123456789abcdef0123456789abcdef01\n",
    ]);

    $info = app(SystemInfoService::class)->git($root);

    expect($info['branch'])->toBeNull()
        ->and($info['commit'])->toBe('abcdef0123');
});

it('returns nulls when the checkout has no HEAD', function () {
    $root = systemInfoFixtureRepo(fn () => []);

    $info = app(SystemInfoService::class)->git($root);

    expect($info['branch'])->toBeNull()
        ->and($info['commit'])->toBeNull()
        ->and($info['root'])->toBe($root);
});

it('returns nulls when there is no git checkout', function () {
    $root = systemInfoFixtureRepo(fn () => [], withGit: false);

    $info = app(SystemInfoService::class)->git($root);

    expect($info)->toBe(['branch' => null, 'commit' => null, 'root' => null]);
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

/**
 * Build a throwaway directory, optionally holding a `.git` folder with the
 * given files.
 *
 * @param  callable(): array<string, string>  $files
 */
function systemInfoFixtureRepo(callable $files, bool $withGit = true): string
{
    $root = sys_get_temp_dir().'/picstome-system-info-'.bin2hex(random_bytes(6));
    mkdir($root, 0777, true);

    if ($withGit) {
        mkdir($root.'/.git/refs/heads', 0777, true);
    }

    foreach ($files() as $path => $contents) {
        $target = $root.'/.git/'.$path;

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
    }

    $GLOBALS['systemInfoFixtureRoots'][] = $root;

    return $root;
}

function systemInfoFixtureCleanup(): void
{
    foreach ($GLOBALS['systemInfoFixtureRoots'] ?? [] as $root) {
        if (! is_dir($root)) {
            continue;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($root);
    }

    $GLOBALS['systemInfoFixtureRoots'] = [];
}

afterEach(fn () => systemInfoFixtureCleanup());
