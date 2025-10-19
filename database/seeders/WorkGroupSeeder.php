<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\UserSystem;
use App\Models\WorkGroup;
use App\Models\WorkGroupTenantEntity;
use Illuminate\Database\Seeder;

class WorkGroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Work Group 1
        $workGroup1 = WorkGroup::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'name' => 'Work Group 1',
        ]);

        // Create Work Group 2
        $workGroup2 = WorkGroup::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'name' => 'Work Group 2',
        ]);

        // Get user systems
        $userSystems = UserSystem::orderBy('created_at')->get();

        if ($userSystems->count() >= 5) {
            // Link first 2 users to Work Group 1
            $workGroup1->userSystems()->attach([
                $userSystems[0]->id,
                $userSystems[1]->id,
            ]);

            // Link remaining 3 users to Work Group 2
            $workGroup2->userSystems()->attach([
                $userSystems[2]->id,
                $userSystems[3]->id,
                $userSystems[4]->id,
            ]);

            $this->command->info('Created 2 work groups and linked user systems');
        } else {
            $this->command->warn('Not enough user systems found. Please seed user systems first.');
        }

        // Get all tenants ordered by created_at
        $tenants = Tenant::orderBy('created_at')->get();

        if ($tenants->count() > 0) {
            // Work Group 1: Link to all tenants
            foreach ($tenants as $tenant) {
                WorkGroupTenantEntity::create([
                    'work_group_id' => $workGroup1->id,
                    'tenant_id' => $tenant->id,
                ]);
            }

            // Work Group 2: Link to first half of tenants
            $halfCount = (int) ceil($tenants->count() / 2);
            $firstHalfTenants = $tenants->take($halfCount);

            foreach ($firstHalfTenants as $tenant) {
                WorkGroupTenantEntity::create([
                    'work_group_id' => $workGroup2->id,
                    'tenant_id' => $tenant->id,
                ]);
            }

            $this->command->info('Linked work groups to tenants: WG1 to all tenants, WG2 to first half');
        } else {
            $this->command->warn('No tenants found. Please seed tenants first.');
        }
    }
}
