# Work Order Photos - 2026-04-27

## Goal

Add the first real photo workflow for work orders, tied directly to each W.I. line.

The design keeps the main work order detail screen compact. Photos are not displayed inline on the work order page. Instead, each filled W.I. row has a small `Photos` button on the second line, before the `Action taken` field.

## Main User Flow

1. Open a work order detail page.
2. Click `Photos` under a filled W.I. row.
3. A popup opens for that exact W.I.
4. User can take a photo from phone/tablet camera or upload an existing image.
5. User selects a photo stage:
   - Before
   - During
   - After
   - Inspection
   - Internal
6. User can add a caption.
7. User can mark the photo as `Show on customer PDF`.

## Database Added

Created migration:

```text
database/2026_04_27_work_order_photos.sql
```

New table:

```text
work_order_photos
```

Important fields:

```text
photo_id
WOID
work_item_index
stage
category
caption
file_path
thumbnail_path
original_name
mime_type
file_size
show_on_customer_pdf
uploaded_by
created_at
```

The table is linked to `work_order.WOID` with cascade delete, so work-order photos are removed if the work order is deleted.

## Files Added

```text
includes/models/WorkOrderPhoto.php
modules/shared/work_order_photos.php
database/2026_04_27_work_order_photos.sql
database/2026_04_27_work_order_photo_categories.sql
```

## Files Updated

```text
includes/bootstrap.php
modules/admin/work_order_detail.php
modules/mechanic/work_order_detail.php
public/css/style.css
```

## Storage Location

Uploaded photos are saved under:

```text
uploads/work_order_photos/PREC-007129/
```

The folder uses the formatted work order number.

Example:

```text
uploads/work_order_photos/PREC-007129/wi1_before_20260427_123000_ab12cd34.jpg
```

## W.I. Button Placement

The `Photos` button was added under the W.I. label and checkbox area, at the beginning of the second line, before `Action taken`.

Layout intent:

```text
W.I. 1     [x]     tires
Photos            Action taken
```

If photos exist, the button shows a count:

```text
Photos 2
```

## Upload Support

The popup supports both:

```html
<input type="file" accept="image/*" capture="environment">
```

and:

```html
<input type="file" accept="image/*">
```

This allows phones, iPhones, tablets, and iPads to take a new photo or upload/select an existing photo.

## Current Validation

Allowed image types:

```text
JPG
PNG
GIF
WEBP
```

The upload logic checks MIME type and file size before saving.

## Permissions

Admin and mechanic users can open the photo popup.

Delete rules:

```text
Admin can delete any photo.
Uploader can delete their own photo.
```

## Local Database

The migration was applied locally to the `precision` database.

Confirmed table:

```text
work_order_photos
```

## Verification

PHP lint passed for:

```text
includes/bootstrap.php
includes/models/WorkOrderPhoto.php
modules/shared/work_order_photos.php
modules/admin/work_order_detail.php
modules/mechanic/work_order_detail.php
```

The local `work_order_photos` table was created successfully.

## Current Limitation

The app stores the original uploaded image and uses it directly in the photo manager. It does not generate resized thumbnails yet because the current PHP environment does not have an image-resize library enabled.

## Next Step

Update the customer-facing work order PDF so it includes only photos where:

```text
show_on_customer_pdf = 1
```

Photos should be grouped under the matching W.I. and organized by stage:

```text
W.I. 1 - starter
Before
During
After
```

## Plan For Other Photos

Other photos are photos that belong to the whole work order, not one specific W.I.

Examples:

```text
Vehicle arrival
Odometer
Dashboard warning lights
VIN label
License plate
Exterior condition
Interior condition
Existing damage
Fluid leaks
Undercarriage
Tow-in condition
Customer concern location
General inspection evidence
```

## General Photo Design

The current `work_order_photos` table already supports this because `work_item_index` can be `NULL`.

Planned meaning:

```text
work_item_index = 1 to 5  => photo belongs to that W.I.
work_item_index = NULL    => general work order photo
```

## General Photo Button Placement

Add one general `Photos` button in the work order footer beside the other tools.

Suggested placement:

```text
Save | Print | Inspection | Photos | Check List | History | Signature | Close
```

This keeps the W.I. photos attached to each W.I. row, while the footer `Photos` button handles whole-vehicle/work-order photos.

## General Photo Popup

The popup should reuse the same photo manager page, but without a W.I. number.

Suggested title:

```text
Photos - PREC-007129 - General
```

General photo categories:

```text
Arrival
Odometer
VIN / Plate
Exterior
Interior
Damage
Leak
Dashboard
Inspection
Internal
```

The existing `stage` field can still be used for customer PDF grouping:

```text
Before
During
After
Inspection
Internal
```

General photos now have a real `category` column in `work_order_photos`.

## Customer PDF Rules For Other Photos

Only include general photos when:

```text
show_on_customer_pdf = 1
```

Suggested PDF grouping:

```text
General Vehicle Photos
Arrival / Condition
Odometer
VIN / Plate
Inspection
```

Internal photos should stay out of the customer-facing PDF unless manually marked for the customer.

## Recommended Implementation Order

1. Done: Update `WorkOrderPhoto` to allow `work_item_index = NULL`.
2. Done: Update `modules/shared/work_order_photos.php` to support `wi=general`.
3. Done: Add a footer `Photos` button to admin and mechanic work-order detail pages.
4. Done: Show a general photo count on that footer button.
5. Done: Add `category` column for general photos.
6. Later: Add customer PDF rendering for selected W.I. photos and selected general photos.

## General Photos Implemented

General photos are now supported through:

```text
modules/shared/work_order_photos.php?woid=7129&wi=general
```

The footer `Photos` button on admin and mechanic work-order detail pages opens this general photo popup.

General photos save with:

```text
work_item_index = NULL
```

General photos can now be categorized as:

```text
Arrival
Odometer
VIN / Plate
Exterior
Interior
Damage
Leak
Dashboard
Undercarriage
Inspection
Customer Concern
Other
```

W.I. photos still save with:

```text
work_item_index = 1 to 5
```
