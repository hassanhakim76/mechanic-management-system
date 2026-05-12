# Application Improvement Roadmap

## Purpose

This file is the living improvement plan for the autoshop application.

Whenever a new improvement is completed, update this file:

- Move the item from planned to completed when applicable.
- Add the changed files.
- Add the date.
- Add any follow-up notes.

The goal is to keep the project direction clear as the app grows.

## Current Overall Rating

Current estimated level:

```text
7.5 / 10
```

Strong areas:

- Real work order workflow
- Mechanic work order flow
- Multi-point inspection
- Customer-facing PDFs
- Users and employees control
- Inspection settings
- Advanced Find
- Draft intake foundation

Areas needing improvement:

- Security consistency
- Frontdesk role/dashboard
- UI consistency
- Mobile layout
- Production migration order
- Legacy code cleanup
- Reporting

## Improvement Priority Order

## 1. Security Pass

Priority:

```text
Critical
```

Why:

The application is now large enough that security must be treated as a foundation, not a later detail.

Planned improvements:

- Verify every form uses CSRF protection.
- Verify every page has role permission checks.
- Confirm admin-only actions cannot be reached by mechanic/frontdesk users.
- Harden file uploads:
  - file type validation
  - file size limit
  - random stored filename
  - no executable uploads
  - upload directories protected from PHP execution
- Hide raw database errors in production.
- Add safer session settings.
- Review login and logout behavior.
- Add audit notes for sensitive actions:
  - user changes
  - employee changes
  - work order reopen
  - inspection settings changes

Recommended first files to review:

- `includes/Session.php`
- `includes/functions.php`
- `includes/bootstrap.php`
- `modules/admin/users.php`
- `modules/admin/employees.php`
- `modules/admin/inspection_settings.php`
- `modules/admin/work_order_detail.php`
- upload-related modules

## 2. Frontdesk Dashboard

Priority:

```text
High
```

Why:

Frontdesk is a real shop role. It should not be forced into admin or mechanic screens.

Planned improvements:

- Create a dedicated frontdesk dashboard.
- Send frontdesk users to their dashboard after login.
- Show:
  - active intake drafts
  - quick customer/vehicle search
  - active work orders
  - billing/pickup queue
  - appointment placeholder
  - quick create work order
- Keep admin settings hidden from frontdesk.
- Allow frontdesk actions that match real counter workflow.

Related plan:

- `docs/FRONTDESK_ROLE_IMPLEMENTATION_PLAN.md`

## 3. UI Consistency Pass

Priority:

```text
High
```

Why:

Some pages now look professional, while older pages still look like developer forms.

Planned improvements:

- Standardize page headers.
- Standardize buttons.
- Standardize forms.
- Standardize tables and result lists.
- Improve spacing and alignment.
- Make mobile behavior consistent.
- Remove raw/developer-looking layouts.
- Use clear badges instead of cryptic values.

Important pages:

- Work orders list
- Work order detail
- Advanced Find
- Users Control
- Employees Control
- Inspection Settings
- Inspection View
- Mechanic inspection form

## 4. Customer and Vehicle Workflow

Priority:

```text
High
```

Why:

Customer and vehicle lookup is central to shop speed.

Planned improvements:

- From Advanced Find, create a new work order for a selected customer.
- From Advanced Find, create a new work order for a selected vehicle.
- Add vehicle directly from customer detail.
- Improve active/inactive vehicle handling.
- Add duplicate detection later:
  - same VIN
  - same plate
  - similar customer phone/name
- Add customer/vehicle history timeline.

Related docs:

- `docs/ADVANCED_FIND.md`
- `docs/USERS_AND_EMPLOYEES_CONTROL.md`

## 5. Inspection Phase 2

Priority:

```text
Medium High
```

Why:

The inspection module is one of the strongest parts of the system. The next step is evidence and customer clarity.

Planned improvements:

- Add photos per inspection item.
- Use `vehicle_inspection_photos`.
- Let mechanic attach photos to Watch/Repair findings.
- Add customer-visible toggle for photos.
- Add customer-visible toggle for notes if needed.
- Add recommendations summary.
- Add previous inspection comparison.
- Consider admin review before sending PDF.

Related docs:

- `docs/inspection_and_setting_inspection.md`
- `docs/MULTI_POINT_INSPECTION_IMPLEMENTATION_PLAN.md`

## 6. Work Order Lifecycle Control

Priority:

```text
Medium High
```

Why:

Status changes should match real shop rules and avoid confusing history.

Current lifecycle:

```text
NEW -> PENDING -> BILLING -> COMPLETE
```

Other supported status:

```text
ON-HOLD
```

Planned improvements:

- Add clear reason when moving to On-Hold.
- Add clear reason when reopening completed work orders.
- Keep completed work orders protected by default.
- Add customer approval status later.
- Add estimate/parts/labor later only if the business chooses that direction.
- Improve status badges and timeline notes.

## 7. Reporting

Priority:

```text
Medium
```

Why:

The app should help the shop understand workload and opportunities.

Planned reports:

- Daily active work orders
- Completed work orders
- Mechanic workload
- Inspection Watch/Repair opportunities
- Customer history report
- Vehicle history report
- Draft intake conversion report
- Future revenue reports if billing/parts/labor are implemented

## 8. Production and Database Migration Control

Priority:

```text
Medium
```

Why:

The project now has multiple SQL migration files. Production deployment needs a clear order.

Planned improvements:

- Create `database/README_INSTALL_ORDER.md`.
- List all migrations in required order.
- Add backup reminder before every production migration.
- Add a database version table later.
- Add rollback notes where practical.
- Keep online/local database differences documented.

Known relevant migrations:

- `database/2026_05_02_multi_point_vehicle_inspection.sql`
- `database/2026_05_02_inspection_category_item_codes.sql`
- `database/2026_05_03_users_employee_link.sql`

## 9. Legacy Cleanup

Priority:

```text
Medium
```

Why:

Some older modules and naming patterns still exist. Cleanup should be gradual and safe.

Planned improvements:

- Keep old UCDA inspection isolated from new inspection.
- Avoid loading legacy models globally if not needed.
- Remove unused old code only after confirming no page depends on it.
- Keep historical data safe.
- Add notes before removing any legacy tables.

## 10. Testing and Quality

Priority:

```text
Medium
```

Why:

As the app grows, manual checking becomes slower and easier to miss.

Planned improvements:

- Add PHP syntax checks for changed files.
- Add focused regression tests for:
  - login roles
  - work order status rules
  - inspection creation
  - inspection completion
  - Advanced Find search behavior
  - users/employees permissions
- Keep test cases documented with expected counts.

Known Find test:

```text
FAAY159 -> 5 work orders, 1 vehicle, 0 drafts
```

## Completed Improvements Log

Use this section whenever an improvement is completed.

### 2026-05-04 - Mechanic Work Orders Mobile Row Open

Status:

```text
Completed
```

Summary:

- Updated mechanic work order rows to use the shared work order row handler.
- Added mobile tap support so tapping a mechanic queue record opens its detail page.
- Added keyboard open support for mechanic queue rows.
- Added the mobile viewport meta tag to the mechanic dashboard.

Files:

- `modules/mechanic/work_orders.php`
- `public/js/main.js`

### 2026-05-03 - Employee Control Accordion List

Status:

```text
Completed trial layout
```

Summary:

- Changed Employee Control from two-column card grid to a compact accordion list.
- Each employee now appears as one row showing identity, contact, linked login, and status.
- Clicking the row opens the full edit form with the same fields as before.
- This reduces scrolling while keeping full employee editing available.

Files:

- `modules/admin/employees.php`
- `docs/USERS_AND_EMPLOYEES_CONTROL.md`

### 2026-05-03 - Users Employee Link Cleanup

Status:

```text
Completed
```

Summary:

- Create User now lists only active employees in the Employee Link dropdown.
- Server-side validation prevents linking a new user to an inactive employee.
- Existing user edit forms can still show inactive linked employees for review/history.
- Inactive employee options are clearly labeled with `(inactive)`.

Files:

- `modules/admin/users.php`
- `docs/USERS_AND_EMPLOYEES_CONTROL.md`

### 2026-05-03 - Advanced Find Production Collation Fix

Status:

```text
Completed
```

Summary:

- Fixed production MySQL collation error in Find searches.
- Search comparisons now force `utf8mb4_general_ci` for text `LIKE` and exact comparisons.
- This prevents mixed collation errors between online tables/parameters.
- No database migration required.

Files:

- `includes/models/WorkOrder.php`
- `modules/admin/find.php`
- `docs/ADVANCED_FIND.md`

### 2026-05-03 - Advanced Find Phase 1 and Phase 2

Status:

```text
Completed
```

Summary:

- Improved work order grid search.
- Added Advanced Find page.
- Separated results into:
  - Work Orders
  - Customers
  - Vehicles
  - Draft Intake
- Changed vehicle status display from `A` / `I` to:
  - Active vehicle
  - Inactive vehicle
- Refined results into a compact list layout.

Files:

- `modules/admin/find.php`
- `modules/admin/work_orders.php`
- `includes/models/WorkOrder.php`
- `docs/ADVANCED_FIND.md`

### 2026-05-03 - Users and Employees Control

Status:

```text
Completed
```

Summary:

- Added Users Control page.
- Added Employees Control page.
- Linked users to employees using `users.employee_id`.
- Improved user role/status/password management.
- Improved employee creation/editing/reactivation.

Files:

- `modules/admin/users.php`
- `modules/admin/employees.php`
- `includes/models/User.php`
- `includes/models/Employee.php`
- `database/2026_05_03_users_employee_link.sql`
- `docs/USERS_AND_EMPLOYEES_CONTROL.md`

### 2026-05-02 - Multi-Point Inspection

Status:

```text
Completed base phase
```

Summary:

- Added multi-point inspection system.
- Added inspection settings for categories/items.
- Added inspection PDF.
- Added category/item code system.
- Linked inspection to work order.
- Prevented inspection from being created just by opening the page.

Files:

- `modules/mechanic/inspection.php`
- `modules/admin/inspection_view.php`
- `modules/admin/inspection_pdf.php`
- `modules/admin/inspection_settings.php`
- `includes/models/VehicleInspection.php`
- `database/2026_05_02_multi_point_vehicle_inspection.sql`
- `database/2026_05_02_inspection_category_item_codes.sql`
- `docs/inspection_and_setting_inspection.md`

## How To Update This File

When we finish a new improvement:

1. Add a new entry under `Completed Improvements Log`.
2. Include the date.
3. Include status.
4. Include summary.
5. Include changed files.
6. Update the priority sections if the improvement changes future plans.

This file should remain the main roadmap for app quality and business workflow improvements.
