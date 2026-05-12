# Work Order Detail: Admin vs Mechanic

## Compared URLs

- `http://localhost/autoshop/modules/admin/work_order_detail.php`
- `http://localhost/autoshop/modules/mechanic/work_order_detail.php`

## Summary

Both pages load and edit the same `work_order` record, but they are meant for different roles.

- `modules/admin/work_order_detail.php` is the full back-office management screen.
- `modules/mechanic/work_order_detail.php` is the simplified shop-floor execution screen.

## Main Differences

| Area | Admin Page | Mechanic Page |
| --- | --- | --- |
| Access | Admin only | Mechanic and Admin |
| Main purpose | Full work-order management | Perform work, self-assign, add shop updates, hand off to billing |
| Customer info links | Customer ID and customer name link to customer detail | Customer info is display-only |
| Priority | Editable | Visible but disabled |
| Status workflow | Can move `NEW` to `PENDING` by assigning a mechanic, can move `PENDING` to `BILLING` through an admin override with reason, can complete billing, and can reopen completed work orders | Can move `NEW` to `PENDING` by assigning a mechanic, and move to `BILLING` when job is completed |
| Customer note | Editable | Read-only |
| Admin note | Editable | Read-only |
| Shop note / mechanic note | Can append or fully edit | Can append only |
| Test Drive | Editable | Not exposed |
| Extra tools | Save, Print, Inspection, Check List placeholder, History, Signature, Close | Save Changes, Inspection, History, Close |
| Security on save | Uses CSRF token validation | No CSRF validation currently |

## Detailed Behavior

## Current Status Transition Rules

| From | To | Admin Page | Mechanic Page | Notes |
| --- | --- | --- | --- | --- |
| `NEW` | `PENDING` | Yes, by assigning a mechanic | Yes, by assigning a mechanic | Normal pickup/start-work transition |
| `PENDING` | `BILLING` | Yes, but only through admin override with required reason | Yes, through `Job Completed (Submit for Billing)` | Mileage is required |
| `BILLING` | `PENDING` | Yes, but only through admin override with required reason | No | Exception path with audit note |
| `BILLING` | `COMPLETED` | Yes | No | Admin normal billing-close transition |
| `COMPLETED` | `NEW` / `PENDING` / `BILLING` / `ON-HOLD` | Yes, through reopen flow with confirmation and reason | No | Reopen path is admin-only in this screen |

## 1. Access Rules

- Admin page:
  - Intended only for admins.
  - Non-admin users are redirected away.
- Mechanic page:
  - Intended for mechanics.
  - Admins are also allowed to open it.

## 2. Editing Scope

### Admin page can edit

- `Priority`
- `Mileage`
- `WO_Req1` to `WO_Req5`
- request checkboxes `Req1` to `Req5`
- `Customer_Note`
- `Admin_Note`
- `Mechanic`
- `TestDrive`
- `Mechanic_Note`

### Mechanic page can edit

- `Mileage`
- `WO_Req1` to `WO_Req5`
- request checkboxes `Req1` to `Req5`
- `Mechanic`
- append to `Mechanic_Note`

### Mechanic page cannot directly edit

- `Priority`
- `Customer_Note`
- `Admin_Note`
- `TestDrive`

## 3. Status Flow Differences

### Admin page

- If the work order is `NEW` and a mechanic is assigned, status moves to `PENDING`.
- If the work order is `PENDING`, admin can move it to `BILLING` through an explicit override action with a required reason.
- That override appends an audit note to `Admin_Note`.
- That override is shown only when the current status is `PENDING`.
- Moving from `PENDING` to `BILLING` requires a mileage value.
- If the work order is `BILLING`, admin can return it to `PENDING` through an explicit override action with a required reason.
- That return also appends an audit note to `Admin_Note`.
- That return override is shown only when the current status is `BILLING`.
- Can mark billing complete and move a work order to `COMPLETED`, but only when the current status is `BILLING`.
- The `Billing Completed` control is shown only when the current status is `BILLING`.
- If a work order is already completed, admin can reopen it.
- Reopening requires:
  - explicit reopen checkbox
  - reopen reason
  - confirmation checkbox
  - valid target status such as `NEW`, `PENDING`, `BILLING`, or `ON-HOLD`

### Mechanic page

- If the work order is `NEW` and a mechanic is assigned, status moves to `PENDING`.
- If `Job Completed` is checked while the current status is `PENDING`, status moves to `BILLING`.
- `Job Completed (Submit for Billing)` is not available from `NEW`.
- The `Job Completed (Submit for Billing)` control is shown only when the current status is `PENDING` or already `BILLING`.
- Moving from `PENDING` to `BILLING` requires a mileage value.
- Mechanic page does not provide the full reopen flow.

### Important dashboard note

- In the current implementation, a reopened work order set to `PENDING` will not appear in the mechanic dashboard unless it is assigned to a mechanic.
- Unassigned work is expected to stay in `NEW` for the top mechanic queue.
- Because of that, reopening an order to `PENDING` without assigning a mechanic can create a hidden state where admin sees it, but mechanics do not see it in their list.

## 4. Notes Behavior

### Admin page

- `Customer_Note` is editable.
- `Admin_Note` is editable.
- `Mechanic_Note` supports two modes:
  - append with timestamp and username
  - full edit mode

### Mechanic page

- `Customer_Note` is read-only.
- `Admin_Note` is read-only.
- `Mechanic_Note` is read-only in the textarea itself, but the page allows appending through the prompt action.

## 5. Buttons and Tools

### Admin page buttons

- `Save`
- `Print`
- `Inspection`
- `Check List`
- `History`
- `Signature`
- `Close`

### Mechanic page buttons

- `Save Changes`
- `Inspection`
- `History`
- `Close`

### Mechanic history access

- Mechanic page now includes a `History` button.
- It opens the same VIN-based history view in read-only form and returns the user to the mechanic detail screen.

## 6. Practical Meaning

The admin page is meant for supervisors, front office, and full work-order control.

The mechanic page is meant for day-to-day shop execution:

- pick up work
- update mileage and work items
- leave shop notes
- assign the mechanic
- mark the job ready for billing

The admin page is the control screen for:

- assigning `NEW` work
- handling exceptions
- advancing `BILLING` to `COMPLETED`
- reopening completed orders when needed

## 7. Current Implementation Note

There is also a security difference in the current code:

- Admin save flow validates CSRF tokens.
- Mechanic save flow currently does not validate CSRF tokens.

That means the admin page is currently safer than the mechanic page from a form-submission protection perspective.

## Code References

- `modules/admin/work_order_detail.php`
- `modules/mechanic/work_order_detail.php`
- `includes/models/WorkOrder.php`
