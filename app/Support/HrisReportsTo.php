<?php

namespace App\Support;

class HrisReportsTo
{
    public static function extractHrisEmployeeId(array $employee): ?string
    {
        $reportsTo = $employee['reports_to_hris_employee_id']
            ?? $employee['reports_to_employee_id']
            ?? $employee['reports_to']
            ?? $employee['report_to_hris_employee_id']
            ?? $employee['report_to_employee_id']
            ?? $employee['report_to']
            ?? $employee['manager_hris_employee_id']
            ?? $employee['manager_employee_id']
            ?? $employee['manager_id']
            ?? null;

        if (is_array($reportsTo)) {
            $reportsTo = $reportsTo['hris_employee_id']
                ?? $reportsTo['employee_id']
                ?? $reportsTo['id']
                ?? null;
        }

        $reportsTo = trim((string) ($reportsTo ?? ''));

        return $reportsTo === '' ? null : $reportsTo;
    }
}
