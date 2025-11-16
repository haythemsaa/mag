<?php

namespace Database\Seeders;

use App\Models\DashcamEvent;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class DashcamEventSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Please run OrganizationSeeder first.');

            return;
        }

        $this->command->info('Seeding dashcam events...');

        foreach ($organizations as $organization) {
            // Pending review (various severity)
            DashcamEvent::factory()->forOrganization($organization)->harshBraking()->pendingReview()->count(3)->create();
            DashcamEvent::factory()->forOrganization($organization)->speeding()->pendingReview()->count(2)->create();
            DashcamEvent::factory()->forOrganization($organization)->pendingReview()->count(5)->create();

            // Reviewed
            DashcamEvent::factory()->forOrganization($organization)->reviewed()->count(8)->create();

            // Critical events requiring coaching
            DashcamEvent::factory()->forOrganization($organization)->collision()->critical()->coachingRequired()->count(2)->create();
            DashcamEvent::factory()->forOrganization($organization)->critical()->coachingRequired()->count(3)->create();
        }

        $this->command->info('Dashcam events seeded successfully!');
    }
}
