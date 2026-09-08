<?php

namespace App\Controllers;

class CurrencyController
{
    public function toggle(): void
    {
        $current = $_SESSION['currency'] ?? 'USD';
        $_SESSION['currency'] = $current === 'USD' ? 'KES' : 'USD';

        $redirect = $_POST['redirect_to'] ?? $_SERVER['HTTP_REFERER'] ?? '/dashboard';
        redirect($redirect);
    }
}
