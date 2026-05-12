# Inspection and Inspection Settings

## Purpose

This document describes the current Multi-Point Vehicle Inspection feature, the Inspection Settings page, and the customer-facing Inspection PDF.

The old UCDA inspection feature was removed from the live application. The current inspection system uses `VehicleInspection.php`, not the old `Inspection.php`.

## Main Files

### Model

`includes/models/VehicleInspection.php`

Responsibilities:

- Create and read inspections linked to a work order.
- Copy active template items into inspection snapshot rows.
- Save mechanic ratings and notes.
- Complete and reopen inspections.
- Sync missing active template items into in-progress inspections.
- Manage inspection settings categories and items.
- Format item display codes such as `1.01`, `2.03`, `6.07`.

### Mechanic Inspection Form

`modules/mechanic/inspection.php`

Responsibilities:

- Shows the inspection form for mechanics and admins.
- Does not create an inspection just by opening the page.
- Shows a Start Inspection screen if no inspection exists.
- Creates the inspection only when the user clicks `Start Inspection`.
- Allows editing only when the work order is `PENDING` and the inspection is not completed.
- Requires every visible/applicable item to be rated before completion.
- Requires notes for `Watch` and `Repair`.

### Admin Inspection View

`modules/admin/inspection_view.php`

Responsibilities:

- Read-only admin view of inspection results.
- Shows summary counts, recommendations, and item details.
- Allows admin to open the mechanic form.
- Allows admin to reopen a completed inspection only while the work order is `PENDING`.
- Opens the PDF report.

### PDF Endpoint

`modules/admin/inspection_pdf.php`

Responsibilities:

- Admin-only endpoint.
- Loads inspection data, item groups, summary counts, and recommendations.
- Uses `includes/pdf/MultiPointInspectionPdf.php`.
- Streams the PDF to the browser.

### PDF Renderer

`includes/pdf/MultiPointInspectionPdf.php`

Current PDF behavior:

- Two-page report.
- Both pages are landscape.
- Page 1 includes the larger shop logo and report header.
- Page 2 does not show the logo or report header.
- Font size is never below `9`.
- Detail table uses two columns.
- `Note` column is wider than before.
- Long customer-relevant concerns are summarized in Recommendations.

### Settings Hub

`modules/admin/settings.php`

Responsibilities:

- Admin-only settings landing page.
- First settings item is `Inspection Settings`.

### Inspection Settings

`modules/admin/inspection_settings.php`

Responsibilities:

- Full control over inspection categories and items.
- Add categories.
- Rename categories.
- Change category code.
- Change category display order.
- Activate/deactivate categories.
- Add items.
- Rename items.
- Change item code.
- Change item description / What to Check.
- Change item display order.
- Activate/deactivate items.

Existing items stay inside their current category. Moving items between categories was intentionally removed because it was not needed and could cause confusion.

### Backward-Compatible Settings Route

`modules/admin/inspection_template.php`

This file redirects to `inspection_settings.php` so older links do not break.

## Database Tables

### `inspection_categories`

Stores the editable category template.

Important fields:

- `category_id`
- `category_code`
- `category_name`
- `display_order`
- `active`

Example:

| category_code | category_name |
|---:|---|
| 1 | Brakes |
| 2 | Tires and Wheels |
| 6 | Lights and Electrical |

### `inspection_item_master`

Stores editable inspection template items.

Important fields:

- `master_item_id`
- `category_id`
- `item_code`
- `item_number`
- `item_label`
- `check_description`
- `display_order`
- `active`

The visible item code is generated from:

```text
category_code.item_code
```

Examples:

| Visible Code | Internal item_number | Item |
|---|---:|---|
| 1.01 | 101 | Front brake pads |
| 1.02 | 102 | Rear brake pads / shoes |
| 2.01 | 201 | Front left tire |
| 6.07 | 607 | Horn |

`item_number` is kept as an internal compatibility number. The visible code should be shown to users.

### `vehicle_inspections`

Stores one inspection header per work order.

Important fields:

- `inspection_id`
- `WOID`
- `CVID`
- `CustomerID`
- `mechanic`
- `mileage_at_inspect`
- `status`
- `overall_notes`
- `created_by`
- `created_at`
- `completed_at`

Rules:

- One inspection per work order.
- Status is `in_progress` or `completed`.
- New inspections can only be started while the work order is `PENDING`.

### `vehicle_inspection_items`

Stores the snapshot copy of inspection items for a specific inspection.

Important fields:

- `inspection_item_id`
- `inspection_id`
- `master_item_id`
- `category_id`
- `category_code`
- `item_code`
- `category_name`
- `item_number`
- `item_label`
- `check_description`
- `rating`
- `note`
- `display_order`

This table preserves historical inspection wording. Completed inspections should not be changed by later settings edits.

### `vehicle_inspection_photos`

Reserved for future inspection photos.

Important fields:

- `photo_id`
- `inspection_id`
- `inspection_item_id`
- `file_path`
- `thumbnail_path`
- `caption`
- `show_on_customer_pdf`

## Workflow

### Starting an Inspection

Opening the inspection page no longer creates records.

Flow:

1. User opens `modules/mechanic/inspection.php?woid={WOID}`.
2. If no inspection exists, the page shows `Start Multi-Point Inspection`.
3. User clicks `Start Inspection`.
4. System creates a row in `vehicle_inspections`.
5. System copies active categories/items into `vehicle_inspection_items`.

This avoids creating inspections by accident.

### Editing an Inspection

Allowed only when:

- Work order status is `PENDING`.
- Inspection status is `in_progress`.

The mechanic can:

- Select `Good`
- Select `Watch`
- Select `Repair`
- Select `N/A`
- Add notes
- Save progress
- Complete inspection

### Completing an Inspection

Completion validates visible inspection items.

Rules:

- Every visible item must have a rating.
- `Watch` and `Repair` require a note.
- Completed inspections become locked.

### Reopening an Inspection

Admin can reopen only if:

- Inspection is completed.
- Work order is still `PENDING`.

## Settings Behavior

### Active / Inactive

Delete is intentionally implemented as deactivate/reactivate.

Reasons:

- Preserves historical reports.
- Avoids breaking previous inspection snapshots.
- Allows the shop to bring items back later.

### In-Progress Inspections and Settings Changes

When an in-progress inspection is opened:

- Missing active template items are synced into it.
- Inactive categories/items are hidden from the visible inspection.
- Existing saved ratings/notes are preserved.

Completed inspections keep their historical snapshot.

### Category and Item Codes

The current numbering system is category-based.

Examples:

- `1.01` means category 1, item 1.
- `2.03` means category 2, item 3.
- `6.07` means category 6, item 7.

This replaced the old global `1-46` numbering.

## PDF Report

### Endpoint

`modules/admin/inspection_pdf.php?inspection_id={inspection_id}`

or

`modules/admin/inspection_pdf.php?woid={WOID}`

### Layout

Page 1:

- Landscape.
- Logo and report title.
- Vehicle and inspection summary.
- At-a-glance rating counts.
- Recommendations for `Repair` and `Watch`.
- Overall notes if available.

Page 2:

- Landscape.
- No logo/header.
- Compact inspection detail table.
- Two detail columns.
- Wider `Note` column.
- Font size stays at least `9`.

### PDF Source Class

`includes/pdf/MultiPointInspectionPdf.php`

Important methods:

- `render()`
- `Header()`
- `Footer()`
- `renderVehicleSummary()`
- `renderSummaryCounts()`
- `renderRecommendations()`
- `renderDetails()`
- `compactWidths()`

## Important Design Decisions

### No Automatic Create on Page Visit

The system used to create an inspection immediately when the mechanic opened the page.

That caused confusion because:

- A user could open the page by mistake.
- The database would already have an inspection.
- Template changes after opening could create missing item issues.

Now the user must click `Start Inspection`.

### Snapshot Items

Inspection items are copied from the template at start time.

This is important because the shop can later rename or deactivate template items without changing completed inspection history.

### In-Progress Sync

In-progress inspections sync missing active template items when opened.

This handles cases where:

- An inspection was started while some template items were inactive.
- The shop later reactivated those items.
- The inspection is still not completed.

### Completed Inspection Stability

Completed inspections do not change when settings change.

This protects historical accuracy.

## Migrations / Database Resources

Initial multi-point inspection migration:

`database/2026_05_02_multi_point_vehicle_inspection.sql`

Category-based code migration note:

`database/2026_05_02_inspection_category_item_codes.sql`

Run this migration after the initial inspection migration when deploying to a database that does not already have category/item codes. The PHP code expects these columns, so an online database that only has the first migration will fail with an error like:

`Unknown column 'ic.category_code' in 'SELECT'`

The category-code migration added:

- `inspection_categories.category_code`
- `inspection_item_master.item_code`
- `vehicle_inspection_items.category_code`
- `vehicle_inspection_items.item_code`

Existing records were converted so:

- Brakes became category `1`
- Tires and Wheels became category `2`
- Item visible codes became `1.01`, `1.02`, `2.01`, etc.

## Current Entry Points

Admin:

- Work order detail inspection button:
  `modules/admin/inspection_view.php?woid={WOID}`
- Settings:
  `modules/admin/settings.php`
- Inspection settings:
  `modules/admin/inspection_settings.php`
- PDF:
  `modules/admin/inspection_pdf.php?inspection_id={inspection_id}`

Mechanic:

- Work order detail inspection button:
  `modules/mechanic/inspection.php?woid={WOID}`

## Validation Checklist

When changing this feature later, verify:

- Opening inspection page without clicking Start does not create records.
- Starting inspection creates one `vehicle_inspections` row.
- Starting inspection copies active template items.
- Inactive template items do not show in in-progress inspections.
- Reactivated template items sync into in-progress inspections.
- Completed inspections remain unchanged.
- `Watch` and `Repair` require notes before completion.
- PDF still renders with no font below `9`.
- PDF remains two pages for normal 46-item inspections.
