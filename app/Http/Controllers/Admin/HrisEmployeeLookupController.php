<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\HrisEmployeeLookupClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrisEmployeeLookupController extends Controller
{
    public function __construct(private readonly HrisEmployeeLookupClient $client)
    {
    }

    public function health(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $result = $this->client->health();

        return response()->json([
            'available' => (bool) $result['available'],
            'message' => $result['message'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->client->search(
            trim((string) ($validated['q'] ?? '')),
            (int) ($validated['limit'] ?? 15)
        );

        return response()->json([
            'available' => (bool) $result['available'],
            'message' => $result['message'],
            'data' => $result['payload']['data'] ?? [],
            'count' => $result['payload']['count'] ?? 0,
            'query' => $result['payload']['query'] ?? ($validated['q'] ?? ''),
        ]);
    }

    public function show(Request $request, string $hrisEmployeeId): JsonResponse
    {
        $this->ensureAdmin($request);

        $result = $this->client->show($hrisEmployeeId);

        return response()->json([
            'available' => (bool) $result['available'],
            'message' => $result['message'],
            'employee' => $result['payload']['data'] ?? $result['payload'],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || $request->user()?->hasPermission('manage_users'),
            403
        );
    }
}
