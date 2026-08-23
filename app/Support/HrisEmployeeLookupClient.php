<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class HrisEmployeeLookupClient
{
    public const UNAVAILABLE_MESSAGE = 'HRIS is unavailable. You can continue creating this user manually';

    public function health(): array
    {
        return $this->request('get', '/api/crm/health');
    }

    public function search(string $query = '', int $limit = 15): array
    {
        return $this->request('get', '/api/crm/employees', [
            'q' => $query,
            'limit' => min(max($limit, 1), 50),
        ]);
    }

    public function show(string $hrisEmployeeId): array
    {
        return $this->request('get', '/api/crm/employees/'.rawurlencode($hrisEmployeeId));
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $baseUrl = rtrim(AppSetting::hrisBaseUrl(), '/');
        $token = AppSetting::hrisCrmLookupToken();

        if ($baseUrl === '' || $token === '') {
            return $this->unavailable();
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->withToken($token)
                ->withHeaders(['X-HRIS-Token' => $token])
                ->{$method}($baseUrl.$path, $query);
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        if ($response->successful()) {
            return [
                'available' => true,
                'status' => $response->status(),
                'payload' => $response->json() ?? [],
                'message' => null,
            ];
        }

        if ($response->status() === 404) {
            return [
                'available' => true,
                'status' => 404,
                'payload' => [],
                'message' => 'That HRIS employee no longer exists',
            ];
        }

        return $this->unavailable($response->status());
    }

    private function unavailable(?int $status = null): array
    {
        return [
            'available' => false,
            'status' => $status,
            'payload' => [],
            'message' => self::UNAVAILABLE_MESSAGE,
        ];
    }
}
