# Sarura Fuel Logistics System Design

## 1. Overview

Sarura Fuel Logistics is a Kenyan fuel transport and business management platform for a company that manages drivers, trucks, fuel deliveries, customer orders, payroll, statutory deductions, taxes, payroll reporting, CRM, and business finance.

The system combines operational management and administrative control in one product so that the business can track:

- Drivers and employees
- Trucks and maintenance
- Fuel trip operations
- Customer leads and sales
- Payroll and employee deductions
- Taxes and statutory compliance
- Invoices, expenses, and profit

---

## 2. Business Goals

1. Manage drivers, trucks, and trip assignments efficiently.
2. Track fuel loads, delivery quantities, and fuel loss.
3. Manage customer accounts, leads, quotations, and orders.
4. Support payroll processing with PAYE, NSSF, SHIF, and housing levy logic.
5. Track deductions, advances, and repayments.
6. Generate reports for operations, staffing, fuel, payroll, and finance.
7. Support Kenyan business compliance and staff governance.

---

## 3. User Roles

### Admin / Owner
- Full system access
- Manage settings and approvals
- View executive dashboards and financial reports

### HR / Payroll Officer
- Manage employee records
- Process payroll
- Apply deductions and recoveries
- Review payslips

### Operations Manager
- Create and assign trips
- Manage truck and driver scheduling
- Review delivery performance

### Fleet Manager
- Track maintenance and expiry dates
- Validate truck readiness
- Monitor utilization and safety

### Finance / Accounts
- Invoices and payments
- Expenses and reconciliations
- Profit & loss reporting

### Sales / CRM Officer
- Leads, follow-ups, quotations, customer conversion
- Sales pipeline reporting

### Driver
- View assigned trips
- Update trip milestones
- Upload proof of delivery

---

## 4. Main Modules

### 4.1 Employee Management

Handles organization users and staff.

Fields:
- employee_number
- full_name
- national_id
- kra_pin
- nssf_number
- shif_number
- phone
- email
- department
- job_title
- employment_type
- employment_date
- status
- bank_account
- mpesa_number

Rules:
- employee must be active before payroll processing
- documents must be uploaded and tracked
- contract expiry and probation dates should be monitored

### 4.2 Driver Management

Driver records include:
- license_number
- license_class
- license_expiry
- medical_certificate_expiry
- dangerous_goods_permit
- defensive_driving_expiry
- good_conduct_expiry
- status

Rules:
- cannot assign an expired driver
- must have valid license for truck category
- dangerous goods permit required for fuel movements when policy requires

### 4.3 Fleet Management

Truck records include:
- registration_number
- make
- model
- year
- capacity_litres
- fuel_type
- insurance_expiry
- inspection_expiry
- road_license_expiry
- gps_device_id
- assigned_depot
- status

Rules:
- truck cannot be assigned if under maintenance or suspended
- truck must have valid insurance and inspection
- capacity must suit order quantity

### 4.4 Trip and Logistics Management

Trip lifecycle:
- Planned
- Approved
- Assigned
- Loading
- In Transit
- Offloading
- Delivered
- Completed
- Cancelled
- Disputed

Trip fields:
- trip_number
- customer
- order_id
- product
- truck_id
- driver_id
- loading_point
- offloading_point
- loaded_quantity
- delivered_quantity
- loss_quantity
- loss_percentage
- rate_amount
- total_revenue
- total_cost
- proof_of_delivery
- trip_status

Business logic:
- loaded quantity must be positive
- delivered quantity cannot exceed loaded quantity unless approved adjustment exists
- abnormal fuel variance must trigger investigation

### 4.5 Customer / Sales CRM

Lead fields:
- name
- company_name
- phone
- email
- location
- source
- product_interest
- estimated_volume
- assigned_salesperson
- status
- next_follow_up_date

Lead statuses:
- New
- Contacted
- Qualified
- Quoted
- Negotiation
- Won
- Lost

Customer fields:
- name
- type
- krapin
- phone
- email
- location
- credit_limit
- payment_terms
- status

### 4.6 Orders and Quotations

Quotation and order flow:
- create lead
- qualify lead
- create quotation
- accept quotation
- convert to customer
- create order
- assign trip

Order statuses:
- Draft
- Confirmed
- Assigned to Trip
- In Progress
- Delivered
- Invoiced
- Paid
- Closed
- Cancelled

### 4.7 Payroll and Deduction Management

Primary payroll components:
- Basic salary
- House allowance
- Transport allowance
- Overtime
- Bonus
- Trip allowance
- Commissions
- Other benefits

Statutory deductions:
- PAYE
- NSSF employee contribution
- NSSF employer contribution
- SHIF / SHA contribution
- Housing levy contribution

Other deductions:
- loan repayment
- salary advance
- Sacco
- pension
- missing hours or absence
- damages or recoveries approved by policy

Payroll formula:

Gross Pay = Basic Salary + Allowances + Overtime + Bonuses + Trip Earnings + Other Income

Net Pay = Gross Pay - Statutory Deductions - Other Deductions - Loan Recoveries - Advances

### 4.8 Finance and Invoicing

Finance module includes:
- invoices
- payments
- expenses
- customer statements
- accounts receivable
- profit/loss reports

Invoice fields:
- invoice_number
- customer_id
- trip_id
- issue_date
- due_date
- subtotal
- vat_amount
- total
- status

---

## 5. System Flow

### Lead to Customer Flow

1. Lead is created
2. Salesperson follows up
3. Lead is qualified
4. Quotation is prepared
5. Customer accepts quotation
6. Order is created
7. Dispatch assigns truck and driver
8. Trip is executed
9. Invoice is created and payment follows

### Trip Dispatch Flow

1. Customer order is confirmed
2. System checks product availability
3. Truck availability is checked
4. Driver compliance is validated
5. Trip is approved
6. Loading is completed
7. Vehicle moves to destination
8. Delivery is confirmed
9. Fuel variance is reviewed
10. Invoice is generated

### Payroll Flow

1. Payroll period is opened
2. Active employees are listed
3. Earnings are calculated
4. Statutory deductions are computed
5. Deductions and loans are applied
6. Payroll preview is reviewed
7. Payroll is approved
8. Bank files and payslips are created

---

## 6. Database Design

Core tables:

- companies
- users
- employees
- drivers
- trucks
- customers
- leads
- lead_activities
- quotations
- orders
- trips
- trip_expenses
- salary_structures
- payroll_periods
- payroll_runs
- deductions
- loans
- invoices
- payments
- documents
- audit_logs

### Key Relationships

- one company has many users, employees, trucks, customers
- one employee can be a driver or office worker
- one driver can have many trips
- one truck can have many trips
- one customer can have many orders, invoices, and payments
- one lead can become one customer
- payroll records relate to employees and payroll periods

---

## 7. Security and Compliance

The system should include:

- secure login and password hashing
- role-based access control
- audit logs for all critical actions
- secure file uploads for contracts and documents
- approval workflow for payroll and deductions
- backups and restore process
- compliance with Kenya data protection requirements

---

## 8. Alerts and Notifications

System alerts should include:

- driver license expiry
- truck insurance expiry
- trip delays
- abnormal fuel loss
- invoice due soon
- payroll approval pending
- statutory filing deadlines
- document expiration notices

---

## 9. Dashboard Screens

### Main Dashboard
- KPI cards
- active trucks
- trips today
- revenue this month
- expense summary
- payroll status
- lead pipeline
- alert panels

### Operations Dashboard
- planned trips
- assigned trips
- in-transit jobs
- completed deliveries
- fuel loss status

### Finance Dashboard
- revenue
- expenses
- profit
- overdue invoices
- customer balances

### HR / Payroll Dashboard
- active employees
- leave records
- payroll preview
- deduction totals
- statutory summary

### Sales Dashboard
- leads by source
- conversion rate
- quotations sent
- won deals
- customer pipeline

---

## 10. UI/UX Styling

### Brand personality
- reliable
- professional
- safe
- operational
- modern

### Color palette

```css
:root {
  --primary: #0F172A;
  --primary-dark: #020617;
  --accent: #F97316;
  --accent-dark: #EA580C;
  --success: #16A34A;
  --warning: #F59E0B;
  --danger: #DC2626;
  --info: #2563EB;
  --background: #F8FAFC;
  --card: #FFFFFF;
  --border: #E2E8F0;
  --text-primary: #0F172A;
  --text-secondary: #475569;
}
```

### Layout pattern
- sidebar navigation
- top header with search and user profile
- KPI cards
- scrollable tables
- status badges
- forms with validation

### UI components
- primary button in orange
- secondary button in white with border
- success badges for paid or delivered states
- warning badges for pending, expiring, or due soon
- danger badges for overdue, blocked, or expired

---

## 11. Recommended Implementation Stack

### Web app
- Laravel + Livewire or React + Tailwind
- MySQL or PostgreSQL
- Redis for cache/queue

### Mobile app for drivers
- Flutter or React Native
- offline-friendly forms
- photo upload for proof of delivery
- trip status updates

### Integrations
- SMS notifications
- email notifications
- M-Pesa integration
- bank payment exports
- KRA eTIMS and invoice compliance support

---

## 12. Example Screen Flow

### Dashboard
- summary cards
- alerts panel
- trip table
- payroll summary
- sales pipeline

### Trips page
- date range filter
- driver filter
- truck filter
- status filter
- action buttons

### Payroll page
- select payroll period
- summary cards
- employee payroll ledger
- deduction summary
- approval authority

### Driver profile page
- personal info
- license status
- trips list
- documents
- payroll history
- performance overview

---

## 13. Suggested MVP Scope

To launch quickly, build the MVP around these modules:

1. Employee and driver records
2. Truck and maintenance tracking
3. Trip creation and delivery tracking
4. Lead and customer management
5. Payroll and statutory deductions
6. Invoices and payment tracking
7. Reports and alerts

This MVP is enough to run the business and expand later with mobile app and GPS tracking.

---

## 14. Final Recommendation

The best architecture is a modular ERP-style system with a strong emphasis on operational controls and employee payroll compliance.

The system should prioritize:
- clear ownership of roles
- automated compliance checks
- auditability of payroll and deductions
- trip validation rules
- clean dashboard reporting for management

This will make the system practical for a Kenyan fuel logistics company and scalable as the business grows.
