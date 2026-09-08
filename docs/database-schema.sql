CREATE TABLE companies (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    kra_pin VARCHAR(100),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    logo VARCHAR(255),
    currency VARCHAR(10) DEFAULT 'KES',
    financial_year_start DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_id BIGINT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    role VARCHAR(50),
    status VARCHAR(30) DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE employees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    employee_number VARCHAR(50),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    id_number VARCHAR(50),
    kra_pin VARCHAR(50),
    nssf_number VARCHAR(50),
    shif_number VARCHAR(50),
    phone VARCHAR(50),
    email VARCHAR(255),
    date_of_birth DATE,
    gender VARCHAR(20),
    employment_type VARCHAR(30),
    department VARCHAR(100),
    job_title VARCHAR(100),
    start_date DATE,
    end_date DATE,
    status VARCHAR(30),
    bank_name VARCHAR(255),
    bank_account VARCHAR(100),
    mpesa_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE drivers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT,
    license_number VARCHAR(100),
    license_class VARCHAR(50),
    license_expiry DATE,
    dangerous_goods_permit VARCHAR(100),
    medical_expiry DATE,
    good_conduct_expiry DATE,
    defensive_driving_expiry DATE,
    status VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trucks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    registration_number VARCHAR(50) UNIQUE,
    make VARCHAR(100),
    model VARCHAR(100),
    year INT,
    capacity_litres DECIMAL(10,2),
    fuel_type VARCHAR(50),
    chassis_number VARCHAR(100),
    odometer INT,
    insurance_provider VARCHAR(255),
    insurance_policy_number VARCHAR(100),
    insurance_expiry DATE,
    inspection_expiry DATE,
    road_license_expiry DATE,
    gps_device_id VARCHAR(100),
    status VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customers (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    customer_type VARCHAR(50),
    kra_pin VARCHAR(50),
    phone VARCHAR(50),
    email VARCHAR(255),
    location VARCHAR(255),
    credit_limit DECIMAL(12,2),
    payment_terms VARCHAR(50),
    status VARCHAR(30),
    assigned_salesperson_id BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE leads (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255),
    company_name VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    location VARCHAR(255),
    source VARCHAR(50),
    product_interest VARCHAR(255),
    estimated_volume DECIMAL(12,2),
    assigned_salesperson_id BIGINT,
    status VARCHAR(50),
    next_follow_up_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quotations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    quote_number VARCHAR(50),
    customer_id BIGINT,
    lead_id BIGINT,
    issue_date DATE,
    valid_until DATE,
    subtotal DECIMAL(12,2),
    tax_total DECIMAL(12,2),
    total DECIMAL(12,2),
    status VARCHAR(30),
    notes TEXT,
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50),
    customer_id BIGINT,
    quotation_id BIGINT,
    product_id BIGINT,
    quantity DECIMAL(12,2),
    delivery_location VARCHAR(255),
    required_date DATE,
    status VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trips (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    trip_number VARCHAR(50),
    order_id BIGINT,
    customer_id BIGINT,
    truck_id BIGINT,
    driver_id BIGINT,
    product_id BIGINT,
    loading_point_id BIGINT,
    offloading_point_id BIGINT,
    waybill_number VARCHAR(100),
    delivery_note_number VARCHAR(100),
    seal_numbers VARCHAR(255),
    loaded_quantity DECIMAL(12,2),
    delivered_quantity DECIMAL(12,2),
    loss_quantity DECIMAL(12,2),
    loss_percentage DECIMAL(10,4),
    loading_time DATETIME,
    offloading_time DATETIME,
    trip_date DATE,
    status VARCHAR(30),
    rate_type VARCHAR(50),
    rate_amount DECIMAL(12,2),
    total_revenue DECIMAL(12,2),
    total_cost DECIMAL(12,2),
    proof_of_delivery_url VARCHAR(255),
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trip_expenses (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    trip_id BIGINT,
    expense_type VARCHAR(100),
    amount DECIMAL(12,2),
    expense_date DATE,
    description TEXT,
    receipt_url VARCHAR(255),
    created_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE salary_structures (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT,
    basic_salary DECIMAL(12,2),
    house_allowance DECIMAL(12,2),
    transport_allowance DECIMAL(12,2),
    medical_allowance DECIMAL(12,2),
    communication_allowance DECIMAL(12,2),
    trip_allowance_rule VARCHAR(255),
    effective_date DATE,
    status VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payroll_periods (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    period_name VARCHAR(100),
    start_date DATE,
    end_date DATE,
    status VARCHAR(30),
    cut_off_date DATE,
    approved_by BIGINT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payroll_runs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    payroll_period_id BIGINT,
    employee_id BIGINT,
    basic_salary DECIMAL(12,2),
    allowances DECIMAL(12,2),
    overtime DECIMAL(12,2),
    bonuses DECIMAL(12,2),
    gross_pay DECIMAL(12,2),
    nssf_employee DECIMAL(12,2),
    nssf_employer DECIMAL(12,2),
    paye DECIMAL(12,2),
    shif_employee DECIMAL(12,2),
    shif_employer DECIMAL(12,2),
    housing_levy_employee DECIMAL(12,2),
    housing_levy_employer DECIMAL(12,2),
    other_deductions DECIMAL(12,2),
    total_deductions DECIMAL(12,2),
    net_pay DECIMAL(12,2),
    status VARCHAR(30),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE deductions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT,
    deduction_type VARCHAR(50),
    name VARCHAR(150),
    amount_type VARCHAR(30),
    amount DECIMAL(12,2),
    percentage DECIMAL(8,2),
    calculation_base VARCHAR(100),
    start_date DATE,
    end_date DATE,
    balance DECIMAL(12,2),
    status VARCHAR(30),
    approval_status VARCHAR(30),
    consent_document_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE loans (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT,
    loan_type VARCHAR(50),
    principal_amount DECIMAL(12,2),
    interest_amount DECIMAL(12,2),
    total_repayable DECIMAL(12,2),
    monthly_repayment DECIMAL(12,2),
    start_date DATE,
    end_date DATE,
    balance DECIMAL(12,2),
    status VARCHAR(30),
    approved_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE invoices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50),
    customer_id BIGINT,
    order_id BIGINT,
    trip_id BIGINT,
    issue_date DATE,
    due_date DATE,
    subtotal DECIMAL(12,2),
    vat_amount DECIMAL(12,2),
    total DECIMAL(12,2),
    amount_paid DECIMAL(12,2),
    status VARCHAR(30),
    etims_reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_id BIGINT,
    customer_id BIGINT,
    amount DECIMAL(12,2),
    payment_date DATE,
    payment_method VARCHAR(50),
    reference VARCHAR(255),
    status VARCHAR(30),
    received_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE documents (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    entity_type VARCHAR(100),
    entity_id BIGINT,
    document_type VARCHAR(100),
    file_path VARCHAR(255),
    expiry_date DATE,
    status VARCHAR(30),
    uploaded_by BIGINT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE audit_logs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT,
    action VARCHAR(255),
    entity_type VARCHAR(100),
    entity_id BIGINT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
