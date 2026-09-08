<?php

namespace Database\Seeders;

use App\Enums\AcademicTrack;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\AssetLoan;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Event;
use App\Models\FinancialCategory;
use App\Models\FinancialRecord;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DummyDataSeeder extends Seeder
{
    private const string GUARD = 'logto';

    private const string COVER_URL = 'https://picsum.photos/seed/%s/800/400';

    public function run(): void
    {
        $this->command?->info('Seeding dummy data...');

        $this->seedMembers();
        $this->seedEvents();
        $this->seedDocuments();
        $this->seedFinancials();
        $this->seedAssets();

        $this->command?->info('Dummy data seeded successfully.');
    }

    private function seedMembers(): void
    {
        $this->command?->info('  → Members...');

        $roles = ['executive', 'member', 'alumni'];

        User::factory(20)->create()->each(function (User $user) use ($roles) {
            $roleName = fake()->randomElement($roles);
            $role = Role::findByName($roleName, self::GUARD);
            $user->assignRole($role);
        });
    }

    private function seedEvents(): void
    {
        $this->command?->info('  → Events...');

        $users = User::all();

        Event::factory(10)->create([
            'created_by' => $users->random()->id,
        ])->each(function (Event $event) use ($users) {
            $this->attachCover($event);

            $attendeeCount = fake()->numberBetween(0, 15);
            $attendees = $users->random(min($attendeeCount, $users->count()));

            foreach ($attendees as $attendee) {
                Attendance::factory()->create([
                    'event_id' => $event->id,
                    'user_id' => $attendee->id,
                    'checked_in_at' => $event->event_date->isPast()
                        ? fake()->dateTimeBetween($event->event_date, $event->event_date->copy()->addHours(3))
                        : fake()->dateTimeBetween('now', '+1 week'),
                ]);
            }
        });
    }

    private function seedDocuments(): void
    {
        $this->command?->info('  → Documents...');

        $categories = DocumentCategory::factory(5)->create();
        $users = User::all();

        Document::factory(15)->create([
            'created_by' => $users->random()->id,
        ])->each(function (Document $doc) use ($categories, $users) {
            $doc->update(['category_id' => $categories->random()->id]);

            DocumentVersion::factory()->create([
                'document_id' => $doc->id,
                'version_number' => '1',
                'uploaded_by' => $users->random()->id,
            ]);
        });
    }

    private function seedFinancials(): void
    {
        $this->command?->info('  → Financial records...');

        $categories = FinancialCategory::factory(6)->create();
        $users = User::all();

        FinancialRecord::factory(30)->create([
            'category_id' => $categories->random()->id,
            'recorded_by' => $users->random()->id,
        ]);
    }

    private function seedAssets(): void
    {
        $this->command?->info('  → Assets...');

        $users = User::all();

        Asset::factory(15)->create();

        Asset::factory(5)->borrowed()->create()->each(function (Asset $asset) use ($users) {
            AssetLoan::factory()->create([
                'asset_id' => $asset->id,
                'borrower_id' => $users->random()->id,
                'issued_by' => $users->random()->id,
            ]);
        });

        AssetLoan::factory(3)->overdue()->create()->each(function (AssetLoan $loan) use ($users) {
            $loan->asset()->update(['status' => 'borrowed']);
        });
    }

    private function attachCover(Event $event): void
    {
        try {
            $seed = Str::slug($event->title);
            $url = sprintf(self::COVER_URL, $seed);
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $tmpFile = storage_path('app/cover-' . $event->id . '.jpg');
                File::put($tmpFile, $response->body());
                $event->addMedia($tmpFile)->toMediaCollection('cover');
                File::delete($tmpFile);
            }
        } catch (\Throwable) {
            // Skip cover if download fails
        }
    }
}
