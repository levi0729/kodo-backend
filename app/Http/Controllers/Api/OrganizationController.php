<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::orderBy('name')->get();

        return response()->json(['organizations' => $organizations]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'logo_url'              => 'nullable|string|max:500',
            'domain'                => 'nullable|string|max:255',
            'plan_type'             => 'nullable|string|in:free,standard,business,pro,enterprise',
            'max_members'           => 'nullable|integer|min:1',
            'max_storage_gb'        => 'nullable|integer|min:1',
            'allowed_email_domains' => 'nullable|array',
        ]);

        $org = Organization::create([
            'name'                  => $data['name'],
            'slug'                  => Str::slug($data['name']),
            'description'           => $data['description'] ?? null,
            'logo_url'              => $data['logo_url'] ?? null,
            'domain'                => $data['domain'] ?? null,
            'plan_type'             => $data['plan_type'] ?? 'free',
            'max_members'           => $data['max_members'] ?? 50,
            'max_storage_gb'        => $data['max_storage_gb'] ?? 5,
            'allowed_email_domains' => $data['allowed_email_domains'] ?? null,
        ]);

        return response()->json(['organization' => $org], 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        $organization->load('projects', 'teams');

        return response()->json(['organization' => $organization]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'name'                  => 'sometimes|string|max:255',
            'description'           => 'nullable|string',
            'logo_url'              => 'nullable|string|max:500',
            'domain'                => 'nullable|string|max:255',
            'plan_type'             => 'sometimes|string|in:free,standard,business,pro,enterprise',
            'max_members'           => 'sometimes|integer|min:1',
            'max_storage_gb'        => 'sometimes|integer|min:1',
            'allowed_email_domains' => 'nullable|array',
        ]);

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $organization->update($data);

        return response()->json(['organization' => $organization->fresh()]);
    }

    public function destroy(Organization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json(['message' => 'Organization deleted.']);
    }
}
