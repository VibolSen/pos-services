<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 0. Seed Roles and Permissions First
        $this->call(RoleAndPermissionSeeder::class);
        // 1. Outlet & Register First
        $existingOutlet = DB::table('outlets')->where('code', 'PP-01')->first();
        if ($existingOutlet) {
            $outletId = $existingOutlet->id;
        } else {
            $outletId = (string) Str::uuid();
            DB::table('outlets')->insert([
                'id' => $outletId,
                'name' => 'Phnom Penh Main Outlet',
                'code' => 'PP-01',
                'address' => 'Monivong Blvd, Phnom Penh',
                'phone' => '+855 23 123 456',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingRegister = DB::table('registers')->where('code', 'REG-01')->first();
        if (!$existingRegister) {
            $registerId = (string) Str::uuid();
            DB::table('registers')->insert([
                'id' => $registerId,
                'outlet_id' => $outletId,
                'name' => 'Register #1',
                'code' => 'REG-01',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Seed Users across all 8 RBAC Roles
        $users = [
            [
                'email' => 'superadmin@pos.com',
                'name' => 'Platform Super Admin',
                'role' => 'super_admin',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'vibolsen2002@gmail.com',
                'name' => 'Vibol',
                'role' => 'super_admin',
                'password' => Hash::make('Vibol@2020'),
            ],
            [
                'email' => 'manager@pos.com',
                'name' => 'Outlet Manager',
                'role' => 'outlet_manager',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'supervisor@pos.com',
                'name' => 'Store Supervisor',
                'role' => 'supervisor',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'cashier@pos.com',
                'name' => 'John Cashier',
                'role' => 'cashier',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'inventory@pos.com',
                'name' => 'Stock Clerk',
                'role' => 'inventory_clerk',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'accountant@pos.com',
                'name' => 'Finance Accountant',
                'role' => 'accountant',
                'password' => Hash::make('password'),
            ],
            [
                'email' => 'customer@pos.com',
                'name' => 'Demo Customer',
                'role' => 'customer',
                'password' => Hash::make('password'),
            ],
        ];

        $adminUser = null;
        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'role' => $userData['role'],
                    'outlet_id' => $outletId,
                    'password' => $userData['password'],
                ]
            );
            if ($userData['role'] === 'super_admin') {
                $adminUser = $user;
            }
        }

        // 3. Categories
        $existingBev = DB::table('categories')->where('slug', 'beverages')->first();
        if ($existingBev) {
            $beveragesId = $existingBev->id;
        } else {
            $beveragesId = (string) Str::uuid();
            DB::table('categories')->insert([
                'id' => $beveragesId,
                'name' => 'Beverages',
                'slug' => 'beverages',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $existingSnk = DB::table('categories')->where('slug', 'snacks')->first();
        if ($existingSnk) {
            $snacksId = $existingSnk->id;
        } else {
            $snacksId = (string) Str::uuid();
            DB::table('categories')->insert([
                'id' => $snacksId,
                'name' => 'Snacks',
                'slug' => 'snacks',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. Products
        if (!DB::table('products')->where('sku', 'BEV-AME-01')->exists()) {
            $p1 = (string) Str::uuid();
            DB::table('products')->insert([
                'id' => $p1,
                'category_id' => $beveragesId,
                'name' => 'Iced Americano',
                'sku' => 'BEV-AME-01',
                'description' => 'Cold brew iced coffee',
                'cost_price' => 1.20,
                'selling_price' => 2.50,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('products')->where('sku', 'BEV-LAT-01')->exists()) {
            $p2 = (string) Str::uuid();
            DB::table('products')->insert([
                'id' => $p2,
                'category_id' => $beveragesId,
                'name' => 'Iced Latte',
                'sku' => 'BEV-LAT-01',
                'description' => 'Espresso with fresh milk',
                'cost_price' => 1.50,
                'selling_price' => 3.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!DB::table('products')->where('sku', 'SNK-CRO-01')->exists()) {
            $p3 = (string) Str::uuid();
            DB::table('products')->insert([
                'id' => $p3,
                'category_id' => $snacksId,
                'name' => 'Butter Croissant',
                'sku' => 'SNK-CRO-01',
                'description' => 'Freshly baked croissant',
                'cost_price' => 0.80,
                'selling_price' => 2.00,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Initial Inventory Balances
        $productIds = DB::table('products')->pluck('id')->toArray();
        foreach ($productIds as $productId) {
            $hasBal = DB::table('inventory_balances')->where('product_id', $productId)->exists();
            if (!$hasBal) {
                DB::table('inventory_balances')->insert([
                    'id' => (string) Str::uuid(),
                    'outlet_id' => $outletId,
                    'product_id' => $productId,
                    'on_hand' => 100,
                    'available' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('inventory_movements')->insert([
                    'id' => (string) Str::uuid(),
                    'outlet_id' => $outletId,
                    'product_id' => $productId,
                    'quantity_change' => 100,
                    'movement_type' => 'receive',
                    'created_by' => $adminUser ? $adminUser->id : (string) Str::uuid(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
