<?php

namespace Database\Factories;

use App\Models\AccountingExport;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AccountingExport>
 */
class AccountingExportFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = AccountingExport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organization = Organization::inRandomOrder()->first() ?? Organization::factory()->create();
        $user = User::where('organization_id', $organization->id)->inRandomOrder()->first()
            ?? User::factory()->create(['organization_id' => $organization->id]);

        $startDate = fake()->dateTimeBetween('-3 months', '-1 month');
        $endDate = (clone $startDate)->modify('+'.fake()->numberBetween(1, 30).' days');

        return [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'type' => fake()->randomElement(['costs', 'fuel', 'maintenance', 'contracts', 'all']),
            'format' => fake()->randomElement(['csv', 'excel', 'json', 'xml']),
            'status' => 'pending',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'account_mapping' => AccountingExport::getDefaultAccountMapping(),
            'filters' => null,
            'file_path' => null,
            'records_exported' => null,
            'file_size_bytes' => null,
            'started_at' => null,
            'completed_at' => null,
            'processing_time_seconds' => null,
            'error_message' => null,
            'downloaded_by' => null,
            'downloaded_at' => null,
            'download_count' => 0,
        ];
    }

    /**
     * Indicate export is pending
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'file_path' => null,
        ]);
    }

    /**
     * Indicate export is processing
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now()->subMinutes(fake()->numberBetween(1, 30)),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate export is completed
     */
    public function completed(): static
    {
        $startedAt = fake()->dateTimeBetween('-7 days', '-1 day');
        $processingTime = fake()->numberBetween(5, 300);
        $completedAt = (clone $startedAt)->modify("+{$processingTime} seconds");
        $recordsExported = fake()->numberBetween(10, 1000);
        $fileSizeBytes = $recordsExported * fake()->numberBetween(100, 500);

        return $this->state(function (array $attributes) use ($startedAt, $completedAt, $processingTime, $recordsExported, $fileSizeBytes) {
            $format = $attributes['format'];
            $type = $attributes['type'];
            $extension = $format === 'excel' ? 'xlsx' : $format;

            return [
                'status' => 'completed',
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'processing_time_seconds' => $processingTime,
                'file_path' => "exports/{$type}_".fake()->numerify('########').'.'.$extension,
                'records_exported' => $recordsExported,
                'file_size_bytes' => $fileSizeBytes,
                'error_message' => null,
            ];
        });
    }

    /**
     * Indicate export has failed
     */
    public function failed(): static
    {
        $startedAt = fake()->dateTimeBetween('-7 days', '-1 day');
        $processingTime = fake()->numberBetween(1, 60);
        $completedAt = (clone $startedAt)->modify("+{$processingTime} seconds");

        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
            'processing_time_seconds' => $processingTime,
            'error_message' => fake()->randomElement([
                'Database connection timeout',
                'Insufficient memory',
                'No data found for specified date range',
                'Export file generation failed',
                'Invalid account mapping configuration',
            ]),
        ]);
    }

    /**
     * Export type: costs
     */
    public function costs(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'costs',
        ]);
    }

    /**
     * Export type: fuel
     */
    public function fuel(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fuel',
        ]);
    }

    /**
     * Export type: maintenance
     */
    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'maintenance',
        ]);
    }

    /**
     * Export type: contracts
     */
    public function contracts(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'contracts',
        ]);
    }

    /**
     * Export type: all
     */
    public function all(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'all',
        ]);
    }

    /**
     * Export format: CSV
     */
    public function csv(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'csv',
        ]);
    }

    /**
     * Export format: Excel
     */
    public function excel(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'excel',
        ]);
    }

    /**
     * Export format: JSON
     */
    public function json(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'json',
        ]);
    }

    /**
     * Export format: XML
     */
    public function xml(): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => 'xml',
        ]);
    }

    /**
     * Export has been downloaded
     */
    public function downloaded(): static
    {
        return $this->state(function (array $attributes) {
            $user = User::find($attributes['user_id']);

            return [
                'downloaded_by' => $user?->email ?? fake()->email(),
                'downloaded_at' => now()->subDays(fake()->numberBetween(0, 7)),
                'download_count' => fake()->numberBetween(1, 10),
            ];
        });
    }

    /**
     * For a specific organization
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(function (array $attributes) use ($organization) {
            $user = User::where('organization_id', $organization->id)->inRandomOrder()->first()
                ?? User::factory()->create(['organization_id' => $organization->id]);

            return [
                'organization_id' => $organization->id,
                'user_id' => $user->id,
            ];
        });
    }

    /**
     * Export from this month
     */
    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
        ]);
    }

    /**
     * Export from last month
     */
    public function lastMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => now()->subMonth()->startOfMonth(),
            'end_date' => now()->subMonth()->endOfMonth(),
        ]);
    }
}
