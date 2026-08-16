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
            'email'       => 'required|email|unique:tenants,email',
            'client_tier' => 'required|in:free_personal,business_runner,enterprise_org',
            'phone'       => 'nullable|string|max:30',
            'address'     => 'nullable|string|max:500',
            'country'     => 'nullable|string|max:10',
        ]);

        $slug = Str::slug($request->name);
        $existing = DB::table('tenants')->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . Str::random(4);
        }

        $limits = match ($request->client_tier) {
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
            'client_tier'   => $request->client_tier,
            'status'        => 'trial',
            'email'         => $request->email,
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

        $planInfo = $planMap[$request->client_tier];

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

        return response()->json([
            'success' => true,
            'message' => 'Tenant workspace registered successfully! Your 14-day trial has started.',
            'data'    => DB::table('tenants')->where('id', $id)->first(),
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
}
