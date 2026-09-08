# Sarura Fuel Logistics - Technical Overview

## Project Goal

Sarura Fuel Logistics is a PHP-based operational system for a Kenyan fuel transport business. It is structured to manage drivers, trucks, payroll, deductions, leads, customers, trips, invoices, and business reporting from a single application.

## Architecture

The project uses a lightweight MVC-inspired structure:

- public entry point for request handling
- route definitions in the web routing layer
- controller classes for business logic
- view templates for UI rendering
- helper functions for reusable view logic
- modular component structure for future expansion

## Main Modules

### Routing
The app uses a simple router for GET/POST route registration and dispatch. Routes are mapped to controller methods.

### Controllers
Controller classes handle the main business pages, including:

- dashboard
- drivers
- trips
- payroll
- leads

### Views
The UI is rendered using PHP view templates, with shared layout logic stored separately so all pages use the same app shell, sidebar, and topbar styling.

### Helpers
Shared helper functions provide reusable utilities such as view rendering and base URL generation.

## Dashboard Features

The dashboard is designed as an operational control panel and includes:

- KPI cards for revenue, trips, trucks, and variance
- business alerts and operational issues
- trip overview tables
- payroll summary widgets
- chart-style revenue monitoring
- modern responsive shell with sidebar navigation

## Business Logic

### Trip Management
- assign truck and driver
- track loaded and delivered quantities
- identify fuel variance
- mark trip status based on lifecycle

### Payroll Logic
- calculate gross pay
- handle statutory deductions such as PAYE, NSSF, and SHIF contributions
- support housing levy and other approved deductions
- generate net pay and payroll output

### Compliance and Alerts
- flag invalid license and insurance issues
- detect overdue invoices and operational risks
- support document expiry monitoring
- highlight abnormal fuel loss conditions

## Data Design

The application is designed around business entities such as:

- employees
- drivers
- trucks
- trips
- customers
- leads
- payroll records
- invoices
- payments
- deductions
- documents

## Styling System

The visual layer uses a custom design token system with:

- neutral background and white cards
- navy sidebar branding
- orange emphasis for actions and status
- green for success and completion
- amber for warnings
- red for risk and overdue actions
- blue for informational states

## Roadmap

Planned technical work includes:

- authentication and role enforcement
- database-backed CRUD operations
- form validation and sanitization
- reporting exports
- payment integrations
- audit trail logging
- mobile-ready interfaces for drivers and dispatchers

## Summary

This project is structured as a lightweight but scalable PHP application for a fuel logistics business. It is organized to support operational reporting, compliance, payroll, and business management while keeping the interface modern, clean, and client-ready.
