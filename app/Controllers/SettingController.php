<?php

namespace App\Controllers;

class SettingController
{
    public function index(): string
    {
        $data = [
            'title' => 'Settings — Sarura Fuel',
            'settings' => [
                ['name' => 'Business profile', 'description' => 'Company identity, contact details, and branch setup'],
                ['name' => 'Payroll rules', 'description' => 'PAYE, NSSF, SHIF, housing levy, and deductions'],
                ['name' => 'Tax configuration', 'description' => 'KRA settings and filing compliance'],
                ['name' => 'User roles', 'description' => 'Admin, finance, ops, HR, and sales permissions'],
                ['name' => 'Notifications', 'description' => 'Alerts for invoices, documents, and fleet compliance'],
            ],
        ];

        return view('settings.index', $data);
    }
}
