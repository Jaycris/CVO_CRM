<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Response;
use Throwable;

class MaintenanceController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view('maintenance', [
                'estimatedReturn' => $this->estimatedReturn(),
            ], 503);
    }

    public function preview(): Response
    {
        return response()
            ->view('maintenance', [
                'estimatedReturn' => $this->estimatedReturn(),
                'isPreview' => true,
            ]);
    }

    private function estimatedReturn(): ?string
    {
        try {
            return AppSetting::maintenanceReturn();
        } catch (Throwable) {
            return config('app.maintenance_return');
        }
    }
}
