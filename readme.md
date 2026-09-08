# Sarura Fuel Logistics Management System

A Kenyan fuel logistics and business management platform built for a company that manages drivers, trucks, fuel trips, employees, payroll, taxes, deductions, leads, customers, invoices, and operational reporting.

## Overview

Sarura Fuel Logistics helps manage the full business lifecycle of a fuel transport company:

- lead generation and CRM
- customer management
- trip dispatch and delivery tracking
- fleet and driver management
- payroll and statutory compliance
- financial reporting and invoice management
- operational dashboards and business alerts

The system is designed for a Kenyan business environment and supports local payroll needs such as PAYE, NSSF, SHIF, Affordable Housing Levy, and employee deductions.

## Core Goals

1. Manage drivers, trucks, and fuel trip operations efficiently
2. Track customer orders and deliveries in real time
3. Keep operational and financial data in one place
4. Automate payroll calculations and statutory deductions
5. Reduce fuel loss and improve trip accountability
6. Monitor business performance with a clean dashboard
7. Support HR, finance, payroll, and sales in one platform

## Users

- Admin / Owner
- Operations Manager
- Dispatch Officer
- Fleet Manager
- HR / Payroll Officer
- Finance / Accounts Officer
- Sales / CRM Officer
- Drivers
- Customers / Business Partners

## Modules

### 1. Dashboard

The dashboard is the main operational control center. It provides:

- KPI cards for revenue, fuel delivered, active trucks, and fuel variance
- trip overview for the day
- alerts for critical issues
- revenue trend chart
- workforce and payroll status
- leads and sales pipeline snapshot

### 2. Driver Management

- driver profiles
- license expiry tracking
- driving class and permit validation
- driver assignment history
- status: active, on leave, suspended, terminated
- document tracking for ID, license, and certificates

### 3. Fleet Management

- truck registration and capacity tracking
- insurance, inspection, and road license expiry monitoring
- maintenance scheduling
- assignment to trips and drivers
- status tracking for available, on trip, under maintenance, suspended, and sold

### 4. Trip Management

- create trip records
- assign truck and driver
- loading and delivery quantity tracking
- route and customer management
- proof of delivery uploads
- abnormal loss detection and investigation flags
- status tracking: planned, assigned, loading, in transit, delivered, disputed, cancelled

### 5. CRM and Leads

- lead capture and source tracking
- follow-up scheduling
- quotation management
- lead status tracking
- conversion to customer
- sales stage pipeline

### 6. Customers and Orders

- customer profiles and credit limits
- order creation and status tracking
- payment terms management
- customer statement history
- overdue monitoring

### 7. Payroll and Employee Records

- employee onboarding and profiles
- department, role, and contract data
- salary structures
- earnings and deductions
- payroll runs and pay approval
- payslip generation
- bank or mobile-money payment export

### 8. Statutory Compliance

The system supports Kenya payroll rules and should be configurable by policy and effective date.

Includes:

- PAYE calculation
- NSSF calculations
- SHIF / SHA contributions
- Affordable Housing Levy
- employee and employer contribution records
- payroll compliance reporting

### 9. Finance and Invoicing

- invoice generation
- customer payment tracking
- expense records
- revenue and profit summaries
- accounts receivable and payable views
- periodic financial reporting

### 10. Alerts and Notifications

- license expiry reminders
- truck insurance alerts
- overdue invoices
- abnormal fuel loss
- payroll due dates
- document expiration alerts
- follow-up reminders for leads and customers

## Business Workflow

The company workflow can be summarized as:

Lead → Customer → Order → Dispatch → Loading → Trip → Delivery → Invoice → Payment

Internal operations workflow:

Employee Onboarding → Job Assignment → Salary Setup → Payroll → Deductions → Payslip → Payment

## Key Features

- responsive dashboard design
- fuel logistics KPI monitoring
- trip assignment and reconciliation
- fleet and driver compliance tracking
- payroll and deduction engine
- lead and customer relationship tracking
- invoice and payment management
- document expiry alerts
- company-wide reporting
- role-based access control
- audit logging

## Dashboard Design

The dashboard follows a modern operations-style UI with:

- dark navy sidebar branding
- orange highlight accents for actions and status
- white cards on a light gray background
- clean spacing and rounded corners
- KPI tiles for operational metrics
- table-based trip and payroll views
- alert lists for operational issues
- compact financial chart widgets

### Design Tokens

```css
:root {
  --bg: #F6F7FB;
  --card: #FFFFFF;
  --card-2: #F9FAFC;
  --text: #0F172A;
  --text-2: #64748B;
  --text-3: #94A3B8;
  --border: #EEF1F6;
  --brand: #4F46E5;
  --brand-soft: #EEF2FF;
  --accent: #F97316;
  --accent-soft: #FFF3E8;
  --green: #059669;
  --green-soft: #E7F8F1;
  --red: #DC2626;
  --red-soft: #FDECEC;
  --amber: #D97706;
  --amber-soft: #FEF4E5;
  --blue: #2563EB;
  --blue-soft: #E9F0FE;
}
```

## User Roles and Permissions

| Role | Access |
|---|---|
| Admin | Full system access |
| Operations | Dispatch, trips, fleet |
| Finance | Invoices, payments, reports |
| HR / Payroll | Employees, payroll, deductions |
| Sales | Leads, customers, quotations |
| Driver | Trip tasks and status updates |

## Data Model Overview

### Employee

- id
- full_name
- employee_number
- id_number
- kra_pin
- phone
- email
- department
- job_title
- employment_status
- start_date
- salary

### Driver

- id
- employee_id
- license_number
- license_expiry
- license_class
- medical_certificate_expiry
- status
- assigned_truck_id

### Truck

- id
- registration_number
- make_model
- capacity_litres
- fuel_type
- insurance_expiry
- inspection_expiry
- road_license_expiry
- status

### Trip

- id
- trip_number
- customer_id
- truck_id
- driver_id
- loading_point
- destination
- loaded_quantity
- delivered_quantity
- fuel_loss
- status
- route

### Payroll

- employee_id
- pay_period
- gross_pay
- taxable_income
- paye
- nssf
- shif
- housing_levy
- other_deductions
- net_pay

### Leads

- id
- lead_name
- company_name
- source
- contact_person
- phone
- email
- status
- next_follow_up
- notes

## Payroll Logic

The system calculates pay using:

Gross Pay = Basic Salary + Allowances + Overtime + Bonuses + Trip Earnings

Net Pay = Gross Pay - Statutory Deductions - Other Approved Deductions

At minimum, it should support:

- PAYE
- NSSF
- SHIF / SHA contributions
- Affordable Housing Levy
- employee loan recoveries
- salary advance repayments
- other approved deductions

## Fuel Variance Tracking

Fuel loss must be tracked for every trip.

Formula:

```text
Fuel Loss = Loaded Quantity - Delivered Quantity
Loss % = (Fuel Loss / Loaded Quantity) * 100
```

When loss is above the configured threshold, the system should: 

- flag the trip
- notify operations
- require investigation
- attach a reason and approval record

## Security and Compliance

- role-based access
- secure user authentication
- password hashing
- session management
- audit logs for important actions
- controlled file uploads
- data retention rules
- configurable compliance settings

## Reporting

The system should include:

- operations summary
- fleet utilization report
- cashflow and profit report
- payroll summary
- statutory summary
- fuel loss report
- customer and sales report
- overdue invoice report
- compliance and document expiry report

## Suggested Tech Stack

### Backend

- PHP
- MySQL or PostgreSQL
- MVC structure
- REST API if needed

### Frontend

- HTML
- CSS
- JavaScript
- responsive dashboard layout

### Optional Future Stack

- Laravel
- React
- Vue
- Tailwind CSS

## Project Status

This project currently includes:

- dashboard experience matching the proposed Sarura operations design
- sidebar navigation and responsive layout
- KPI widgets and revenue cards
- trips overview table
- payroll summary section
- drivers and operational data views
- modular PHP structure for route-to-view rendering

## Future Improvements

- full authentication and role management
- database-backed CRUD for drivers, trucks, payroll, and trips
- login pages and user management
- invoice and payment workflows
- real reporting and export tools
- mobile app for drivers
- audit dashboard and security controls

## Summary

Sarura Fuel Logistics is a complete operations, payroll, and business management system for a Kenyan fuel transportation company. The platform combines dispatch, fleet, HR, finance, CRM, and reporting into one streamlined environment with a professional dashboard design that reflects the business’s real operational needs.
