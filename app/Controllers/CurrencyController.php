<?php

namespace App\Controllers;

use App\Core\Database;
use PDO;

class CurrencyController
{
    public function toggle(): void
    {
        $current = $_SESSION['currency'] ?? 'USD';
        $_SESSION['currency'] = $current === 'USD' ? 'KES' : 'USD';

        $redirect = $_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        redirect($redirect);
    }

    public function setRate(): void
    {
        $rate = (float)($_POST['rate'] ?? 0);
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($rate <= 0) {
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Exchange rate must be greater than 0.']);
                exit;
            }
            flash('fleet_error', 'Invalid exchange rate specified.');
            redirect($_SERVER['HTTP_REFERER'] ?? '/dashboard');
            return;
        }

        $_SESSION['exchange_rate'] = $rate;

        try {
            $pdo = Database::connection();
            $exists = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key = 'usd_kes_exchange_rate'")->fetchColumn();
            if ((int)$exists > 0) {
                $upd = $pdo->prepare("UPDATE settings SET setting_value = ?, updated_at = ? WHERE setting_key = 'usd_kes_exchange_rate'");
                $upd->execute([(string)$rate, date('Y-m-d H:i:s')]);
            } else {
                $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('usd_kes_exchange_rate', ?, ?)");
                $ins->execute([(string)$rate, date('Y-m-d H:i:s')]);
            }
            log_audit('Settings', 'UPDATE_EXCHANGE_RATE', "Updated USD to KES exchange rate to {$rate}");
        } catch (\Throwable $e) {
            error_log('Error saving exchange rate: ' . $e->getMessage());
        }

        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'rate' => $rate,
                'message' => "Exchange rate updated to 1 USD = {$rate} KES."
            ]);
            exit;
        }

        flash('fleet_success', "Exchange rate updated to 1 USD = {$rate} KES.");
        redirect($_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? '/dashboard');
    }
}
