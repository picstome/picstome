<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Generates two years of demo usage data so the admin stats page has
 * meaningful totals, growth, and free/paid/lifetime averages.
 *
 * Run explicitly with: php artisan db:seed --class=StatsDemoSeeder
 */
class StatsDemoSeeder extends Seeder
{
    private const USER_COUNT = 120;

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusing to seed demo stats in production.');

            return;
        }

        $this->deleteExistingDemoData();

        $start = now()->subYears(2)->startOfDay();
        $now = now();

        $this->command?->info('Seeding two years of demo usage stats...');

        foreach (range(1, self::USER_COUNT) as $index) {
            $createdAt = $this->randomDate($start, $now);

            $user = User::factory()->withPersonalTeam()->create([
                'name' => fake()->name(),
                'email' => "stats-demo-{$index}@example.test",
            ]);

            $user->forceFill(['created_at' => $createdAt])->saveQuietly();

            $team = $user->personalTeam();
            $team->update(['created_at' => $createdAt]);

            $user->forceFill(['current_team_id' => $team->id])->saveQuietly();

            $this->seedPlan($team, $createdAt, $now);
            $this->seedGalleries($team, $createdAt, $now);
            $this->seedContracts($team, $createdAt, $now);
        }

        $this->command?->info('Demo stats seeded: '.User::query()->where('email', 'like', 'stats-demo-%@example.test')->count().' users.');
    }

    /**
     * Give the team a current subscription, a canceled subscription, or
     * lifetime access. The remaining teams stay free.
     */
    private function seedPlan(Team $team, Carbon $teamCreatedAt, Carbon $now): void
    {
        $roll = fake()->numberBetween(1, 100);

        if ($roll <= 50) {
            $subscriptionCreatedAt = $this->randomDate($teamCreatedAt, $now);

            $team->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => 'sub_demo_'.$team->id,
                'stripe_status' => 'active',
                'stripe_price' => fake()->randomElement(['price_demo_100gb', 'price_demo_250gb', 'price_demo_1tb']),
                'quantity' => 1,
                'created_at' => $subscriptionCreatedAt,
                'updated_at' => $subscriptionCreatedAt,
            ]);

            return;
        }

        if ($roll <= 62) {
            if ($teamCreatedAt->gte($now->copy()->subDays(30))) {
                return;
            }

            $subscriptionCreatedAt = $this->randomDate($teamCreatedAt, $now->copy()->subDays(15));

            $team->subscriptions()->create([
                'type' => 'default',
                'stripe_id' => 'sub_demo_canceled_'.$team->id,
                'stripe_status' => 'canceled',
                'stripe_price' => 'price_demo_100gb',
                'quantity' => 1,
                'ends_at' => $subscriptionCreatedAt->copy()->addDays(fake()->numberBetween(5, 14)),
                'created_at' => $subscriptionCreatedAt,
                'updated_at' => $subscriptionCreatedAt,
            ]);

            return;
        }

        if ($roll <= 75) {
            $team->update([
                'lifetime_at' => $this->randomDate($teamCreatedAt, $now),
            ]);
        }
    }

    private function seedGalleries(Team $team, Carbon $teamCreatedAt, Carbon $now): void
    {
        foreach (range(1, fake()->numberBetween(2, 5)) as $galleryIndex) {
            $galleryCreatedAt = $this->randomDate($teamCreatedAt, $now);

            $gallery = Gallery::factory()->for($team)->create([
                'name' => 'Demo shoot '.$galleryIndex,
                'created_at' => $galleryCreatedAt,
            ]);

            foreach (range(1, fake()->numberBetween(10, 30)) as $photoIndex) {
                $photoCreatedAt = $this->randomDate($galleryCreatedAt, $now);
                $size = fake()->numberBetween(1_500_000, 12_000_000);

                $photo = Photo::factory()->for($gallery)->create([
                    'name' => "IMG_{$gallery->id}_{$photoIndex}.jpg",
                    'source_name' => "IMG_{$gallery->id}_{$photoIndex}.jpg",
                    'path' => "teams/{$team->id}/galleries/{$gallery->ulid}/photos/{$photoIndex}.jpg",
                    'size' => $size,
                    'stored_size' => (int) round($size * fake()->randomFloat(2, 0.65, 1.05)),
                    'status' => 'processed',
                    'version_count' => 1,
                    'uploaded_at' => $photoCreatedAt,
                    'created_at' => $photoCreatedAt,
                ]);

                if (! fake()->boolean(15)) {
                    continue;
                }

                foreach (range(2, fake()->numberBetween(2, 3)) as $versionNumber) {
                    $versionCreatedAt = $this->randomDate($photoCreatedAt, $now);

                    PhotoVersion::factory()->for($photo)->create([
                        'version_number' => $versionNumber,
                        'name' => $photo->name,
                        'source_name' => $photo->source_name,
                        'path' => "teams/{$team->id}/galleries/{$gallery->ulid}/versions/{$photoIndex}-{$versionNumber}.jpg",
                        'size' => $size,
                        'stored_size' => (int) round($size * fake()->randomFloat(2, 0.5, 0.9)),
                        'status' => 'processed',
                        'uploaded_at' => $versionCreatedAt,
                        'created_at' => $versionCreatedAt,
                    ]);
                }
            }
        }
    }

    private function seedContracts(Team $team, Carbon $teamCreatedAt, Carbon $now): void
    {
        $contractCount = fake()->numberBetween(0, 3);

        if ($contractCount === 0) {
            return;
        }

        foreach (range(1, $contractCount) as $contractIndex) {
            $contractCreatedAt = $this->randomDate($teamCreatedAt, $now);

            Contract::factory()->for($team)->create([
                'title' => 'Demo contract '.$contractIndex,
                'description' => fake()->sentence(),
                'location' => fake()->city(),
                'shooting_date' => $contractCreatedAt->copy()->addDays(fake()->numberBetween(-20, 20)),
                'executed_at' => fake()->boolean(50)
                    ? $this->randomDate($contractCreatedAt, $now)
                    : null,
                'created_at' => $contractCreatedAt,
            ]);
        }
    }

    private function randomDate(Carbon $start, Carbon $end): Carbon
    {
        if ($end->lessThanOrEqualTo($start)) {
            return $start->copy();
        }

        $date = Carbon::instance(fake()->dateTimeBetween($start, $end));

        // MySQL TIMESTAMP columns reject wall-clock times that do not exist
        // because of a daylight-saving jump (for example 02:xx in Europe).
        if ($date->hour === 2) {
            $date->addHour();
        }

        return $date;
    }

    /**
     * Remove earlier demo runs so the seeder can be run repeatedly without
     * touching real users or their data.
     */
    private function deleteExistingDemoData(): void
    {
        $userIds = User::query()
            ->where('email', 'like', 'stats-demo-%@example.test')
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return;
        }

        $teamIds = Team::query()->whereIn('user_id', $userIds)->pluck('id');
        $galleryIds = Gallery::query()->whereIn('team_id', $teamIds)->pluck('id');
        $photoIds = Photo::query()->whereIn('gallery_id', $galleryIds)->pluck('id');

        PhotoVersion::query()->whereIn('photo_id', $photoIds)->delete();
        Photo::query()->whereIn('id', $photoIds)->delete();
        Gallery::query()->whereIn('id', $galleryIds)->delete();
        Contract::query()->whereIn('team_id', $teamIds)->delete();
        DB::table('subscriptions')->whereIn('team_id', $teamIds)->delete();
        Team::query()->whereIn('id', $teamIds)->delete();
        User::query()->whereIn('id', $userIds)->delete();
    }
}
