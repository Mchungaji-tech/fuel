<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\DriverController;
use App\Controllers\TripController;
use App\Controllers\FleetController;
use App\Controllers\CustomerController;
use App\Controllers\InvoiceController;
use App\Controllers\DocumentController;
use App\Controllers\PayrollController;
use App\Controllers\ReportController;
use App\Controllers\AuditController;
use App\Controllers\SettingController;
use App\Controllers\CurrencyController;
use App\Controllers\ProductController;
use App\Controllers\TruckController;
use App\Controllers\ExpenseController;
use App\Controllers\UserController;
use App\Controllers\SyncController;

return [
    // Auth Routes
    ['GET', '/login', [AuthController::class, 'login']],
    ['POST', '/login', [AuthController::class, 'authenticate']],
    ['GET', '/register', [AuthController::class, 'registerForm']],
    ['POST', '/register', [AuthController::class, 'register']],
    ['POST', '/logout', [AuthController::class, 'logout']],

    // Currency Switcher
    ['POST', '/currency/toggle', [CurrencyController::class, 'toggle']],

    // Dashboard
    ['GET', '/', [DashboardController::class, 'index']],
    ['GET', '/dashboard', [DashboardController::class, 'index']],

    // Fleet Dispatches & Ledger
    ['GET', '/fleet', [FleetController::class, 'index']],
    ['POST', '/fleet/store', [FleetController::class, 'store']],
    ['POST', '/fleet/inline-update', [FleetController::class, 'inlineUpdate']],
    ['POST', '/fleet/confirm-delivery/{id}', [FleetController::class, 'confirmDelivery']],
    ['GET', '/fleet/bol/{id}', [FleetController::class, 'bol']],
    ['GET', '/fleet/export', [FleetController::class, 'export']],
    ['POST', '/fleet/import', [FleetController::class, 'import']],
    ['GET', '/fleet/template', [FleetController::class, 'template']],
    ['POST', '/fleet/delete/{id}', [FleetController::class, 'delete']],

    // Trucks & Tankers
    ['GET', '/trucks', [TruckController::class, 'index']],
    ['GET', '/trucks/view/{id}', [TruckController::class, 'view']],
    ['POST', '/trucks/store', [TruckController::class, 'store']],
    ['POST', '/trucks/inline-update', [TruckController::class, 'inlineUpdate']],
    ['POST', '/trucks/delete/{id}', [TruckController::class, 'delete']],

    // Fuel Products
    ['GET', '/products', [ProductController::class, 'index']],
    ['POST', '/products/store', [ProductController::class, 'store']],
    ['POST', '/products/delete/{id}', [ProductController::class, 'delete']],

    // Maintenance & Outside Expenses
    ['GET', '/expenses', [ExpenseController::class, 'index']],
    ['GET', '/expenses/export', [ExpenseController::class, 'export']],
    ['POST', '/expenses/store', [ExpenseController::class, 'store']],
    ['POST', '/expenses/inline-update', [ExpenseController::class, 'inlineUpdate']],
    ['POST', '/expenses/delete/{id}', [ExpenseController::class, 'delete']],

    // Drivers & Dynamic Assignment
    ['GET', '/drivers', [DriverController::class, 'index']],
    ['GET', '/drivers/new', [DriverController::class, 'create']],
    ['POST', '/drivers/store', [DriverController::class, 'store']],
    ['POST', '/drivers/salary/store', [DriverController::class, 'issueSalary']],
    ['POST', '/drivers/salary/update', [DriverController::class, 'updateSalary']],
    ['POST', '/drivers/salary/delete/{id}', [DriverController::class, 'deleteSalary']],
    ['POST', '/drivers/salary/toggle', [DriverController::class, 'toggleSalary']],
    ['POST', '/drivers/delete/{id}', [DriverController::class, 'delete']],

    // Trips (dispatch board)
    ['GET', '/trips', [TripController::class, 'index']],
    ['GET', '/trips/new', [TripController::class, 'create']],
    ['POST', '/trips/store', [TripController::class, 'store']],

    // Invoices redirected to fleet
    ['GET', '/invoices', function() { redirect('/fleet'); }],

    // Payroll redirected to drivers
    ['GET', '/payroll', function() { redirect('/drivers'); }],

    // Customers
    ['GET', '/customers', [CustomerController::class, 'index']],

    // Documents
    ['GET', '/documents', [DocumentController::class, 'index']],
    ['POST', '/documents/upload', [DocumentController::class, 'upload']],

    // Reports (Monthly Assessment of Profit, Expenses, Salaries & Detailed Truck Reports)
    ['GET', '/reports', [ReportController::class, 'index']],
    ['GET', '/reports/export', [ReportController::class, 'exportMonthly']],
    ['GET', '/reports/trucks', [ReportController::class, 'truckReport']],
    ['GET', '/reports/trucks/export', [ReportController::class, 'exportTrucks']],

    // Audit Trail
    ['GET', '/audit', [AuditController::class, 'index']],

    // Settings
    ['GET', '/settings', [SettingController::class, 'index']],

    // User Management & Staff Registration (Super Admin)
    ['GET', '/users', [UserController::class, 'index']],
    ['POST', '/users/store', [UserController::class, 'store']],
    ['POST', '/users/update/{id}', [UserController::class, 'update']],
    ['POST', '/users/terminate/{id}', [UserController::class, 'terminate']],
    ['POST', '/users/delete/{id}', [UserController::class, 'delete']],
    ['POST', '/users/role/{id}', [UserController::class, 'updateRole']],

    // Developer & Security Dashboard
    ['GET', '/dev', [\App\Controllers\DevController::class, 'index']],
    ['POST', '/dev/maintenance/toggle', [\App\Controllers\DevController::class, 'toggleMaintenance']],
    ['POST', '/dev/sessions/terminate/{id}', [\App\Controllers\DevController::class, 'terminateSession']],
    ['POST', '/dev/sessions/terminate-all-user/{userId}', [\App\Controllers\DevController::class, 'terminateAllUserSessions']],

    // Schema & Dynamic Column Customization (for every table)
    ['POST', '/schema/columns/update', [\App\Controllers\SchemaController::class, 'updateColumns']],
    ['POST', '/schema/columns/add', [\App\Controllers\SchemaController::class, 'addColumn']],
    ['POST', '/schema/columns/delete', [\App\Controllers\SchemaController::class, 'deleteColumn']],
    ['POST', '/schema/table/clear', [\App\Controllers\SchemaController::class, 'clearTable']],
    ['POST', '/schema/table/create', [\App\Controllers\SchemaController::class, 'createTable']],
    ['POST', '/schema/table/delete', [\App\Controllers\SchemaController::class, 'dropTable']],

    // Custom Tables & Datasets Hub
    ['GET', '/custom-tables', [\App\Controllers\CustomTableController::class, 'index']],
    ['GET', '/custom-tables/{key}', [\App\Controllers\CustomTableController::class, 'view']],
    ['POST', '/custom-tables/{key}/store', [\App\Controllers\CustomTableController::class, 'store']],
    ['POST', '/custom-tables/{key}/inline-update', [\App\Controllers\CustomTableController::class, 'inlineUpdate']],
    ['POST', '/custom-tables/{key}/delete/{id}', [\App\Controllers\CustomTableController::class, 'deleteRecord']],
    ['GET', '/custom-tables/{key}/export', [\App\Controllers\CustomTableController::class, 'export']],
    ['GET', '/custom-tables/{key}/template', [\App\Controllers\CustomTableController::class, 'template']],
    ['POST', '/custom-tables/{key}/import', [\App\Controllers\CustomTableController::class, 'import']],

    // Hybrid Database Synchronization & Offline/Online Status
    ['POST', '/system/sync-db', [SyncController::class, 'sync']],
    ['GET', '/system/db-status', [SyncController::class, 'status']],
];
