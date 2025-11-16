<?php

namespace Database\Seeders;

use App\Models\AccountingExport;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AccountingExportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->command->warn('No organizations found. Please run OrganizationSeeder first.');

            return;
        }

        $this->command->info('Seeding accounting exports...');

        foreach ($organizations as $organization) {
            $this->seedExportsForOrganization($organization);
        }

        $this->command->info('Accounting exports seeded successfully!');
    }

    /**
     * Seed exports for a specific organization
     */
    protected function seedExportsForOrganization(Organization $organization): void
    {
        // Pending exports (waiting to be processed)
        AccountingExport::factory()
            ->forOrganization($organization)
            ->pending()
            ->costs()
            ->csv()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->pending()
            ->fuel()
            ->excel()
            ->create();

        // Processing export (currently being generated)
        AccountingExport::factory()
            ->forOrganization($organization)
            ->processing()
            ->all()
            ->json()
            ->create();

        // Completed exports (various types and formats)
        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->costs()
            ->csv()
            ->downloaded()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->fuel()
            ->excel()
            ->downloaded()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->maintenance()
            ->json()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->contracts()
            ->xml()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->all()
            ->csv()
            ->lastMonth()
            ->downloaded()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->costs()
            ->excel()
            ->thisMonth()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->completed()
            ->fuel()
            ->json()
            ->lastMonth()
            ->downloaded()
            ->create();

        // Failed exports (for testing error handling)
        AccountingExport::factory()
            ->forOrganization($organization)
            ->failed()
            ->all()
            ->csv()
            ->create();

        AccountingExport::factory()
            ->forOrganization($organization)
            ->failed()
            ->maintenance()
            ->excel()
            ->create();
    }
}
