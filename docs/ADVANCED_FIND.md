# Advanced Find

## Purpose

Advanced Find is the central search page for the admin/frontdesk workflow.

The goal is to let the user search once and see related records separated by type:

- Work Orders
- Customers
- Vehicles
- Draft Intake

This is better than one mixed table because each result type needs different information and different actions.

## Current Files

- `modules/admin/find.php`
- `modules/admin/work_orders.php`
- `includes/models/WorkOrder.php`

## Current Entry Point

The `Find` button in `modules/admin/work_orders.php` opens:

```text
modules/admin/find.php
```

The old work order grid search is still supported through query parameters:

```text
modules/admin/work_orders.php?search_operator=Contain&search_value=VALUE&hide_completed=0&status=All
```

## Current Search Scopes

The page supports these scopes:

| Scope | Meaning |
|---|---|
| All | Search all supported record types |
| Work Orders | Search work orders only |
| Customers | Search customer records only |
| Vehicles | Search vehicle records only |
| Draft Intake | Search online intake draft records only |

## Current Operators

| Operator | Meaning |
|---|---|
| Contain | Value appears anywhere in the field |
| Equal | Exact match |
| Start With | Field starts with value |
| End With | Field ends with value |

## Current Result Layout

Results are grouped by section.

Each result is shown as a single horizontal row:

- Main identity on the left
- Key details in the middle
- Action buttons on the right

Vehicle status is displayed as readable text:

- `Active vehicle`
- `Inactive vehicle`

This replaced the old confusing `A` and `I` display.

## Current Search Coverage

### Work Orders

Searches:

- Work order number
- Raw WOID
- Customer name
- Customer ID
- Phone
- Cell
- Email
- Plate
- VIN
- Vehicle year, make, model, color
- Work requested
- Customer note
- Admin note
- Mechanic note
- Mechanic
- Admin

Actions:

- Open Work Order
- History, when VIN exists
- Open Work Order Grid with the same search

### Customers

Searches:

- Customer ID
- First name
- Last name
- Full name
- Reversed name
- Phone
- Cell
- Email
- Address
- City
- Postal code

Shows:

- Customer name
- Customer ID
- Phone and cell
- Email
- Vehicle count
- Work order count

Actions:

- Open Customer

### Vehicles

Searches:

- CVID
- Customer ID
- Plate
- VIN
- Make
- Model
- Year
- Color
- Full vehicle string
- Customer name
- Phone
- Cell
- Email

Shows:

- Year, make, model
- CVID
- Owner
- Plate
- VIN
- Color
- Work order count
- Active/Inactive vehicle status

Actions:

- Open Customer
- History, when VIN exists

### Draft Intake

Searches:

- Draft work order ID
- Draft customer ID
- Draft vehicle ID
- Draft status
- Work order target status
- Priority
- Work note
- Missing reasons
- Customer name
- Phone
- Cell
- Email
- Plate
- VIN
- Make
- Model
- Year
- Created by username

Shows:

- Draft ID
- Created date/time
- Customer name
- Draft status
- Phone/cell
- Vehicle
- Plate
- VIN

Actions:

- Open Draft
- Approve, when draft status is `draft`

## Important Search Rule

Alphanumeric searches such as plates must not be treated as numeric ID searches.

Example:

```text
FAAY159
```

This should search as a plate, not as ID `159`.

Numeric-only matching should only happen when the input looks numeric or phone-like.

## Production Collation Note

Production may have older or mixed MySQL collations, for example:

```text
utf8mb4_bin
utf8mb4_general_ci
```

Advanced Find and Work Order Find force text search comparisons to:

```text
utf8mb4_general_ci
```

This prevents MySQL errors such as:

```text
Illegal mix of collations for operation 'like'
```

This is a code-level fix only. It does not require a database migration.

The production-compatible search avoids MySQL `REPLACE(...)` normalization in SQL because some online environments fail before the outer collation can be applied. Compact work order searches like `PREC007120` are handled without `REPLACE`.

## Known Test Cases

### FAAY159

Expected current local database result:

| Result Type | Count |
|---|---:|
| Work Orders | 5 |
| Vehicles | 1 |
| Drafts | 0 |

Expected total in Advanced Find scope `All`:

```text
6
```

### 007092

Expected behavior:

- Work order number searches should include completed records.
- This is intentional because exact work order lookup should find the record even if completed records are normally hidden in the grid.

## Database Changes

No database changes were required for Advanced Find phase 1 or phase 2.

The page reads existing tables:

- `work_order`
- `customers`
- `customer_vehicle`
- `draft_work_orders`
- `draft_customers`
- `draft_vehicles`
- `users`

## Completed Phases

### Phase 1: Better Work Order Find

Completed.

Work order grid search was improved to search more real-world terms:

- Work order number
- WOID
- Customer name
- Phone/cell
- VIN
- Plate
- Vehicle details
- Work requested
- Notes
- Mechanic/admin names

Completed work orders are included automatically when the search looks like a specific work order number.

### Phase 2: Advanced Find Page

Completed.

Created a separated search page that groups results by:

- Work Orders
- Customers
- Vehicles
- Draft Intake

Result layout was refined from cards to a compact list-style row while keeping key details visible.

## Future Plan

## Phase 3: Smart Actions From Find

The next step is to make Find operational, not only informational.

### Work Order Actions

Add:

- Create related new work order for same customer/vehicle
- Reopen completed work order, admin only
- Print customer work order PDF
- Print inspection PDF, if an inspection exists
- Open customer profile

### Customer Actions

Add:

- Create new work order for this customer
- Add vehicle
- View all vehicles
- View work order history
- Create intake draft for this customer

### Vehicle Actions

Add:

- Create new work order for this vehicle
- Reactivate inactive vehicle, admin only
- Open vehicle work order history
- Flag possible duplicate by VIN or plate

### Draft Intake Actions

Add:

- Match draft to an existing customer found in search
- Match draft to an existing vehicle found in search
- Cancel draft, admin/frontdesk only
- Convert draft to work order after match review

### Recommended First Phase 3 Item

Add `New Work Order` buttons to Customer and Vehicle results.

This gives frontdesk the biggest workflow improvement:

```text
Find customer or vehicle -> click New Work Order -> work order form opens prefilled
```

## Phase 4: Advanced Filters and Result Controls

Add more control for users who search heavily.

### Filters

Add optional filters:

- Status
- Date range
- Active/inactive vehicles
- Completed/open work orders
- Draft status
- Customer only
- Vehicle only
- Phone only
- VIN/plate only

### Sorting

Add sorting options:

- Newest first
- Oldest first
- Most recent work order
- Customer name
- Plate
- VIN
- Active records first

### View Controls

Add:

- Compact/list view
- Detailed/card view
- Results per section limit
- Show more per section

### Highlighting

Highlight the matched search value inside result rows.

Example:

```text
Plate: FAAY159
```

When searching `FAAY159`, the matched plate should visually stand out.

## Phase 5: Search Intelligence and Data Quality

This phase makes Find act like a shop assistant.

### Duplicate Detection

Detect possible duplicate records:

- Same VIN with different plate
- Same plate with different VIN
- Same phone with similar customer names
- Same customer name with different phone
- Multiple inactive/active vehicle records for same car

### Suggested Matches

When searching from intake or draft approval, show suggested links:

- This draft likely matches customer X
- This draft likely matches vehicle Y
- This VIN already exists under another customer

### Data Quality Alerts

Show warnings for:

- Missing VIN
- Missing phone
- Missing customer name
- Unknown vehicle color
- Duplicate active vehicles
- Incomplete draft intake

### Saved Search History

Optional:

- Store recent searches per user
- Show last 10 searches
- Let frontdesk repeat common searches quickly

### Global Search Shortcut

Optional:

- Add a keyboard shortcut from admin/frontdesk pages.
- Example: `/` focuses global search.

## Security Notes

Advanced Find must remain permission-aware.

Recommended access:

| Role | Access |
|---|---|
| Admin | Full access |
| Frontdesk | Full search, limited admin actions |
| Mechanic | No global find, or mechanic-limited work order search only |

Future action buttons must check permissions server-side.

Do not rely only on hiding buttons in the UI.

## UI Direction

Current preferred layout:

- Grouped sections
- One row per result
- Strong identifiers
- Clear readable status labels
- Actions on the right

Do not return to one mixed table for all result types.

If needed later, each section can have its own table view, but the result types should remain separated.
