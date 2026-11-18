<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development only. It refuses to run in any other environment,
 * so test/dummy data can never reach production.
 */
final class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalDemoSeeder skipped: it only runs in the "local" environment.');

            return;
        }

        $demoBranches = [
            'DEMO-1' => 'Demo Branch One',
            'DEMO-2' => 'Demo Branch Two',
        ];

        foreach ($demoBranches as $code => $name) {
            if (Branch::query()->where('code', $code)->exists()) {
                continue;
            }

            $branch = Branch::factory()->create(['code' => $code, 'name' => $name]);

            Warehouse::factory()->create([
                'branch_id' => $branch->getKey(),
                'code' => $code . '-ST',
                'name' => $name . ' - Back Store',
            ]);
        }
    }
}
