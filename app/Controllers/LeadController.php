<?php

namespace App\Controllers;

class LeadController
{
    public function index(): string
    {
        $data = [
            'title' => 'Leads & CRM — Sarura Fuel',
            'leads' => [
                ['name' => 'Kenya Power Bulk', 'source' => 'Website', 'stage' => 'Qualified', 'owner' => 'Amina'],
                ['name' => 'Muthaiga Traders', 'source' => 'Referral', 'stage' => 'Proposal', 'owner' => 'Zack'],
                ['name' => 'Nairobi Logistics Co.', 'source' => 'Cold Call', 'stage' => 'New', 'owner' => 'Joy'],
            ],
        ];

        return view('leads.index', $data);
    }
}
