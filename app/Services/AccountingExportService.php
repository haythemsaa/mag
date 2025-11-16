<?php

namespace App\Services;

use App\Models\AccountingExport;
use App\Models\Contract;
use App\Models\Cost;
use App\Models\FuelTransaction;
use App\Models\Infraction;
use App\Models\Maintenance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class AccountingExportService
{
    /**
     * Process an accounting export
     */
    public function process(AccountingExport $export): void
    {
        try {
            $export->markAsStarted();

            // Get data based on export type
            $data = $this->getData($export);

            // Format data according to account mapping
            $formatted = $this->formatData($data, $export);

            // Generate file
            $filePath = $this->generateFile($formatted, $export);

            // Get file statistics
            $fileSize = Storage::disk('local')->size($filePath);
            $recordCount = $data->count();

            $export->markAsCompleted($filePath, $recordCount, $fileSize);
        } catch (\Exception $e) {
            $export->markAsFailed($e->getMessage());
            throw $e;
        }
    }

    /**
     * Get data based on export type
     */
    protected function getData(AccountingExport $export): Collection
    {
        return match ($export->type) {
            'costs' => $this->getCostsData($export),
            'fuel' => $this->getFuelData($export),
            'maintenance' => $this->getMaintenanceData($export),
            'contracts' => $this->getContractsData($export),
            'all' => $this->getAllData($export),
            'custom' => $this->getCustomData($export),
            default => collect(),
        };
    }

    /**
     * Get costs data
     */
    protected function getCostsData(AccountingExport $export): Collection
    {
        $query = Cost::where('organization_id', $export->organization_id)
            ->whereBetween('cost_date', [$export->start_date, $export->end_date])
            ->with(['vehicle', 'site']);

        if ($export->filters && isset($export->filters['vehicle_ids'])) {
            $query->whereIn('vehicle_id', $export->filters['vehicle_ids']);
        }

        if ($export->filters && isset($export->filters['cost_types'])) {
            $query->whereIn('cost_type', $export->filters['cost_types']);
        }

        return $query->get()->map(function ($cost) use ($export) {
            $account = $this->getAccountForCostType($cost->cost_type, $export->account_mapping);

            return [
                'date' => $cost->cost_date->format('Y-m-d'),
                'document_number' => $cost->invoice_number ?? $cost->reference_number,
                'description' => $this->formatDescription('Cost', $cost),
                'account_code' => $account,
                'debit' => $cost->amount_ttc,
                'credit' => 0,
                'vehicle' => $cost->vehicle?->registration_number,
                'reference' => $cost->reference_number,
                'type' => $cost->cost_type,
            ];
        });
    }

    /**
     * Get fuel data
     */
    protected function getFuelData(AccountingExport $export): Collection
    {
        $query = FuelTransaction::where('organization_id', $export->organization_id)
            ->whereBetween('transaction_date', [$export->start_date, $export->end_date])
            ->with(['vehicle', 'site', 'driver']);

        if ($export->filters && isset($export->filters['vehicle_ids'])) {
            $query->whereIn('vehicle_id', $export->filters['vehicle_ids']);
        }

        return $query->get()->map(function ($fuel) use ($export) {
            $account = $export->account_mapping['fuel_account'] ?? '6061';

            return [
                'date' => $fuel->transaction_date->format('Y-m-d'),
                'document_number' => $fuel->invoice_number,
                'description' => $this->formatDescription('Fuel', $fuel),
                'account_code' => $account,
                'debit' => $fuel->total_cost,
                'credit' => 0,
                'vehicle' => $fuel->vehicle?->registration_number,
                'reference' => $fuel->fuel_number,
                'quantity' => $fuel->quantity_liters,
                'unit_price' => $fuel->unit_price,
            ];
        });
    }

    /**
     * Get maintenance data
     */
    protected function getMaintenanceData(AccountingExport $export): Collection
    {
        $query = Maintenance::where('organization_id', $export->organization_id)
            ->whereBetween('scheduled_date', [$export->start_date, $export->end_date])
            ->whereIn('status', ['completed', 'invoiced'])
            ->with(['vehicle', 'workshop']);

        if ($export->filters && isset($export->filters['vehicle_ids'])) {
            $query->whereIn('vehicle_id', $export->filters['vehicle_ids']);
        }

        return $query->get()->map(function ($maintenance) use ($export) {
            $account = $export->account_mapping['maintenance_account'] ?? '6155';

            return [
                'date' => $maintenance->completed_at?->format('Y-m-d') ?? $maintenance->scheduled_date->format('Y-m-d'),
                'document_number' => $maintenance->invoice_number,
                'description' => $this->formatDescription('Maintenance', $maintenance),
                'account_code' => $account,
                'debit' => $maintenance->total_cost,
                'credit' => 0,
                'vehicle' => $maintenance->vehicle?->registration_number,
                'reference' => $maintenance->maintenance_number,
                'workshop' => $maintenance->workshop?->name,
            ];
        });
    }

    /**
     * Get contracts data
     */
    protected function getContractsData(AccountingExport $export): Collection
    {
        $query = Contract::where('organization_id', $export->organization_id)
            ->where(function ($q) use ($export) {
                $q->whereBetween('start_date', [$export->start_date, $export->end_date])
                    ->orWhereBetween('end_date', [$export->start_date, $export->end_date]);
            })
            ->with(['vehicle', 'supplier']);

        if ($export->filters && isset($export->filters['vehicle_ids'])) {
            $query->whereIn('vehicle_id', $export->filters['vehicle_ids']);
        }

        return $query->get()->map(function ($contract) use ($export) {
            $account = $this->getAccountForContractType($contract->contract_type, $export->account_mapping);

            return [
                'date' => $contract->start_date->format('Y-m-d'),
                'document_number' => $contract->contract_number,
                'description' => $this->formatDescription('Contract', $contract),
                'account_code' => $account,
                'debit' => $contract->monthly_cost ?? $contract->total_cost,
                'credit' => 0,
                'vehicle' => $contract->vehicle?->registration_number,
                'reference' => $contract->contract_number,
                'supplier' => $contract->supplier?->name,
                'type' => $contract->contract_type,
            ];
        });
    }

    /**
     * Get all data combined
     */
    protected function getAllData(AccountingExport $export): Collection
    {
        $costs = $this->getCostsData($export);
        $fuel = $this->getFuelData($export);
        $maintenance = $this->getMaintenanceData($export);
        $contracts = $this->getContractsData($export);

        // Add infractions if available
        $infractions = $this->getInfractionsData($export);

        return collect()
            ->merge($costs)
            ->merge($fuel)
            ->merge($maintenance)
            ->merge($contracts)
            ->merge($infractions)
            ->sortBy('date')
            ->values();
    }

    /**
     * Get infractions data
     */
    protected function getInfractionsData(AccountingExport $export): Collection
    {
        $query = Infraction::where('organization_id', $export->organization_id)
            ->whereBetween('infraction_date', [$export->start_date, $export->end_date])
            ->whereIn('status', ['paid', 'pending_payment'])
            ->with(['vehicle', 'driver']);

        if ($export->filters && isset($export->filters['vehicle_ids'])) {
            $query->whereIn('vehicle_id', $export->filters['vehicle_ids']);
        }

        return $query->get()->map(function ($infraction) use ($export) {
            $account = $export->account_mapping['fine_account'] ?? '6712';

            return [
                'date' => $infraction->infraction_date->format('Y-m-d'),
                'document_number' => $infraction->reference_number,
                'description' => $this->formatDescription('Infraction', $infraction),
                'account_code' => $account,
                'debit' => $infraction->amount,
                'credit' => 0,
                'vehicle' => $infraction->vehicle?->registration_number,
                'reference' => $infraction->infraction_number,
                'driver' => $infraction->driver?->name,
            ];
        });
    }

    /**
     * Get custom filtered data
     */
    protected function getCustomData(AccountingExport $export): Collection
    {
        // For custom exports, combine all data and apply filters
        return $this->getAllData($export);
    }

    /**
     * Format data with account codes and structure
     */
    protected function formatData(Collection $data, AccountingExport $export): Collection
    {
        // Add summary row if needed
        if ($data->isNotEmpty()) {
            $total = $data->sum('debit') - $data->sum('credit');

            $data->push([
                'date' => '',
                'document_number' => '',
                'description' => 'TOTAL',
                'account_code' => '',
                'debit' => $data->sum('debit'),
                'credit' => $data->sum('credit'),
                'vehicle' => '',
                'reference' => '',
            ]);
        }

        return $data;
    }

    /**
     * Generate export file
     */
    protected function generateFile(Collection $data, AccountingExport $export): string
    {
        $filename = sprintf(
            'exports/%s_%s_%s_%s.%s',
            $export->type,
            $export->start_date->format('Ymd'),
            $export->end_date->format('Ymd'),
            now()->format('YmdHis'),
            $this->getFileExtension($export->format)
        );

        return match ($export->format) {
            'csv' => $this->generateCsv($data, $filename),
            'json' => $this->generateJson($data, $filename),
            'excel' => $this->generateCsv($data, $filename), // Simplified: CSV for now, can use PhpSpreadsheet for real Excel
            'xml' => $this->generateXml($data, $filename),
            default => $this->generateCsv($data, $filename),
        };
    }

    /**
     * Generate CSV file
     */
    protected function generateCsv(Collection $data, string $filename): string
    {
        $csv = [];

        // Add header
        if ($data->isNotEmpty()) {
            $csv[] = array_keys($data->first());
        }

        // Add data rows
        foreach ($data as $row) {
            $csv[] = array_values($row);
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        // Store file
        Storage::disk('local')->put($filename, $csvContent);

        return $filename;
    }

    /**
     * Generate JSON file
     */
    protected function generateJson(Collection $data, string $filename): string
    {
        $json = json_encode($data->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        Storage::disk('local')->put($filename, $json);

        return $filename;
    }

    /**
     * Generate XML file
     */
    protected function generateXml(Collection $data, string $filename): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><accounting_export></accounting_export>');

        $xml->addChild('generated_at', now()->toIso8601String());
        $xml->addChild('record_count', $data->count());

        $entries = $xml->addChild('entries');

        foreach ($data as $row) {
            $entry = $entries->addChild('entry');
            foreach ($row as $key => $value) {
                $entry->addChild($key, htmlspecialchars($value ?? ''));
            }
        }

        Storage::disk('local')->put($filename, $xml->asXML());

        return $filename;
    }

    /**
     * Get file extension for format
     */
    protected function getFileExtension(string $format): string
    {
        return match ($format) {
            'excel' => 'xlsx',
            default => $format,
        };
    }

    /**
     * Get account code for cost type
     */
    protected function getAccountForCostType(string $costType, ?array $mapping): string
    {
        $default = AccountingExport::getDefaultAccountMapping();
        $mapping = $mapping ?? $default;

        return match ($costType) {
            'fuel' => $mapping['fuel_account'] ?? $default['fuel_account'],
            'maintenance' => $mapping['maintenance_account'] ?? $default['maintenance_account'],
            'insurance' => $mapping['insurance_account'] ?? $default['insurance_account'],
            'tax' => $mapping['tax_account'] ?? $default['tax_account'],
            'toll' => $mapping['toll_account'] ?? $default['toll_account'],
            'tire' => $mapping['tire_account'] ?? $default['tire_account'],
            'fine' => $mapping['fine_account'] ?? $default['fine_account'],
            default => '6000', // General expenses
        };
    }

    /**
     * Get account code for contract type
     */
    protected function getAccountForContractType(string $contractType, ?array $mapping): string
    {
        $default = AccountingExport::getDefaultAccountMapping();
        $mapping = $mapping ?? $default;

        return match ($contractType) {
            'insurance' => $mapping['insurance_account'] ?? $default['insurance_account'],
            'lease', 'rental' => $mapping['lease_account'] ?? $default['lease_account'],
            'maintenance' => $mapping['maintenance_account'] ?? $default['maintenance_account'],
            default => '6132', // General lease/rental
        };
    }

    /**
     * Format description for entry
     */
    protected function formatDescription(string $type, $model): string
    {
        return match ($type) {
            'Cost' => sprintf(
                '%s - %s (%s)',
                $type,
                $model->cost_type,
                $model->vehicle?->registration_number ?? 'N/A'
            ),
            'Fuel' => sprintf(
                'Fuel - %.2fL @ %.2f€/L (%s)',
                $model->quantity_liters,
                $model->unit_price,
                $model->vehicle?->registration_number ?? 'N/A'
            ),
            'Maintenance' => sprintf(
                'Maintenance - %s (%s)',
                $model->maintenance_type,
                $model->vehicle?->registration_number ?? 'N/A'
            ),
            'Contract' => sprintf(
                'Contract %s - %s (%s)',
                $model->contract_type,
                $model->supplier?->name ?? 'N/A',
                $model->vehicle?->registration_number ?? 'N/A'
            ),
            'Infraction' => sprintf(
                'Fine - %s (%s)',
                $model->infraction_type,
                $model->vehicle?->registration_number ?? 'N/A'
            ),
            default => $type,
        };
    }
}
