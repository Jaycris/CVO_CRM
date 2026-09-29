<?php

namespace App\Support;

class HrisReportsTo
{
    public static function employeeFromPayload(array $payload): array
    {
        return self::findEmployee($payload) ?? [];
    }

    public static function extractHrisEmployeeId(array $employee): ?string
    {
        $reportsTo = self::reportsToValue($employee);

        if (is_array($reportsTo)) {
            $reportsTo = self::valueFromKeys($reportsTo, [
                'hris_employee_id',
                'employee_id',
                'employee_no',
                'employee_number',
                'emp_id',
                'id',
                'code',
            ]);
        }

        $reportsTo = trim((string) ($reportsTo ?? ''));

        if (! self::looksLikeHrisEmployeeId($reportsTo)) {
            return null;
        }

        return $reportsTo === '' ? null : $reportsTo;
    }

    public static function extractEmployeeId(array $employee): ?string
    {
        $employeeId = self::valueFromKeys($employee, [
            'hris_employee_id',
            'employee_id',
            'employee_no',
            'employee_number',
            'emp_id',
            'id',
            'code',
        ]);

        $employeeId = trim((string) ($employeeId ?? ''));

        return $employeeId === '' ? null : $employeeId;
    }

    public static function extractDisplayName(array $employee): ?string
    {
        $reportsTo = self::reportsToValue($employee);

        if (is_array($reportsTo)) {
            $name = trim((string) (self::valueFromKeys($reportsTo, [
                'phone_name',
                'full_name',
                'name',
                'display_name',
            ]) ?? ''));

            if ($name !== '') {
                return $name;
            }

            $name = trim(implode(' ', array_filter([
                $reportsTo['first_name'] ?? null,
                $reportsTo['last_name'] ?? null,
            ])));

            return $name !== '' ? $name : null;
        }

        $reportsTo = trim((string) ($reportsTo ?? ''));

        if ($reportsTo === '' || self::looksLikeHrisEmployeeId($reportsTo)) {
            return null;
        }

        return $reportsTo;
    }

    private static function looksLikeEmployee(array $value): bool
    {
        return self::valueFromKeys($value, [
            'hris_employee_id',
            'employee_id',
            'employee_no',
            'employee_number',
            'phone_name',
            'first_name',
            'last_name',
            'email',
        ]) !== null;
    }

    private static function findEmployee(array $value, int $depth = 0): ?array
    {
        if (self::looksLikeEmployee($value)) {
            return $value;
        }

        if ($depth >= 3) {
            return null;
        }

        foreach (['data', 'employee', 'result', 'record', 'profile', 'item'] as $key) {
            $nested = $value[$key] ?? null;

            if (! is_array($nested)) {
                continue;
            }

            if (array_is_list($nested)) {
                foreach ($nested as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $employee = self::findEmployee($item, $depth + 1);

                    if ($employee) {
                        return $employee;
                    }
                }

                continue;
            }

            $employee = self::findEmployee($nested, $depth + 1);

            if ($employee) {
                return $employee;
            }
        }

        return null;
    }

    private static function reportsToValue(array $employee): mixed
    {
        $reportsTo = self::valueFromKeys($employee, self::reportsToKeys());

        if ($reportsTo !== null) {
            return $reportsTo;
        }

        foreach (['employment', 'job', 'position', 'organization', 'department', 'profile', 'employee', 'data', 'work'] as $nestedKey) {
            $nested = $employee[$nestedKey] ?? null;

            if (! is_array($nested)) {
                continue;
            }

            $reportsTo = self::valueFromKeys($nested, self::reportsToKeys());

            if ($reportsTo !== null) {
                return $reportsTo;
            }
        }

        return null;
    }

    private static function looksLikeHrisEmployeeId(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (preg_match('/\s/', $value)) {
            return false;
        }

        return preg_match('/\d/', $value) === 1;
    }

    private static function valueFromKeys(array $value, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $value) && $value[$key] !== null && $value[$key] !== '') {
                return $value[$key];
            }
        }

        return null;
    }

    private static function reportsToKeys(): array
    {
        return [
            'reports_to_hris_employee_id',
            'reports_to_hris_id',
            'reports_to_employee_id',
            'reports_to_emp_id',
            'reports_to_id',
            'reports_to',
            'reportsToHrisEmployeeId',
            'reportsToEmployeeId',
            'reportsTo',
            'report_to_hris_employee_id',
            'report_to_employee_id',
            'report_to_id',
            'report_to',
            'reporting_to',
            'reporting_manager_hris_employee_id',
            'reporting_manager_employee_id',
            'reporting_manager_id',
            'reporting_manager',
            'manager_hris_employee_id',
            'manager_hris_id',
            'manager_employee_id',
            'manager_code',
            'manager_id',
            'manager',
            'manager_employee',
            'direct_manager_hris_employee_id',
            'direct_manager_employee_id',
            'direct_manager_id',
            'direct_manager',
            'supervisor_hris_employee_id',
            'supervisor_employee_id',
            'supervisor_id',
            'supervisor',
            'immediate_supervisor_hris_employee_id',
            'immediate_supervisor_employee_id',
            'immediate_supervisor_id',
            'immediate_supervisor',
        ];
    }
}
