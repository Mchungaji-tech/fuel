<?php

namespace App\Controllers;

class PayrollController
{
    public function index(): string
    {
        $data = [
            'title' => 'Payroll — August 2026',
            'employees' => [
                ['name' => 'Amina Wanjiku', 'gross' => '150,000', 'paye' => '36,100', 'nssf' => '4,320', 'shif' => '4,125', 'housing' => '2,250', 'net' => '98,205'],
                ['name' => 'Peter Otieno', 'gross' => '82,000', 'paye' => '15,850', 'nssf' => '2,160', 'shif' => '2,255', 'housing' => '1,230', 'net' => '57,505'],
                ['name' => 'Grace Njeri', 'gross' => '55,000', 'paye' => '8,700', 'nssf' => '2,160', 'shif' => '1,513', 'housing' => '825', 'net' => '41,802'],
            ],
        ];

        return view('payroll.index', $data);
    }
}
