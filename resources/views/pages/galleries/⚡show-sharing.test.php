<?php

use App\Events\PhotoAdded;
use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPhoto;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Photoshoot;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutDefer;

uses(RefreshDatabase::class);

beforeEach(function () {
    withoutDefer();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Gallery Sharing', function () {
    it('can be shared with no options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
    });

    it('can expose versions in shared galleries and remembers the default', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.versionsVisible', true)
            ->call('share')
            ->assertHasNoErrors();

        expect($gallery->fresh()->is_share_versions_visible)->toBeTrue();
        expect($this->user->fresh()->shareGalleryDefaults()['versions_visible'])->toBeTrue();
    });

    it('prefills new gallery sharing options from the user defaults', function () {
        $this->user->rememberShareGalleryDefaults([
            'selectable' => true,
            'versions_visible' => true,
            'comments_enabled' => false,
        ]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSet('shareForm.selectable', true)
            ->assertSet('shareForm.versionsVisible', true)
            ->assertSet('shareForm.commentsEnabled', false);
    });

    it('can be shared with selectable options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.selectable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_selectable)->toBeTrue();
    });

    it('can be shared with downloadable options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.downloadable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_downloadable)->toBeTrue();
    });

    it('can be shared with limited selection', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.limitedSelection', true)
            ->set('shareForm.selectionLimit', 10)
            ->call('share');

        expect($gallery->fresh()->share_selection_limit)->toBe(10);
    });

    it('can be disabled for sharing', function () {
        $gallery = Gallery::factory()->for($this->team)->shared()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->call('disableSharing');

        expect($gallery->fresh()->is_shared)->toBeFalse();
    });

    it('can be shared with password protection', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->share_password)->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', true)
            ->set('shareForm.password', 'secret')
            ->call('share');

        expect($gallery->fresh()->share_password)->not->toBeNull();
    });

    it('can be shared with watermarked options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->fresh()->is_share_watermarked)->toBeFalse();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.watermarked', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_watermarked)->toBeTrue();
    });

    it('can disable password protection', function () {
        $gallery = Gallery::factory()->for($this->team)->protected('password')->create();
        expect($gallery->share_password)->not->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', false)
            ->call('share');

        expect($gallery->fresh()->share_password)->toBeNull();
    });

    it('can enable and disable photo comments for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeTrue();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', false)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeFalse();
    });

    it('can enable and disable photo annotations for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_annotations_enabled)->toBeTrue();
        expect($gallery->are_comments_enabled)->toBeTrue();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', false)
            ->call('share');

        expect($gallery->fresh()->are_annotations_enabled)->toBeFalse();
    });

    it('enables photo comments when photo annotations are enabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', false)
            ->set('shareForm.annotationsEnabled', true)
            ->assertSet('shareForm.commentsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeTrue();
        expect($gallery->are_annotations_enabled)->toBeTrue();
    });

    it('disables photo annotations when photo comments are disabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', true)
            ->set('shareForm.commentsEnabled', false)
            ->assertSet('shareForm.annotationsEnabled', false)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeFalse();
        expect($gallery->are_annotations_enabled)->toBeFalse();
    });

    it('does not seed photo annotations when photo comments are disabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->shared()->create([
            'are_comments_enabled' => false,
            'are_annotations_enabled' => true,
        ]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSet('shareForm.annotationsEnabled', false);
    });

    it('keeps photo annotations enabled for free teams', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', true)
            ->set('shareForm.annotationsEnabled', true)
            ->call('share');

        expect($gallery->fresh()->are_annotations_enabled)->toBeTrue();
    });

    it('can be shared with a description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->share_description)->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'This is a beautiful wedding gallery')
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBe('This is a beautiful wedding gallery');
    });

    it('can update the share description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Old description']);
        expect($gallery->share_description)->toBe('Old description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'Updated description')
            ->call('share');

        expect($gallery->fresh()->share_description)->toBe('Updated description');
    });

    it('can be shared without a description', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can remove the share description', function () {
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Some description']);
        expect($gallery->share_description)->toBe('Some description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('requires description when description is enabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasErrors('shareForm.description');
    });

    it('does not require description when description is disabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasNoErrors('shareForm.description');
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can be shared without cover image visible (default)', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
    });

    it('renders the share url for galleries whose name has no latin characters', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'name' => '日本語ギャラリー',
            'ulid' => 'TESTGALLERY',
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertHasNoErrors();
        $component->assertSee('shares/TESTGALLERY/TESTGALLERY');
    });

    it('falls back to the ulid when the name has no slugifiable characters', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'name' => '日本語ギャラリー',
            'ulid' => 'TESTGALLERY',
        ]);

        expect($gallery->slug)->toBe('TESTGALLERY');
        expect(Gallery::factory()->for($this->team)->create()->slug)->toBe('just-a-gallery');
    });
});

it('enables comments when annotations are enabled in the share modal', function () {
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    visit('/galleries/'.$gallery->id)
        ->click('@open-share-modal')
        ->click('@share-comments-toggle')
        ->assertNotChecked('@share-comments-toggle')
        ->click('@share-annotations-toggle')
        ->assertChecked('@share-comments-toggle')
        ->assertChecked('@share-annotations-toggle')
        ->assertNoJavascriptErrors();
})->group('browser');

test('the owner can open their own password-protected shared gallery', function () {
    $gallery = Gallery::factory()->shared()->protected()->for($this->team)->create();

    $this->actingAs($this->user)
        ->get('/galleries/'.$gallery->id)
        ->assertOk();
});
