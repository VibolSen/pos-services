<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Public Self-Service Tenant Registration.
     * POST /api/v1/tenants/register
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'nullable|email',
            'client_tier' => 'nullable|in:free_personal,business_runner,enterprise_org',
            'phone'       => 'nullable|string|max:30',
            'address'     => 'nullable|string|max:500',
            'country'     => 'nullable|string|max:10',
        ]);

        $clientTier = $request->client_tier ?? 'business_runner';
        $slug = Str::slug($request->name);
        $existing = DB::table('tenants')->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . Str::random(4);
        }

        // Ensure email is unique in tenants table without failing valid registrations
        $email = $request->email;
        if (empty($email)) {
            $email = $slug . '@codebridges.app';
        } else {
            $emailTaken = DB::table('tenants')->where('email', $email)->exists();
            if ($emailTaken) {
                $parts = explode('@', $email);
                $email = $parts[0] . '+' . Str::lower(Str::random(4)) . '@' . ($parts[1] ?? 'codebridges.app');
            }
        }

        $limits = match ($clientTier) {
            'enterprise_org'  => ['max_outlets' => 50, 'max_registers' => 200, 'max_users' => 500],
            'business_runner' => ['max_outlets' => 5,  'max_registers' => 15,  'max_users' => 50],
            default           => ['max_outlets' => 1,  'max_registers' => 2,   'max_users' => 5],
        };

        $id = (string) Str::uuid();

        DB::table('tenants')->insert([
            'id'            => $id,
            'name'          => $request->name,
            'slug'          => $slug,
            'company_code'  => 'CB-' . strtoupper(Str::random(6)),
            'client_tier'   => $clientTier,
            'status'        => 'trial',
            'email'         => $email,
            'phone'         => $request->phone,
            'address'       => $request->address,
            'country'       => $request->country ?? 'KH',
            'currency'      => 'USD',
            'max_outlets'   => $limits['max_outlets'],
            'max_registers' => $limits['max_registers'],
            'max_users'     => $limits['max_users'],
            'trial_ends_at' => now()->addDays(14),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $planMap = [
            'free_personal'   => ['plan' => 'Personal Solopreneur Free', 'price' => 0],
            'business_runner' => ['plan' => 'Business Runner Pro',       'price' => 49.00],
            'enterprise_org'  => ['plan' => 'Enterprise Organization',   'price' => 199.00],
        ];

        $planInfo = $planMap[$clientTier];

        DB::table('tenant_subscriptions')->insert([
            'id'            => (string) Str::uuid(),
            'tenant_id'     => $id,
            'plan_name'     => $planInfo['plan'],
            'billing_cycle' => 'monthly',
            'price'         => $planInfo['price'],
            'currency'      => 'USD',
            'status'        => 'pending',
            'starts_at'     => now(),
            'expires_at'    => now()->addDays(14),
            'notes'         => '14-day free trial period.',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // 3. Automatically provision Default Primary Store Outlet & Register for this tenant
        $outletId = (string) Str::uuid();
        $outletCode = 'STR-' . strtoupper(Str::random(4));
        DB::table('outlets')->insert([
            'id'             => $outletId,
            'name'           => $request->name . ' - Main Store',
            'code'           => $outletCode,
            'phone'          => $request->phone,
            'address'        => $request->address,
            'receipt_header' => 'Welcome to ' . $request->name,
            'receipt_footer' => 'Thank you for shopping with us!',
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        DB::table('registers')->insert([
            'id'         => (string) Str::uuid(),
            'outlet_id'  => $outletId,
            'name'       => 'Register #1 (Main)',
            'code'       => 'REG-01',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Link authenticated user to new tenant workspace
        if ($request->user()) {
            DB::table('users')->where('id', $request->user()->id)->update([
                'tenant_id' => $id,
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tenant workspace registered successfully! Your 14-day trial has started.',
            'data'    => array_merge(
                (array) DB::table('tenants')->where('id', $id)->first(),
                [
                    'outlet_id'   => $outletId,
                    'outlet_name' => $request->name . ' - Main Store',
                    'outlet_code' => $outletCode,
                ]
            ),
        ], 201);
    }

    /**
     * Super Admin: List all tenants.
     * GET /api/v1/super-admin/tenants
     */
    public function index(Request $request)
    {
        $query = DB::table('tenants')
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_tier')) {
            $query->where('client_tier', $request->client_tier);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%$q%")
                    ->orWhere('email', 'like', "%$q%")
                    ->orWhere('slug', 'like', "%$q%");
            });
        }

        $tenants = $query->get();

        // Attach subscription info
        foreach ($tenants as $tenant) {
            $tenant->subscription = DB::table('tenant_subscriptions')
                ->where('tenant_id', $tenant->id)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        $stats = [
            'total'          => DB::table('tenants')->count(),
            'active'         => DB::table('tenants')->where('status', 'active')->count(),
            'trial'          => DB::table('tenants')->where('status', 'trial')->count(),
            'suspended'      => DB::table('tenants')->where('status', 'suspended')->count(),
            'enterprise_org' => DB::table('tenants')->where('client_tier', 'enterprise_org')->count(),
            'business_runner'=> DB::table('tenants')->where('client_tier', 'business_runner')->count(),
            'free_personal'  => DB::table('tenants')->where('client_tier', 'free_personal')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $tenants,
            'stats'   => $stats,
        ]);
    }

    /**
     * Super Admin: View single tenant detail.
     * GET /api/v1/super-admin/tenants/{id}
     */
    public function show($id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        $tenant->subscriptions = DB::table('tenant_subscriptions')
            ->where('tenant_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $tenant->users_count = DB::table('users')->where('tenant_id', $id)->count();

        return response()->json([
            'success' => true,
            'data'    => $tenant,
        ]);
    }

    /**
     * Super Admin: Update tenant subscription plan.
     * PUT /api/v1/super-admin/tenants/{id}/subscription
     */
    public function updateSubscription(Request $request, $id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        $request->validate([
            'plan_name'     => 'required|string',
            'price'         => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,quarterly,yearly',
            'expires_at'    => 'nullable|date',
            'client_tier'   => 'nullable|in:free_personal,business_runner,enterprise_org',
        ]);

        DB::table('tenant_subscriptions')->insert([
            'id'            => (string) Str::uuid(),
            'tenant_id'     => $id,
            'plan_name'     => $request->plan_name,
            'billing_cycle' => $request->billing_cycle,
            'price'         => $request->price,
            'currency'      => 'USD',
            'status'        => 'active',
            'starts_at'     => now(),
            'expires_at'    => $request->expires_at ?? now()->addMonth(),
            'notes'         => $request->notes,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $updateData = ['status' => 'active', 'updated_at' => now()];
        if ($request->filled('client_tier')) {
            $limits = match ($request->client_tier) {
                'enterprise_org'  => ['max_outlets' => 50, 'max_registers' => 200, 'max_users' => 500],
                'business_runner' => ['max_outlets' => 5,  'max_registers' => 15,  'max_users' => 50],
                default           => ['max_outlets' => 1,  'max_registers' => 2,   'max_users' => 5],
            };
            $updateData = array_merge($updateData, $limits, ['client_tier' => $request->client_tier]);
        }

        DB::table('tenants')->where('id', $id)->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Tenant subscription updated successfully.',
        ]);
    }

    /**
     * Super Admin: Suspend or activate tenant account.
     * POST /api/v1/super-admin/tenants/{id}/suspend
     */
    public function suspend(Request $request, $id)
    {
        $tenant = DB::table('tenants')->where('id', $id)->first();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant not found.'], 404);
        }

        $newStatus = $tenant->status === 'suspended' ? 'active' : 'suspended';

        DB::table('tenants')->where('id', $id)->update([
            'status'     => $newStatus,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Tenant account has been " . ($newStatus === 'suspended' ? 'suspended' : 'reactivated') . " successfully.",
            'status'  => $newStatus,
        ]);
    }

    /**
     * Get Real-time Subscription Quota Usage for active tenant.
     * GET /api/v1/tenants/quota-usage
     */
    public function quotaUsage(Request $request)
    {
        $user = $request->user();
        $tenantId = $user?->tenant_id;
        $activeWorkspace = $request->header('X-Tenant-Workspace');

        $tenantQuery = DB::table('tenants');
        if ($tenantId) {
            $tenantQuery->where('id', $tenantId);
        } elseif (!empty($activeWorkspace) && $activeWorkspace !== 'No Organization Yet! Please Create') {
            $tenantQuery->where('name', $activeWorkspace)->orWhere('slug', $activeWorkspace);
        }

        $tenant = $tenantQuery->first() ?? DB::table('tenants')->first();

        $tier = $tenant->client_tier ?? 'free_personal';
        $maxUsers = $tenant->max_users ?? ($tier === 'enterprise_org' ? 500 : ($tier === 'business_runner' ? 50 : 5));
        $maxOutlets = $tenant->max_outlets ?? ($tier === 'enterprise_org' ? 50 : ($tier === 'business_runner' ? 5 : 1));
        $maxRegisters = $tenant->max_registers ?? ($tier === 'enterprise_org' ? 200 : ($tier === 'business_runner' ? 15 : 2));

        $usersCount = DB::table('users')->where('role', '!=', 'super_admin')->count();
        $outletsCount = DB::table('outlets')->count();
        $registersCount = DB::table('registers')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'tenant' => [
                    'id' => $tenant->id ?? null,
                    'name' => $tenant->name ?? 'Default Workspace',
                    'slug' => $tenant->slug ?? 'default',
                    'client_tier' => $tier,
                    'status' => $tenant->status ?? 'active',
                    'trial_ends_at' => $tenant->trial_ends_at ?? null,
                ],
                'quotas' => [
                    'users' => [
                        'used' => $usersCount,
                        'limit' => $maxUsers,
                        'percentage' => $maxUsers > 0 ? round(($usersCount / $maxUsers) * 100, 1) : 0,
                        'is_exceeded' => $usersCount >= $maxUsers,
                    ],
                    'outlets' => [
                        'used' => $outletsCount,
                        'limit' => $maxOutlets,
                        'percentage' => $maxOutlets > 0 ? round(($outletsCount / $maxOutlets) * 100, 1) : 0,
                        'is_exceeded' => $outletsCount >= $maxOutlets,
                    ],
                    'registers' => [
                        'used' => $registersCount,
                        'limit' => $maxRegisters,
                        'percentage' => $maxRegisters > 0 ? round(($registersCount / $maxRegisters) * 100, 1) : 0,
                        'is_exceeded' => $registersCount >= $maxRegisters,
                    ],
                ],
            ],
        ]);
    }
}

