<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class ProductController
{
    public function index(): string
    {
        $pdo = Database::connection();
        $search = trim($_GET['search'] ?? '');

        $sql = 'SELECT * FROM products WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (code LIKE ? OR name LIKE ? OR category LIKE ?)';
            $term = "%{$search}%";
            $params = [$term, $term, $term];
        }

        $sql .= ' ORDER BY code ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return view('products.index', [
            'title' => 'Fuel Products — Sarura Fuel',
            'products' => $products,
            'search' => $search,
        ]);
    }

    public function store(): void
    {
        $pdo = Database::connection();
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Fuel');
        $unit = trim($_POST['unit'] ?? 'Litres');
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawUnitPrice = (float) ($_POST['unit_price'] ?? 0);
        $unitPrice = $isKes ? ($rawUnitPrice / $rate) : $rawUnitPrice;
        $status = trim($_POST['status'] ?? 'Active');

        if ($code !== '' && $name !== '') {
            $stmt = $pdo->prepare('INSERT INTO products (code, name, category, unit, unit_price, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            try {
                $stmt->execute([$code, $name, $category, $unit, $unitPrice, $status, date('Y-m-d H:i:s')]);
                flash('product_success', "Product {$code} - {$name} added successfully (Unit Price: " . format_money($unitPrice) . " per {$unit}).");
            } catch (\Exception $e) {
                flash('product_error', "Product code {$code} already exists or error occurred.");
            }
        } else {
            flash('product_error', 'Product code and name are required.');
        }

        redirect('/products');
    }

    public function update(): void
    {
        $pdo = Database::connection();
        $id = (int) ($_POST['id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? 'Fuel');
        $unit = trim($_POST['unit'] ?? 'Litres');
        $isKes = current_currency() === 'KES';
        $rate = exchange_rate();
        $rawUnitPrice = (float) ($_POST['unit_price'] ?? 0);
        $unitPrice = $isKes ? ($rawUnitPrice / $rate) : $rawUnitPrice;
        $status = trim($_POST['status'] ?? 'Active');

        if ($id > 0 && $code !== '' && $name !== '') {
            $stmt = $pdo->prepare('UPDATE products SET code = ?, name = ?, category = ?, unit = ?, unit_price = ?, status = ? WHERE id = ?');
            $stmt->execute([$code, $name, $category, $unit, $unitPrice, $status, $id]);
            flash('product_success', "Product {$code} updated successfully (Unit Price: " . format_money($unitPrice) . " per {$unit}).");
        } else {
            flash('product_error', 'Invalid product data for update.');
        }

        redirect('/products');
    }

    public function delete(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = ?');
        $stmt->execute([$id]);

        flash('product_success', 'Product removed successfully.');
        redirect('/products');
    }
}
