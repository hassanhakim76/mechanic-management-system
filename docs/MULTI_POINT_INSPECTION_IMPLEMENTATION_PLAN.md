# Multi-Point Vehicle Inspection Implementation Plan

Date: 2026-05-02

## Purpose

Build a multi-point vehicle inspection module connected to work orders and vehicles.

The inspection will let a mechanic check the full vehicle during a work order, record findings, attach customer-safe evidence, and produce a customer-facing report.

This module should be separate from the current work order detail form. The work order will link to it, but the inspection should have its own data model, forms, and PDF/reporting flow.

## Core Decisions

### Inspection Is Linked to Work Order and Vehicle

Each inspection belongs to:

- a specific work order visit
- the vehicle history

This lets the shop answer both questions:

- What was inspected during work order `PREC-007129`?
- What has changed on this vehicle across previous inspections?

Recommended relationships:

```text
work_order.WOID
    -> vehicle_inspections.WOID

customer_vehicle.CVID
    -> vehicle_inspections.CVID

vehicle_inspections.inspection_id
    -> vehicle_inspection_items.inspection_id
```

### Categories and Items Must Be Editable

The 46 inspection items should not be hardcoded in PHP.

They should be stored in a master table so the shop can:

- add categories
- rename categories
- reorder categories
- add inspection items
- rename inspection items
- change check descriptions
- disable old items
- re-enable items

Important: do not permanently delete master items that may be referenced by old inspections. Use `active = 0` instead.

### Historical Snapshot Required

When an inspection is created, each inspection result row should store a snapshot of:

- category name
- item number / display order
- item label
- check description

Reason:

If the master checklist is changed later, old inspection reports must still show what was inspected at that time.

## Implementation Phases

## Phase 1: Database Migration

Create migration:

```text
database/2026_05_02_multi_point_vehicle_inspection.sql
```

The migration should create:

1. `inspection_categories`
2. `inspection_item_master`
3. `vehicle_inspections`
4. `vehicle_inspection_items`
5. optional `vehicle_inspection_photos`

### 1. inspection_categories

Purpose:

Manage editable inspection categories.

Suggested fields:

```sql
category_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
category_name VARCHAR(80) NOT NULL,
display_order INT NOT NULL DEFAULT 0,
active TINYINT(1) NOT NULL DEFAULT 1,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL
```

Initial categories:

```text
Brakes
Tires and Wheels
Under Hood - Fluids
Under Hood - Components
Undercarriage
Lights and Electrical
Wipers and Glass
Interior and Safety
```

### 2. inspection_item_master

Purpose:

Editable master checklist used to create new inspections.

Suggested fields:

```sql
master_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
category_id INT UNSIGNED NOT NULL,
item_number INT NOT NULL,
item_label VARCHAR(120) NOT NULL,
check_description VARCHAR(255) NULL,
display_order INT NOT NULL DEFAULT 0,
active TINYINT(1) NOT NULL DEFAULT 1,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL,
FOREIGN KEY (category_id) REFERENCES inspection_categories(category_id)
```

Important:

- `item_number` is the visible/spec number.
- `display_order` controls ordering.
- `active = 0` hides the item from future inspections without breaking history.

### 3. vehicle_inspections

Purpose:

Header record for each inspection.

Suggested fields:

```sql
inspection_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
WOID INT NOT NULL,
CVID INT NULL,
CustomerID INT NULL,
mechanic VARCHAR(80) NULL,
mileage_at_inspect VARCHAR(40) NULL,
status ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
overall_notes TEXT NULL,
created_by VARCHAR(80) NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at DATETIME NULL,
completed_at DATETIME NULL,
UNIQUE KEY uq_vehicle_inspection_woid (WOID),
KEY idx_vehicle_inspections_cvid_created (CVID, created_at),
FOREIGN KEY (WOID) REFERENCES work_order(WOID)
    ON DELETE CASCADE
    ON UPDATE CASCADE
```

Notes:

- Use one inspection per work order.
- `CVID` and `CustomerID` are copied from the work order for reporting convenience.
- `mechanic` can be copied from the work order at creation and editable if needed.

### 4. vehicle_inspection_items

Purpose:

Stores the actual ratings/notes for an inspection.

Suggested fields:

```sql
inspection_item_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
inspection_id INT UNSIGNED NOT NULL,
master_item_id INT UNSIGNED NULL,
category_id INT UNSIGNED NULL,
category_name VARCHAR(80) NOT NULL,
item_number INT NOT NULL,
item_label VARCHAR(120) NOT NULL,
check_description VARCHAR(255) NULL,
rating ENUM('good','watch','repair','na') NULL,
note TEXT NULL,
display_order INT NOT NULL DEFAULT 0,
updated_at DATETIME NULL,
FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(inspection_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE
```

Important:

- `rating` starts as `NULL` for unrated items.
- Completed inspection requires all active snapshot items to have a rating.
- `watch` and `repair` require a note.

### 5. vehicle_inspection_photos

Optional but recommended.

Purpose:

Attach photos to specific inspection findings, not only work order W.I. rows.

Suggested fields:

```sql
photo_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
inspection_id INT UNSIGNED NOT NULL,
inspection_item_id INT UNSIGNED NULL,
file_path VARCHAR(255) NOT NULL,
thumbnail_path VARCHAR(255) NULL,
caption VARCHAR(255) NULL,
original_name VARCHAR(255) NULL,
mime_type VARCHAR(80) NULL,
file_size INT UNSIGNED NULL,
show_on_customer_pdf TINYINT(1) NOT NULL DEFAULT 1,
uploaded_by VARCHAR(80) NULL,
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (inspection_id) REFERENCES vehicle_inspections(inspection_id)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
FOREIGN KEY (inspection_item_id) REFERENCES vehicle_inspection_items(inspection_item_id)
    ON DELETE SET NULL
    ON UPDATE CASCADE
```

Storage path:

```text
uploads/inspection_photos/PREC-007129/
```

## Phase 2: Seed Master Checklist

Insert the initial 8 categories and 46 items.

Use the spec already reviewed:

- Brakes: 7 items
- Tires and Wheels: 7 items
- Under Hood - Fluids: 6 items
- Under Hood - Components: 6 items
- Undercarriage: 6 items
- Lights and Electrical: 7 items
- Wipers and Glass: 3 items
- Interior and Safety: 4 items

Migration should be safe enough to avoid duplicate seed data when rerun.

Recommended strategy:

- insert categories by unique category name
- insert items by unique `(category_id, item_number)` or `(category_id, item_label)`

## Phase 3: Models

Add model:

```text
includes/models/VehicleInspection.php
```

Optional split later:

```text
includes/models/VehicleInspectionPhoto.php
```

### VehicleInspection Methods

Required methods:

```php
getById($inspectionId)
getByWorkOrder($woid)
getOrCreateForWorkOrder($woid)
getItems($inspectionId)
saveItems($inspectionId, array $items)
saveOverallNotes($inspectionId, $overallNotes)
complete($inspectionId)
reopen($inspectionId)
getSummaryCounts($inspectionId)
getPreviousByVehicle($cvid, $excludeInspectionId = null)
getCustomerReportData($inspectionId)
```

### getOrCreateForWorkOrder

Behavior:

1. Load work order with customer and vehicle.
2. If inspection exists for `WOID`, return it.
3. Create inspection header.
4. Copy active master items into `vehicle_inspection_items` as snapshot rows.
5. Return the new inspection.

### complete

Validation:

- every item must have a rating
- `watch` requires note
- `repair` requires note

If valid:

- set `status = completed`
- set `completed_at = NOW()`

If invalid:

- return errors showing missing ratings/notes

## Phase 4: Bootstrap

Update:

```text
includes/bootstrap.php
```

Load:

```php
require_once __DIR__ . '/models/VehicleInspection.php';
```

If photo model is separate:

```php
require_once __DIR__ . '/models/VehicleInspectionPhoto.php';
```

## Phase 5: Mechanic Inspection Form

Create:

```text
modules/mechanic/inspection.php
```

URL:

```text
modules/mechanic/inspection.php?woid=7129
```

Purpose:

Mechanic starts/resumes inspection for a work order.

### Page Behavior

On GET:

- require login
- allow mechanic/admin
- load work order
- get or create inspection
- render categories/items

On POST save partial:

- verify CSRF
- save ratings and notes
- save overall notes
- keep status `in_progress`
- redirect back with success message

On POST complete:

- verify CSRF
- save ratings and notes
- validate completion rules
- if valid, set completed
- if invalid, show errors and keep form open

### Form UI

Recommended layout:

- work order/vehicle summary header
- summary count sidebar
- category sections
- each item row:
  - item label
  - check description
  - rating segmented buttons:
    - Good
    - Watch
    - Repair
    - N/A
  - note textarea
  - photo button later

### Required Note Rule

Client-side:

- if rating is Watch or Repair, visually mark note required
- block complete if missing

Server-side:

- always enforce the rule again

## Phase 6: Admin Inspection View

Create:

```text
modules/admin/inspection_view.php
```

URL:

```text
modules/admin/inspection_view.php?woid=7129
```

Purpose:

Admin can review inspection but does not normally fill it out.

Features:

- read-only inspection summary
- detail by category
- previous inspections for same vehicle
- print/PDF button
- reopen button if admin needs to send it back to mechanic

## Phase 7: Manage Inspection Template

Create admin management screen:

```text
modules/admin/inspection_template.php
```

Purpose:

Allow editing categories and items.

Admin can:

- add category
- rename category
- reorder category
- deactivate category
- add item
- rename item
- edit check description
- reorder item
- deactivate item

Rules:

- use deactivate instead of hard delete
- old inspections keep snapshot labels
- active master records affect future inspections only

## Phase 8: PDF Report

Create:

```text
includes/pdf/MultiPointInspectionPdf.php
modules/admin/inspection_pdf.php
```

PDF should be admin-only initially.

Mechanic may view inspection, but PDF generation can stay admin/front-desk controlled.

### PDF Sections

1. Header
   - shop name/logo
   - report title
   - date

2. Vehicle and inspection summary
   - customer
   - vehicle
   - VIN
   - mileage
   - mechanic
   - work order number

3. Summary counts
   - Good
   - Watch
   - Repair
   - N/A

4. Recommendations
   - Repair now
   - Watch soon

5. Detail by category
   - item
   - rating
   - note if present

6. Photos
   - selected customer photos from inspection findings

7. Disclaimer / signature area if needed

### PDF Rating Colors

Use:

```text
Good = green
Watch = yellow
Repair = red
N/A = gray
```

### PDF Sample Already Created

Review sample:

```text
docs/generated/multi_point_inspection_report_sample.pdf
```

## Phase 9: Work Order Integration

Update admin work order detail:

```text
modules/admin/work_order_detail.php
```

Inspection button behavior:

- if inspection exists, open admin inspection view
- if no inspection exists, show no completed report yet or open view with start information

Update mechanic work order detail:

```text
modules/mechanic/work_order_detail.php
```

Inspection button behavior:

- open mechanic inspection form
- create/resume inspection

Current buttons already include `Inspection`; they can be redirected to the new inspection pages.

## Phase 10: Customer Work Order PDF Integration

Later, add option to current customer PDF options:

```text
Include Inspection Summary
```

If enabled, customer work order PDF can show:

- inspection status
- Good / Watch / Repair / N/A counts
- repair-now recommendations
- watch-soon recommendations

Do not embed the full inspection detail in the work order PDF at first.

Keep full inspection report as a separate PDF.

## Phase 11: Recommendations and Follow-Up Work

After base module works, add recommendation workflow.

Any item rated:

- `watch`
- `repair`

can become a recommendation candidate.

Future admin action:

```text
Convert to Work Order Item
Convert to Estimate Line
Create Follow-Up Reminder
```

This is where the inspection becomes a sales/service planning tool.

## Permissions

### Mechanic

Can:

- start inspection for visible/assigned work order
- save partial
- complete inspection
- upload inspection photos later

Cannot:

- edit inspection template
- generate official customer PDF unless allowed later
- edit completed inspection unless reopened

### Admin

Can:

- view all inspections
- print/generate PDF
- reopen inspection
- manage master template
- view previous inspections

### Front Desk

Optional later:

- view completed inspections
- generate/print customer PDF
- no edit access

## Validation Rules

### Save Partial

Allowed even if:

- not all items rated
- watch/repair notes missing

But UI should still show incomplete/required markers.

### Complete Inspection

Required:

- all items have rating
- watch items have notes
- repair items have notes

Optional:

- overall notes
- photos

## Status Rules

Inspection statuses:

```text
in_progress
completed
```

Potential future statuses:

```text
reopened
void
```

Start simple with only `in_progress` and `completed`.

## Photo Plan

Inspection photos should be independent from W.I. photos.

Reason:

- Work order photos document requested work.
- Inspection photos document general vehicle condition/findings.

Storage:

```text
uploads/inspection_photos/PREC-007129/
```

Filename examples:

```text
inspection_item_001_repair_20260502_103000_ab12cd34.jpg
inspection_general_20260502_103100_cd34ef56.jpg
```

PDF should include only photos marked:

```text
show_on_customer_pdf = 1
```

## Existing Mockups and Samples

HTML form sample:

```text
docs/mockups/multi_point_inspection_form_sample.html
```

PDF report sample:

```text
docs/generated/multi_point_inspection_report_sample.pdf
```

These are review-only samples.

They are not connected to the database or work order workflow.

## Build Order for Tomorrow

Recommended order:

1. Create database migration with categories, master items, inspections, inspection items.
2. Run migration locally.
3. Add `VehicleInspection` model.
4. Load model in bootstrap.
5. Create mechanic inspection form.
6. Add save partial.
7. Add complete validation.
8. Link mechanic work order `Inspection` button to new form.
9. Create admin read-only inspection view.
10. Link admin work order `Inspection` button to admin view.
11. Create FPDF inspection report.
12. Add admin PDF button.
13. Test with one existing work order.
14. Document final usage.

## Testing Checklist

### Database

- tables created
- categories seeded
- 46 active master items seeded
- duplicate seed does not create duplicates
- one inspection per work order enforced

### Mechanic Form

- opens from work order
- creates inspection if missing
- resumes existing inspection
- saves partial
- preserves ratings and notes
- watch/repair notes visually required
- complete blocks missing ratings
- complete blocks missing watch/repair notes
- completed inspection becomes read-only or protected

### Admin View

- admin can view completed inspection
- admin can view in-progress inspection
- admin can see previous inspections for same vehicle
- mechanic cannot access admin management pages

### Template Management

- admin can add category
- admin can rename category
- admin can deactivate category
- admin can add item
- admin can rename item
- admin can deactivate item
- old inspections keep old labels

### PDF

- summary counts correct
- repair recommendations show first
- watch recommendations show second
- good items appear in detail
- N/A items appear in detail
- long notes wrap correctly
- multi-page report has header/footer
- PDF opens from admin view

## Risks and Decisions to Confirm

1. Should admin be allowed to edit inspection results, or only reopen them for mechanic?
2. Should front desk be allowed to print inspection PDFs?
3. Should inspection be optional for every work order, or required before billing?
4. Should photos be included in phase one, or added after base checklist works?
5. Should completed inspection be locked permanently unless admin reopens?
6. Should previous inspections be visible to mechanics while filling out a new one?

## Recommendation

Start with:

- database
- master checklist
- mechanic form
- save partial
- complete validation
- admin read-only view

Then add:

- PDF
- photos
- template management UI

This keeps the first implementation controlled while still laying the correct foundation.

