# Customer Work Order PDF Report

Last updated: 2026-04-29

## Purpose

The Customer Work Order PDF is a customer-facing report generated from an existing work order.

It is designed to summarize:

- the work order identity and current state
- customer and vehicle information
- requested work items
- action taken / result notes
- customer-safe notes
- selected photo evidence
- optional customer acknowledgement signature area

This report is not an invoice, not an estimate, and not an internal shop audit report.

## Current Implementation Summary

The report is generated with FPDF.

The generation flow is admin-only:

1. Admin opens a work order detail page.
2. Admin clicks `Customer PDF`.
3. The PDF options page opens in a popup.
4. Admin chooses what to include or hide.
5. Admin clicks `Generate PDF`.
6. The PDF opens in a new browser tab.

Mechanics cannot create this report.

- The mechanic detail page does not show a Customer PDF button.
- The PDF endpoint checks `Session::isAdmin()`.
- Non-admin users are redirected away before PDF generation.

## User-Facing Flow

### Entry Point

Admin work order detail page:

```text
modules/admin/work_order_detail.php
```

Button:

```text
Customer PDF
```

The button opens:

```text
modules/admin/work_order_pdf_options.php?woid={WOID}
```

### Options Page

Options page:

```text
modules/admin/work_order_pdf_options.php
```

The options form submits by POST to:

```text
modules/admin/work_order_pdf.php
```

The form uses:

- `method="POST"`
- `target="_blank"`
- `csrfField()`

This keeps PDF creation protected by CSRF while still opening the PDF in a separate tab.

### PDF Endpoint

PDF endpoint:

```text
modules/admin/work_order_pdf.php
```

Responsibilities:

- require login
- require admin role
- require POST
- verify CSRF token
- load the work order by `WOID`
- read report options
- load eligible photos only if photos are enabled
- instantiate `CustomerWorkOrderPdf`
- stream the PDF inline to the browser

Output filename pattern:

```text
customer_work_order_PREC-000001.pdf
```

## Report Options

The admin can choose:

| Option | POST field | Default | Effect |
| --- | --- | --- | --- |
| Include Customer Note | `include_customer_note` | On | Shows `work_order.Customer_Note` |
| Include Work Order Note | `include_work_order_note` | On | Shows `work_order.WO_Note` |
| Include Action Taken | `include_action_taken` | On | Shows `WO_Action1` to `WO_Action5` |
| Include Customer Photos | `include_photos` | On | Enables/disables the entire photo section |
| Include General Photos | `include_general_photos` | On | Shows selected photos where `work_item_index IS NULL` |
| Include W.I. Photos | `include_work_item_photos` | On | Shows selected photos where `work_item_index IS NOT NULL` |
| Include Signature Area | `include_signature` | On | Shows the acknowledgement/signature section |

The options are applied at generation time only.

They are not currently saved as user preferences.

## Report Sections

### 1. Header

Shows:

- optional `Header.jpg` image if available
- application name from `APP_NAME`
- title `Customer Work Order Report`
- page divider

Local logo/header resource:

```text
Header.jpg
```

### 2. Work Order Summary

Shows:

- Work Order number
- Date / Time
- Status
- Priority
- Mileage
- Mechanic

Data source:

```text
work_order
```

Important helper functions:

```text
generateWONumber()
formatDateTime()
```

### 3. Customer and Vehicle

Shows customer data:

- customer name
- customer ID
- phone
- cell
- email
- address

Shows vehicle data:

- year / make / model
- plate
- VIN
- color
- engine

Data source joins are handled by:

```text
includes/models/WorkOrder.php
```

Method:

```php
WorkOrder::getById($woid)
```

### 4. Work Requested and Action Taken

Current columns when Action Taken is enabled:

```text
W.I. | Requested | Action Taken / Result
```

Current columns when Action Taken is disabled:

```text
W.I. | Requested
```

Data mapping:

| PDF column | Work order fields |
| --- | --- |
| `W.I.` | generated from item number 1 to 5 |
| `Requested` | `WO_Req1` to `WO_Req5` |
| `Action Taken / Result` | `WO_Action1` to `WO_Action5` |

Only work item rows with a non-empty `WO_Req#` are printed.

### Why the Done Column Was Removed

The report originally had a `Done` column based on:

```php
Req1, Req2, Req3, Req4, Req5
```

That was misleading.

The `Req#` checkbox currently behaves like an internal workflow checkpoint. It is used to ensure each filled work item has been reviewed before moving the work order to billing.

It does not always mean the requested work was physically completed.

Example:

```text
Requested:
Install unavailable part

Action Taken / Result:
It's not done; we could not find parts.
```

In that scenario, the mechanic may need to check the internal checkbox to move the work order to billing, but the customer report must not say:

```text
Done: Yes
```

That contradiction is why the customer PDF now uses `Action Taken / Result` instead.

### 5. Notes

Customer PDF can show:

- `Customer_Note`
- `WO_Note`

Customer PDF does not show:

- `Admin_Note`
- `Mechanic_Note`

Reason:

- `Admin_Note` is internal office/admin context.
- `Mechanic_Note` may contain internal shop communication.
- Customer-facing notes should be explicit and intentionally selected.

### 6. Customer Photos

The report only includes photos marked:

```text
show_on_customer_pdf = 1
```

Photo table:

```text
work_order_photos
```

Photo fields used:

| Field | Purpose |
| --- | --- |
| `WOID` | links photo to work order |
| `work_item_index` | `NULL` for general photos, 1-5 for W.I. photos |
| `stage` | before, during, after, inspection, internal |
| `category` | category for general photos |
| `caption` | displayed under image |
| `file_path` | relative image path |
| `original_name` | fallback/skipped-photo reference |
| `created_at` | fallback caption when no caption exists |
| `show_on_customer_pdf` | required to include in customer PDF |

Photos are loaded by:

```php
WorkOrderPhoto::getCustomerPdfPhotosByWorkOrder($woid, $filters)
```

The method supports filters:

```php
[
    'include_general_photos' => true,
    'include_work_item_photos' => true,
]
```

### Photo Storage

Uploaded work order photos are stored under:

```text
uploads/work_order_photos/
```

Each work order gets a folder:

```text
uploads/work_order_photos/PREC-007129/
```

Example:

```text
uploads/work_order_photos/PREC-007129/wi1_before_20260427_123000_ab12cd34.jpg
```

The database stores the relative file path:

```text
work_order_photos.file_path
```

### Photo Format Limitation

FPDF image rendering supports JPEG, PNG, and GIF.

The current PDF generator skips unsupported image formats when rendering photos.

Known concern:

- the upload model accepts WEBP
- FPDF does not reliably render WEBP

Recommended future fix:

- convert WEBP to JPG/PNG on upload, or
- block WEBP for photos selected for customer PDF, or
- show a clear warning in the photo manager when a WEBP image is selected for PDF

### 7. Customer Acknowledgement

Optional signature section:

```text
Customer Signature
Date
```

The current report does not capture a digital signature.

It only provides a printable signature area.

## Security Rules

### Admin Only

The report endpoint must stay admin-only.

Current guard:

```php
Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}
```

### CSRF Protection

The PDF endpoint requires POST and verifies CSRF:

```php
if (!verifyCSRFToken(post('csrf_token'))) {
    redirect(...);
}
```

This prevents a third-party page from silently generating PDFs through an admin session.

### Mechanic Restriction

Mechanics cannot create the report because:

- no mechanic UI button exists
- the endpoint rejects non-admin users
- direct URL attempts redirect away

## Implementation Resources

### Main Files

| Resource | Purpose |
| --- | --- |
| `includes/pdf/CustomerWorkOrderPdf.php` | FPDF report class |
| `modules/admin/work_order_pdf_options.php` | Admin options form |
| `modules/admin/work_order_pdf.php` | Admin-only PDF generation endpoint |
| `modules/admin/work_order_detail.php` | Contains Customer PDF button |
| `includes/models/WorkOrder.php` | Loads work order/customer/vehicle data |
| `includes/models/WorkOrderPhoto.php` | Loads selected customer PDF photos |
| `modules/shared/work_order_photos.php` | Photo upload/update UI |
| `database/2026_04_27_work_order_photos.sql` | Base photo table migration |
| `database/2026_04_27_work_order_photo_categories.sql` | General photo category migration |

### Local Library Resource

FPDF is installed locally at:

```text
includes/lib/fpdf/fpdf.php
```

Required by:

```php
require_once __DIR__ . '/../lib/fpdf/fpdf.php';
```

### Database Resources

Primary work order table:

```text
work_order
```

Photo evidence table:

```text
work_order_photos
```

Configuration file:

```text
config/config.php
```

Important:

- Do not copy database passwords into documentation.
- Do not hard-code production database credentials inside report files.
- Keep database connection details centralized in `config/config.php`.

### Upload Resources

Photo upload root:

```text
uploads/work_order_photos/
```

Folder naming:

```text
PREC-000001
```

Generated by:

```php
WorkOrderPhoto::workOrderFolder($woid)
```

## External FPDF References

Official resources:

- FPDF home: https://www.fpdf.org/en/home.php
- FPDF reference manual: https://www.fpdf.org/en/doc
- FPDF MultiCell docs: https://www.fpdf.org/en/doc/multicell.htm
- FPDF table with MultiCells example: https://www.fpdf.org/en/script/script3.php
- FPDF FAQ: https://www.fpdf.org/en/FAQ.php

Why these resources matter:

- `Header()` and `Footer()` are used for repeated page branding and page numbers.
- `SetAutoPageBreak()` is used to avoid writing off the page.
- `MultiCell()` is used for long notes and wrapping table text.
- `Image()` is used for selected work order photos.
- `Output()` streams the PDF to the browser.

## Future Plan: Returning a Done/Outcome Concept Safely

Do not reuse `Req1` to `Req5` as customer-visible done flags.

Those fields are internal workflow checkboxes.

If a customer-visible outcome is needed, create a separate outcome concept.

### Option A: Minimal Change on Current Schema

Add five outcome fields:

```sql
ALTER TABLE work_order
  ADD COLUMN WO_Result1 VARCHAR(40) NULL AFTER WO_Action1,
  ADD COLUMN WO_Result2 VARCHAR(40) NULL AFTER WO_Action2,
  ADD COLUMN WO_Result3 VARCHAR(40) NULL AFTER WO_Action3,
  ADD COLUMN WO_Result4 VARCHAR(40) NULL AFTER WO_Action4,
  ADD COLUMN WO_Result5 VARCHAR(40) NULL AFTER WO_Action5;
```

Suggested values:

```text
completed
not_performed
parts_unavailable
customer_declined
deferred
needs_approval
inspection_only
```

PDF label should be:

```text
Outcome
```

Not:

```text
Done
```

Example customer PDF row:

```text
W.I. 1 | Install unavailable part | Parts Unavailable | Could not find parts.
```

### Option B: Better Long-Term Schema

Normalize work items into their own table:

```sql
CREATE TABLE work_order_items (
    item_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    WOID INT NOT NULL,
    item_index TINYINT UNSIGNED NOT NULL,
    requested VARCHAR(255) NOT NULL,
    action_taken VARCHAR(255) NULL,
    outcome VARCHAR(40) NULL,
    reviewed_for_billing TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (item_id),
    UNIQUE KEY uq_wo_item_index (WOID, item_index),
    CONSTRAINT fk_wo_item_work_order
        FOREIGN KEY (WOID) REFERENCES work_order (WOID)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
```

This separates:

- what was requested
- what was done or attempted
- customer-visible outcome
- internal billing review checkbox

This is cleaner than maintaining parallel fields like `WO_Req1`, `Req1`, `WO_Action1`, `WO_Result1`.

### UI Changes for Outcome

If outcome is added, each W.I. row should have:

```text
Requested
Photos
Action Taken / Result
Outcome
Reviewed for Billing
```

Important distinction:

- `Outcome` is customer-visible.
- `Reviewed for Billing` is internal workflow.

### PDF Changes for Outcome

If outcome is added, report table becomes:

```text
W.I. | Requested | Outcome | Action Taken / Result
```

The PDF options page can add:

```text
Include Outcome
```

Default should be on.

### Billing Rule Changes

Current billing rule:

```text
all filled work items must be checked
all filled work items must have action taken
```

Future billing rule:

```text
all filled work items must be reviewed for billing
all filled work items must have action taken / result
all filled work items should have an outcome
```

This allows a mechanic to bill/close a job where a line was not performed for a valid reason, without telling the customer it was completed.

## Testing Checklist

### Access

- Admin can open `Customer PDF` options from work order detail.
- Mechanic cannot see a PDF button.
- Mechanic direct access to `work_order_pdf.php` redirects away.
- Unauthenticated access redirects to login.

### Options

- Turning off Customer Note hides `Customer_Note`.
- Turning off Work Order Note hides `WO_Note`.
- Turning off Action Taken removes the `Action Taken / Result` column.
- Turning off Customer Photos removes the entire photo section.
- Turning off General Photos keeps only W.I. selected photos.
- Turning off W.I. Photos keeps only general selected photos.
- Turning off Signature Area removes the acknowledgement section.

### Photos

- Photos with `show_on_customer_pdf = 1` appear.
- Photos with `show_on_customer_pdf = 0` do not appear.
- General photos obey the General Photos option.
- W.I. photos obey the W.I. Photos option.
- JPG renders.
- PNG renders.
- GIF rendering depends on server support.
- WEBP is skipped or handled by a future conversion rule.

### Content

- Admin Note never appears.
- Mechanic Note never appears.
- Empty work items do not print.
- Long requested text wraps inside the table.
- Long action/result text wraps inside the table.
- Multi-page reports include header/footer on each page.

### Scenario Test: Not Performed Work Item

Given:

```text
Requested:
Install unavailable part

Action Taken / Result:
It's not done; we could not find parts.
```

Expected report:

```text
W.I. 1 | Install unavailable part | It's not done; we could not find parts.
```

The report must not show:

```text
Done: Yes
```

## Known Limitations

- Report is English-only and uses FPDF core fonts.
- Report does not currently support Arabic/RTL layout.
- Report options are not persisted.
- Report is customer-facing only; there is no internal PDF variant yet.
- Report is not an invoice.
- Report is not an estimate.
- Report does not include labor, parts, prices, tax, or payment data.
- Report does not include digital signature capture.
- WEBP photos are not ideal for FPDF output.
- Admin must deliberately mark photos as `Show on customer PDF` before they appear.

## Suggested Next Improvements

1. Add an Internal PDF variant for admin/shop use.
2. Add explicit W.I. outcome fields instead of using checkbox state.
3. Add WEBP conversion to JPG/PNG during upload.
4. Add generated PDF audit logging.
5. Add "Generated by" username to the PDF metadata or footer.
6. Add optional inspection summary when inspection data is available.
7. Add persistent default PDF preferences per admin user.

