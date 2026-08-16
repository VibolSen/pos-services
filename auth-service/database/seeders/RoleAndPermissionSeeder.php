<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Define Granular Permissions List
        $permissionsByGroup = [
            'POS' => [
                'pos.checkout' => 'Execute cashier POS checkout & process tenders',
                'pos.cart.hold' => 'Hold and resume cashier carts',
                'pos.discount.apply' => 'Apply line item & cart discounts',
                'pos.void' => 'Void active POS transaction lines or carts',
                'pos.shift.manage' => 'Open and close cashier register cash float',
            ],
            'Catalog' => [
                'catalog.view' => 'View products, categories, and brand catalogs',
                'catalog.manage' => 'Create, edit, import, and delete products, categories, and brands',
                'catalog.pricing' => 'Manage product pricing, cost prices, and barcode rules',
            ],
            'Inventory' => [
                'inventory.view' => 'View stock balances and inventory movement ledgers',
                'inventory.adjust' => 'Submit manual stock count adjustments and wastage logs',
                'inventory.transfer' => 'Dispatch and receive inter-outlet stock transfers',
                'inventory.po' => 'Create and process supplier purchase order receiving',
            ],
            'HRM' => [
                'hrm.employees.view' => 'View employee profiles and organization structure',
                'hrm.employees.manage' => 'Create, edit, and assign employee PIN codes & departments',
            ],
            'Finance' => [
                'finance.reconciliation' => 'Perform daily ABA Bakong settlement reconciliation',
                'finance.expenses' => 'Create and review company expense and income ledgers',
                'finance.bank_accounts' => 'Manage company bank accounts and settlement targets',
            ],
            'Reports' => [
                'reports.sales' => 'View executive sales revenue & cashier shift analytics',
                'reports.financial' => 'View profit & loss, tax summaries, and gross margin reports',
            ],
            'Security' => [
                'security.roles.manage' => 'Manage dynamic roles and custom permission matrices',
                'security.users.manage' => 'Create, edit, and assign user accounts and roles',
                'security.audit.view' => 'Inspect immutable security audit trail logs',
                'security.outlets.manage' => 'Provision and configure multi-tenant store outlets',
            ],
        ];

        $permissionIdsMap = [];

        foreach ($permissionsByGroup as $group => $perms) {
            foreach ($perms as $permName => $description) {
                $existing = DB::table('permissions')->where('name', $permName)->first();
                if ($existing) {
                    $permissionIdsMap[$permName] = $existing->id;
                } else {
                    $id = (string) Str::uuid();
                    DB::table('permissions')->insert([
                        'id' => $id,
                        'name' => $permName,
                        'group' => $group,
                        'description' => $description,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $permissionIdsMap[$permName] = $id;
                }
            }
        }

        // 2. Define 8 Default Role Templates
        $rolesData = [
            [
                'name' => 'Super Admin',
                'slug' => 'super_admin',
                'description' => 'Platform / Organization Owner with complete administrative control over all multi-tenant features and databases.',
                'is_system' => true,
                'permissions' => array_keys($permissionIdsMap), // All permissions
            ],
            [
                'name' => 'Company Administrator',
                'slug' => 'admin',
                'description' => 'Company Owner with full operational authority over store outlets, products, stock, staff, and financial reconciliation.',
                'is_system' => true,
                'permissions' => [
                    'pos.checkout', 'pos.cart.hold', 'pos.discount.apply', 'pos.void', 'pos.shift.manage',
                    'catalog.view', 'catalog.manage', 'catalog.pricing',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.po',
                    'hrm.employees.view', 'hrm.employees.manage',
                    'finance.reconciliation', 'finance.expenses', 'finance.bank_accounts',
                    'reports.sales', 'reports.financial',
                    'security.roles.manage', 'security.users.manage', 'security.outlets.manage',
                ],
            ],
            [
                'name' => 'Outlet Manager',
                'slug' => 'outlet_manager',
                'description' => 'Store Operations Manager overseeing local store inventory, cashier shift audits, and local store staff.',
                'is_system' => true,
                'permissions' => [
                    'pos.checkout', 'pos.cart.hold', 'pos.discount.apply', 'pos.void', 'pos.shift.manage',
                    'catalog.view',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.po',
                    'hrm.employees.view',
                    'reports.sales',
                ],
            ],
            [
                'name' => 'Store Supervisor',
                'slug' => 'supervisor',
                'description' => 'Floor Supervisor authorizing void sales, discount overrides, return quotes, and shift float variances.',
                'is_system' => true,
                'permissions' => [
                    'pos.checkout', 'pos.cart.hold', 'pos.discount.apply', 'pos.void', 'pos.shift.manage',
                    'catalog.view', 'inventory.view',
                ],
            ],
            [
                'name' => 'Cashier',
                'slug' => 'cashier',
                'description' => 'POS Terminal Operator executing fast customer checkouts, barcode scanning, split tender, and shift open/close.',
                'is_system' => true,
                'permissions' => [
                    'pos.checkout', 'pos.cart.hold', 'pos.shift.manage', 'catalog.view',
                ],
            ],
            [
                'name' => 'Stock Clerk',
                'slug' => 'inventory_clerk',
                'description' => 'Warehouse Clerk handling PO stock receiving, manual stock count adjustments, and inter-outlet stock transfers.',
                'is_system' => true,
                'permissions' => [
                    'catalog.view',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.po',
                ],
            ],
            [
                'name' => 'Finance Accountant',
                'slug' => 'accountant',
                'description' => 'Finance Specialist performing daily ABA Bakong settlement reconciliation, expenses, and margin reports.',
                'is_system' => true,
                'permissions' => [
                    'finance.reconciliation', 'finance.expenses', 'finance.bank_accounts',
                    'reports.sales', 'reports.financial',
                ],
            ],
            [
                'name' => 'User',
                'slug' => 'user',
                'description' => 'Registered general user with access to public platform features and personal profile.',
                'is_system' => true,
                'permissions' => [
                    'catalog.view',
                ],
            ],
            [
                'name' => 'Customer',
                'slug' => 'customer',
                'description' => 'Retail End-Consumer browsing public online catalog and paying via instant KHQR.',
                'is_system' => true,
                'permissions' => [
                    'catalog.view',
                ],
            ],
        ];

        foreach ($rolesData as $roleInfo) {
            $existingRole = DB::table('roles')->where('slug', $roleInfo['slug'])->first();
            $roleId = $existingRole ? $existingRole->id : (string) Str::uuid();

            if (!$existingRole) {
                DB::table('roles')->insert([
                    'id' => $roleId,
                    'company_id' => null,
                    'name' => $roleInfo['name'],
                    'slug' => $roleInfo['slug'],
                    'description' => $roleInfo['description'],
                    'is_system' => $roleInfo['is_system'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Sync Role Permissions in pivot table
            DB::table('role_permissions')->where('role_id', $roleId)->delete();

            foreach ($roleInfo['permissions'] as $pName) {
                if (isset($permissionIdsMap[$pName])) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionIdsMap[$pName],
                    ]);
                }
            }

            // Associate users with matching role slug to this role_id
            DB::table('users')->where('role', $roleInfo['slug'])->update(['role_id' => $roleId]);
        }
    }
}
