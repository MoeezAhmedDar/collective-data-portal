<?php

namespace Database\Seeders;

use App\Models\CarveOut;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use League\Csv\Reader;

class CarveOutSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->error('This seeder can only run in local or testing environment.');
            return;
        }
        CarveOut::truncate();

        $path = public_path('carve_out/carveOut_sample_UPDATED.csv');

        if (! file_exists($path)) {
            Log::error("CSV file not found: {$path}");
            $this->command?->error("CSV file not found: {$path}");
            return;
        }

        try {
            $csv = Reader::createFromPath($path, 'r');
            $csv->setHeaderOffset(0);
            $csv->setDelimiter(',');
            $csv->setEnclosure('"');
            $csv->setEscape('\\');

            $records = $csv->getRecords([
                'retailer_name',
                'email',
                'carve_outs',
                'location',
                'lp',
            ]);

            $created = 0;

            foreach ($records as $record) {
                if (empty(array_filter($record))) {
                    continue;
                }

                CarveOut::create([
                    'retailer_name' => trim($record['retailer_name'] ?? ''),
                    'email'         => trim($record['email'] ?? ''),
                    'carve_outs'    => trim($record['carve_outs'] ?? ''),
                    'location'      => trim($record['location'] ?? ''),
                    'lp'            => trim($record['lp'] ?? ''),
                ]);

                $created++;
            }

            $this->command?->info("Successfully imported {$created} carve-out records.");
        } catch (\League\Csv\Exception $e) {
            Log::error("CSV import failed: " . $e->getMessage());
            $this->command?->error("Failed to process CSV: " . $e->getMessage());
        } catch (\Throwable $e) {
            Log::error("Unexpected error during carve-out import: " . $e->getMessage());
            $this->command?->error("Unexpected error: " . $e->getMessage());
        }
    }
}
