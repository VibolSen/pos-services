<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Seed the default "Platform Owner" tenant for CodeBridges internal use
        $platformTenantId = (string) Str::uuid();

        $existing = DB::table('tenants')->where('slug', 'codebridge-platform')->first();
        if (!$existing) {
            DB::table('tenants')->insert([
                'id'            => $platformTenantId,
                'name'          => 'CodeBridges Platform',
                'slug'          => 'codebridge-platform',
                'company_code'  => 'CB-0001',
                'client_tier'   => 'enterprise_org',
                'status'        => 'active',
                'email'         => 'platform@codebridges.io',
                'phone'         => '+855 12 345 678',
                'address'       => 'Phnom Penh, Cambodia',
                'country'       => 'KH',
                'currency'      => 'USD',
                'max_outlets'   => 999,
                'max_registers' => 999,
                'max_users'     => 9999,
                'trial_ends_at' => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('tenant_subscriptions')->insert([
                'id'            => (string) Str::uuid(),
                'tenant_id'     => $platformTenantId,
                'plan_name'     => 'Enterprise Ultimate',
                'billing_cycle' => 'yearly',
                'price'         => 0,
                'currency'      => 'USD',
                'status'        => 'active',
                'starts_at'     => now(),
                'expires_at'    => now()->addYears(10),
                'notes'         => 'Internal platform owner account — perpetual license.',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } else {
            $platformTenantId = $existing->id;
        }

        // Assign all super_admin users to the platform tenant
        DB::table('users')->where('role', 'super_admin')->update(['tenant_id' => $platformTenantId]);

        // Seed a demo Business Runner tenant
        $bizTenantId = (string) Str::uuid();
        $existingBiz = DB::table('tenants')->where('slug', 'sunny-cafe-pp')->first();
        if (!$existingBiz) {
            DB::table('tenants')->insert([
                'id'            => $bizTenantId,
                'name'          => 'Sunny Cafe Phnom Penh',
                'slug'          => 'sunny-cafe-pp',
                'company_code'  => 'CB-0002',
                'client_tier'   => 'business_runner',
                'status'        => 'active',
                'email'         => 'admin@sunnycafe.kh',
                'phone'         => '+855 23 999 888',
                'address'       => 'Toul Kork, Phnom Penh',
                'country'       => 'KH',
                'currency'      => 'USD',
                'max_outlets'   => 3,
                'max_registers' => 6,
                'max_users'     => 20,
                'trial_ends_at' => now()->addDays(30),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DB::table('tenant_subscriptions')->insert([
                'id'            => (string) Str::uuid(),
                'tenant_id'     => $bizTenantId,
                'plan_name'     => 'Business Runner Pro',
                'billing_cycle' => 'monthly',
                'price'         => 49.00,
                'currency'      => 'USD',
                'status'        => 'active',
                'starts_at'     => now(),
                'expires_at'    => now()->addMonths(1),
                'notes'         => 'Demo SME Business Runner subscription.',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }
}
