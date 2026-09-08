<?php

namespace App\Controllers;

use App\Core\Database;

class InvoiceController
{
    public function index(): string
    {
        $pdo = Database::connection();
        $invoices = $pdo->query('SELECT * FROM invoices ORDER BY id DESC')->fetchAll();

        $data = [
            'title' => 'Invoices — Sarura Fuel',
            'invoices' => array_map(function ($invoice) {
                return [
                    'id' => $invoice['invoice_number'],
                    'client' => $invoice['client'],
                    'amount' => $invoice['amount'],
                    'due' => $invoice['due_date'],
                    'status' => $invoice['status'],
                ];
            }, $invoices),
        ];

        return view('invoices.index', $data);
    }

    public function create(): string
    {
        return view('invoices.new', ['title' => 'New Invoice — Sarura Fuel']);
    }

    public function store(): void
    {
        $number = trim($_POST['invoice_number'] ?? '');
        $client = trim($_POST['client'] ?? '');
        $amount = trim($_POST['amount'] ?? '');
        $due = trim($_POST['due_date'] ?? '');
        $status = trim($_POST['status'] ?? 'Pending');

        if ($number !== '') {
            $pdo = Database::connection();
            $pdo->prepare('INSERT INTO invoices (invoice_number, client, amount, due_date, status) VALUES (?, ?, ?, ?, ?)')
                ->execute([$number, $client, $amount, $due, $status]);
        }

        redirect('/invoices');
    }
}
