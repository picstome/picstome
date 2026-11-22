<?php

use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

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
            ->submit([
                'shareForm.versionsVisible' => true,
            ], 'share')
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
            ->submit([
                'shareForm.selectable' => true,
            ], 'share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_selectable)->toBeTrue();
    });

    it('can be shared with downloadable options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.downloadable' => true,
            ], 'share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_downloadable)->toBeTrue();
    });

    it('can be shared with limited selection', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.limitedSelection' => true,
                'shareForm.selectionLimit' => 10,
            ], 'share');

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
            ->submit([
                'shareForm.passwordProtected' => true,
                'shareForm.password' => 'secret',
            ], 'share');

        expect($gallery->fresh()->share_password)->not->toBeNull();
    });

    it('can be shared with watermarked options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->fresh()->is_share_watermarked)->toBeFalse();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.watermarked' => true,
            ], 'share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_watermarked)->toBeTrue();
    });

    it('can disable password protection', function () {
        $gallery = Gallery::factory()->for($this->team)->protected('password')->create();
        expect($gallery->share_password)->not->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.passwordProtected' => false,
            ], 'share');

        expect($gallery->fresh()->share_password)->toBeNull();
    });

    it('can enable and disable photo comments for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.commentsEnabled' => true,
            ], 'share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeTrue();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.commentsEnabled' => false,
            ], 'share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeFalse();
    });

    it('can enable and disable photo annotations for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.annotationsEnabled' => true,
            ], 'share');

        $gallery->refresh();

        expect($gallery->are_annotations_enabled)->toBeTrue();
        expect($gallery->are_comments_enabled)->toBeTrue();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.annotationsEnabled' => false,
            ], 'share');

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
            ->submit([
                'shareForm.commentsEnabled' => true,
                'shareForm.annotationsEnabled' => true,
            ], 'share');

        expect($gallery->fresh()->are_annotations_enabled)->toBeTrue();
    });

    it('can be shared with a description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->share_description)->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => true,
                'shareForm.description' => 'This is a beautiful wedding gallery',
            ], 'share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBe('This is a beautiful wedding gallery');
    });

    it('can update the share description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Old description']);
        expect($gallery->share_description)->toBe('Old description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => true,
                'shareForm.description' => 'Updated description',
            ], 'share');

        expect($gallery->fresh()->share_description)->toBe('Updated description');
    });

    it('can be shared without a description', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => false,
            ], 'share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can remove the share description', function () {
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Some description']);
        expect($gallery->share_description)->toBe('Some description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => false,
            ], 'share');

        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('requires description when description is enabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => true,
                'shareForm.description' => '',
            ], 'share');

        $component->assertHasErrors('shareForm.description');
    });

    it('does not require description when description is disabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'shareForm.descriptionEnabled' => false,
                'shareForm.description' => '',
            ], 'share');

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
