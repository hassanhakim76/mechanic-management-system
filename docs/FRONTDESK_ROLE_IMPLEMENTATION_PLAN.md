# Frontdesk Role Implementation Plan

## Purpose

Frontdesk should become a real operating role in the autoshop system.

The role should manage the customer-facing side of the shop: intake, appointments, customer communication, approvals, pickup, and customer records. It should not have full admin power and should not perform mechanic-only repair work.

This plan is for future implementation.

## Current App Status

The app already has a `Frontdesk` role:

- `ROLE_FRONTDESK = 3`
- The role is created in the roles table.
- Users can be assigned to Frontdesk from the Users Control page.

Current Frontdesk access is limited:

- Can manage draft intake pages.
- Can approve draft intake.
- Can access customer detail.
- Can edit some customer-facing notes through model logic.

Current problem:

- Login sends every non-admin user to the mechanic work order page.
- Frontdesk does not yet have a dedicated dashboard.
- Frontdesk permissions are not consistently applied across the app.

## Recommended Role Definition

Frontdesk should be responsible for the full customer counter workflow.

Frontdesk should handle:

- Customer intake
- Appointment scheduling
- Customer and vehicle lookup
- New customer creation
- New vehicle creation
- Draft intake review
- Work order creation from approved intake
- Customer notes
- Customer communication
- Customer approval tracking
- Pickup / billing support
- Printing or sending customer-facing reports

Frontdesk should not handle:

- User management
- System settings
- Inspection template settings
- Mechanic-only repair updates
- Completing inspections
- Admin status overrides
- Deleting core records
- Returning billing work orders back to pending

## Target Frontdesk Workflow

### 1. Customer Arrival / Intake

Frontdesk should be able to:

- Search existing customers.
- Search existing vehicles by VIN, plate, phone, name, or customer ID.
- Create a new customer.
- Create a new vehicle.
- Capture VIN, plate, year, make, model, color, mileage, phone, email, and notes.
- Create or approve an intake draft.
- Create a work order with customer complaints and requested services.

### 2. Appointment Flow

Future appointment module should allow Frontdesk to:

- Create appointments.
- Edit appointment date/time.
- Track appointment status:
  - Scheduled
  - Confirmed
  - Arrived
  - No-show
  - Cancelled
  - Converted to work order
- Convert an appointment into a work order.
- View today's arrivals.
- View upcoming appointments.

### 3. Work Order Reception

Frontdesk should be able to:

- View all active work orders.
- Open a limited Frontdesk work order view.
- Add customer-facing notes.
- Add customer complaints.
- Mark the vehicle as dropped off.
- Add pickup promise time.
- Add waiting status:
  - Waiting customer
  - Dropped off
  - Needs phone call
  - Waiting for approval
  - Ready for pickup
- Upload check-in photos if needed.

### 4. Customer Communication

Frontdesk should be able to:

- Log phone calls.
- Log text/email communication manually.
- Mark customer as notified.
- Track customer approval pending.
- Send or print:
  - Work order PDF
  - Inspection PDF
  - Customer report
  - Estimate/authorization form when implemented

Future enhancement:

- SMS/email integration.
- Communication history table.
- Customer communication timeline.

### 5. Estimate and Approval Flow

Frontdesk should become the bridge between mechanic findings and customer authorization.

Future approval system should include:

- Recommended repairs from inspection.
- Estimate line items.
- Parts, labor, and price.
- Customer approved / declined / deferred status.
- Approval timestamp.
- Approved by name.
- Signature or digital authorization.
- Notes about declined repairs.
- Ability to carry declined recommendations into future visits.

### 6. Pickup and Billing Support

Frontdesk should be able to:

- See work orders in Billing / Ready for Pickup.
- Print customer report.
- Print invoice when billing is ready.
- Mark customer notified.
- Mark pickup scheduled.
- Record pickup notes.

If payment module is added later, Frontdesk may also:

- Record payment method.
- Mark invoice paid.
- Print receipt.
- Send payment link.

## Recommended Pages

### New Pages

Create a new module folder:

```text
modules/frontdesk/
```

Recommended pages:

```text
modules/frontdesk/dashboard.php
modules/frontdesk/work_orders.php
modules/frontdesk/work_order_view.php
modules/frontdesk/customer_search.php
modules/frontdesk/customer_form.php
modules/frontdesk/vehicle_form.php
modules/frontdesk/appointments.php
modules/frontdesk/appointment_form.php
modules/frontdesk/approvals.php
```

### Existing Pages to Reuse

Reuse or adapt:

```text
modules/intake/review_queue.php
modules/intake/draft_view.php
modules/intake/approve.php
modules/admin/customer_detail.php
modules/admin/work_order_pdf.php
modules/admin/inspection_pdf.php
```

## First Implementation Phase

### Phase 1: Routing and Dashboard

1. Fix login routing:

```php
Admin      -> modules/admin/work_orders.php
Mechanic   -> modules/mechanic/work_orders.php
Frontdesk  -> modules/frontdesk/dashboard.php
```

2. Fix `public/index.php` with the same role routing.

3. Create `modules/frontdesk/dashboard.php`.

Dashboard should show:

- Drafts needing review
- Today's active work orders
- Waiting for approval
- Ready for pickup
- Recently updated customers
- Quick buttons:
  - New intake
  - Search customer
  - Review drafts
  - Appointments

### Phase 2: Frontdesk Work Orders

Create `modules/frontdesk/work_orders.php`.

It should show active work orders, but not expose admin/mechanic controls.

Recommended filters:

- New
- Pending
- Billing
- On-Hold
- Waiting approval
- Ready pickup
- Customer name
- VIN
- Phone

### Phase 3: Limited Frontdesk Work Order View

Create `modules/frontdesk/work_order_view.php`.

Allow Frontdesk to edit only:

- Customer note
- Customer complaint/request
- Contact/pickup notes
- Waiting/approval flags
- Mileage at intake if missing
- Customer contact corrections

Do not allow:

- Mechanic assignment unless we decide this is part of Frontdesk dispatch.
- Work item completion.
- Inspection edits.
- Admin status override.
- Billing completion.

### Phase 4: Appointment Module

Create appointment support.

Suggested table:

```sql
CREATE TABLE appointments (
    appointment_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    CustomerID INT NULL,
    CVID INT NULL,
    scheduled_at DATETIME NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'scheduled',
    reason TEXT NULL,
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    converted_woid INT NULL,
    PRIMARY KEY (appointment_id),
    KEY idx_appointments_scheduled (scheduled_at),
    KEY idx_appointments_status (status),
    KEY idx_appointments_customer (CustomerID),
    KEY idx_appointments_vehicle (CVID)
);
```

Recommended statuses:

- scheduled
- confirmed
- arrived
- no_show
- cancelled
- converted

### Phase 5: Customer Approval / Authorization

Create approval records for customer authorization.

Suggested table:

```sql
CREATE TABLE work_order_authorizations (
    authorization_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    WOID INT NOT NULL,
    authorized_by VARCHAR(120) NULL,
    authorization_status VARCHAR(30) NOT NULL DEFAULT 'pending',
    signature_path VARCHAR(255) NULL,
    authorization_note TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (authorization_id),
    KEY idx_authorizations_woid (WOID),
    KEY idx_authorizations_status (authorization_status)
);
```

Future line-item approval table:

```sql
CREATE TABLE work_order_authorization_items (
    authorization_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    authorization_id INT UNSIGNED NOT NULL,
    item_label VARCHAR(255) NOT NULL,
    item_type VARCHAR(40) NULL,
    amount DECIMAL(10,2) NULL,
    customer_decision VARCHAR(30) NOT NULL DEFAULT 'pending',
    note TEXT NULL,
    PRIMARY KEY (authorization_item_id),
    KEY idx_auth_items_authorization (authorization_id)
);
```

Recommended decisions:

- pending
- approved
- declined
- deferred

## Permission Matrix

| Feature | Admin | Mechanic | Frontdesk |
|---|---:|---:|---:|
| Manage users | Yes | No | No |
| System settings | Yes | No | No |
| Inspection settings | Yes | No | No |
| Create/edit inspection | Admin view only | Yes | No |
| View inspection report | Yes | Limited future | Yes |
| Create work order | Yes | No | Yes |
| Edit customer notes | Yes | No | Yes |
| Edit mechanic notes | Yes | Yes | No |
| Move work order to billing | Yes override | Yes when valid | No |
| Complete billing | Yes | No | Optional future |
| Intake drafts | Yes | Limited edit | Yes |
| Appointments | Yes | View only future | Yes |
| Customer/vehicle edit | Yes | No | Yes |
| Customer authorization | Yes | No | Yes |

## Security Rules

Frontdesk access should be explicit, not inherited from Admin.

Recommended helper methods later:

```php
Session::isFrontDesk()
Session::canManageIntake()
Session::canManageCustomers()
Session::canViewCustomerReports()
Session::canManageAppointments()
Session::canManageApprovals()
```

Important:

- Do not let Frontdesk access admin settings pages.
- Do not let Frontdesk access user control.
- Do not let Frontdesk modify inspection templates.
- Do not let Frontdesk complete mechanic work.
- Do not let Frontdesk delete core records.
- Use CSRF on every form.
- Keep audit notes for customer authorization actions.

## Professional Shop Features to Consider Later

Advanced shop systems commonly include:

- Digital estimates
- Digital authorization
- E-signatures
- Two-way customer communication
- Digital vehicle inspections
- Photos/videos with notes
- Appointment reminders
- Customer history
- Deferred services
- Recommended services
- Workflow boards
- Payments
- Reporting
- Role-based permissions

Good future additions for this app:

- Customer communication timeline
- Declined service follow-up
- Appointment reminders
- Estimate builder
- Payment tracking
- Service advisor dashboard
- "Waiting for customer approval" status
- "Ready for pickup" status

## Suggested Implementation Order

1. Fix Frontdesk login/index routing.
2. Create Frontdesk dashboard.
3. Create Frontdesk work order list.
4. Create limited Frontdesk work order view.
5. Add customer/vehicle search shortcuts.
6. Add appointment table and pages.
7. Add approval/authorization records.
8. Add signature support.
9. Add customer communication history.
10. Add email/SMS integration later.

## References

These products were used as workflow inspiration:

- Tekmetric shop management: https://www.tekmetric.com/feature/shop-management
- Tekmetric workflow/customer experience: https://www.tekmetric.com/features/automotive-repair-workflow
- Shopmonkey estimates and authorizations: https://www.shopmonkey.io/product/estimates
- Shopmonkey workflow basics: https://support.shopmonkey.io/hc/en-us/articles/38744406930068-Getting-Started-Shopmonkey-Basics
- Shopmonkey invoices and payments: https://www.shopmonkey.io/product/invoices

## Final Direction

Frontdesk should become the customer-service and service-advisor role.

The best design is:

- Admin controls the system.
- Mechanic controls technical work.
- Frontdesk controls the customer journey.

