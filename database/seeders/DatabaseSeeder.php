<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\SubCategory;
use App\Models\Unit;
use App\Models\FlagTemplate;
use App\Models\ReferenceTemplate;
use App\Models\ResultTemplate;
use App\Models\LabTest;
use App\Models\TestParameter;
use App\Models\ReferenceInterval;
use App\Models\Doctor;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Secure Admin User
        // In production an explicit ADMIN_PASSWORD is required; never ship the dev default.
        $password = env('ADMIN_PASSWORD');
        if (empty($password)) {
            if (app()->isProduction()) {
                throw new \RuntimeException('Set ADMIN_PASSWORD in .env before seeding a production database.');
            }
            $password = '12345678';
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'lab@gmail.com')],
            [
                'name' => env('ADMIN_NAME', 'Safwan'),
                'password' => $password,
            ]
        );

        // 2. Load and parse the CSV data
        $this->call(CsvDataSeeder::class);

        // 3. Create default report templates
        $this->call(ReportTemplateSeeder::class);
    }
}
