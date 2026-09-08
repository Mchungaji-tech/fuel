# UI and Styling Specification

## 1. Brand Direction

The system should feel modern, trusted, and highly operational.

### Brand attributes
- reliable
- professional
- safe
- logistics-focused
- Kenyan-business friendly

## 2. Color Palette

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

## 3. Layout

### Main page layout
```text
+------------------------------------------------------------+
| Sidebar | Topbar                                           |
|---------|---------------------------------------------------|
| Menu    | KPI cards                                        |
| Items   | Alerts & latest activity                         |
|         | Tables and analytics                             |
|         | Forms and details                                |
+------------------------------------------------------------+
```

### Sidebar menu
- Dashboard
- Drivers
- Trucks
- Trips
- Customers
- Leads
- Orders
- Payroll
- Invoices
- Expenses
- Settings

## 4. Buttons

```css
.btn-primary {
  background-color: var(--accent);
  color: white;
  border-radius: 8px;
  padding: 10px 16px;
  font-weight: 600;
}

.btn-secondary {
  background-color: white;
  color: var(--text-primary);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 16px;
}

.btn-danger {
  background-color: var(--danger);
  color: white;
  border-radius: 8px;
  padding: 10px 16px;
}
```

## 5. Cards

```css
.card {
  background: var(--card);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04);
}
```

## 6. KPI Cards

```css
.kpi-card {
  background: white;
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 16px;
}

.kpi-title {
  color: var(--text-secondary);
  font-size: 13px;
}

.kpi-value {
  color: var(--text-primary);
  font-size: 24px;
  font-weight: 700;
}
```

## 7. Status Badges

```css
.badge-success { background: #DCFCE7; color: #166534; }
.badge-warning { background: #FEF3C7; color: #92400E; }
.badge-danger { background: #FEE2E2; color: #991B1B; }
.badge-info { background: #DBEAFE; color: #1E40AF; }
.badge-neutral { background: #F1F5F9; color: #334155; }
```

## 8. Priority Screens

### Dashboard
- KPI cards
- new leads
- trips today
- revenue and expenses
- payroll due notices
- alerts panel

### Trips page
- date and status filters
- table with truck, driver, product, quantity, status
- action buttons for update and invoice

### Payroll page
- payroll period selector
- summary values
- employee payroll table
- statutory breakdown
- approval panel

### Driver page
- photo or avatar
- license status
- assigned truck
- trip history
- documents

## 9. Mobile Interface Direction

For driver mobile screens:
- large buttons
- simple navigation
- clear trip statuses
- camera upload for proof of delivery
- minimal typing
- strong contrast

---

This UI system is simple, professional, and suitable for an operational fleet business in Kenya.
