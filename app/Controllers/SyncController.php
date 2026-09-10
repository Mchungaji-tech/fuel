<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\DatabaseSyncService;
use Throwable;

class SyncController
{
    /**
     * Trigger synchronization between MySQL and SQLite.
     */
    public function sync(): void
    {
        header('Content-Type: application/json');

        // Optional permission check: require user to be logged in
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['is_logged_in']) && empty($_SESSION['user']['id']) && empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required to sync database.']);
            exit;
        }

        try {
            // Clear any temporary offline marker to attempt fresh online connection
            Database::clearOfflineMarker();
            Database::resetConnection();

            $result = DatabaseSyncService::syncAll();
            echo json_encode($result);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage(),
            ]);
        }
        exit;
    }

    /**
     * Get live database connection status and health.
     */
    public function status(): void
    {
        header('Content-Type: application/json');

        $activeDriver = Database::getActiveDriver();
        $isFallback = Database::isOfflineFallback();
        $fallbackReason = Database::getFallbackReason();
        $mysqlAvailable = Database::isMysqlAvailable();

        echo json_encode([
            'success' => true,
            'active_driver' => $activeDriver,
            'is_fallback' => $isFallback,
            'fallback_reason' => $fallbackReason,
            'mysql_available' => $mysqlAvailable,
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
        exit;
    }
}
