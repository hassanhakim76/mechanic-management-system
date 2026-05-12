# GPT-5 Suggestions for AutoShop

## Context

This project is a mechanic shop application for managing customers, vehicles, intake drafts, work orders, mechanic workflow, and admin review.

## Good Points

- The app follows a realistic shop workflow: customer/vehicle intake, draft review, work order creation, mechanic queue, billing, and completion.
- Admin/front desk and mechanic screens are separated, which matches how a real shop operates.
- The draft intake system is one of the strongest parts of the project. It allows incomplete walk-in or tow-in information to be captured safely before creating permanent records.
- Work orders already include important shop fields such as plate, VIN, mileage, priority, customer note, admin note, shop note, mechanic assignment, work items, and status.
- Database access generally uses PDO and prepared statements, which is a good security foundation.
- The database layer has useful guardrails through stored procedures and triggers for draft approval and cancellation.
- The codebase is compact and understandable, making it easier to maintain than a large overbuilt system.

## What It Still Needs

- Security cleanup:
  - Move database credentials and API keys out of `config/config.php`.
  - Disable browser error output outside local development.
  - Set `DEBUG_MODE` to false for production.
  - Add missing CSRF checks.
  - Harden session cookies with `httponly`, `secure`, and `SameSite`.
- Fix the Inspection feature:
  - Correct the broken Inspection button path.
  - Replace the missing `WorkOrder::get()` call with the existing `getById()` method.
  - Whitelist inspection fields before saving.
- Complete core mechanic shop features:
  - Appointment scheduling.
  - Checklists.
  - Signature capture.
  - Printable work orders.
  - Estimates or quotes.
  - Invoices and payments.
  - Parts used and inventory.
  - Customer and vehicle service history.
  - Email or SMS reminders.
  - Reports for daily work, billing, completed jobs, and mechanic workload.
- Improve frontend usability for shop-floor use, especially on tablets.
- Add repeatable verification:
  - PHP lint script.
  - Basic workflow checks.
  - Eventually a small automated test suite.
- Improve deployment:
  - Environment-based config.
  - Migration order documentation.
  - Backup process.
  - No debug tools exposed online.

## What To Remove Or Move

- Remove from public web access:
  - `tmp_check_subscribe.php`
  - `config/dump_schema.php`
  - `config/dump_letters_schema.php`
  - `config/setup_general_customer.php`
- Move legacy reference files out of the live web root if they are no longer needed:
  - `default.aspx`
  - `ClientInfo.aspx`
  - `jquery-1.4.3.min.js`
- Remove hardcoded credentials and fallback API keys from `config/config.php`.
- Remove default seed credentials from real deployments:
  - `admin / admin123`
  - `mechanic / mechanic123`
- Hide or remove unfinished buttons until implemented:
  - Appointment
  - Check List
  - Signature
  - Mechanic History placeholder
- Remove `.idea/` from shared or deployed copies of the project.

## Recommended Priority

1. Security cleanup.
2. Fix the Inspection feature.
3. Remove or protect debug/admin utility scripts.
4. Finish incomplete core workflow buttons.
5. Add appointments, invoices, parts, service history, and reports.
6. Add repeatable checks and deployment documentation.

## Bottom Line

The project has a good foundation for a mechanic shop system. The draft intake workflow is especially strong. Before adding many new features, the safest path is to harden security, fix broken inspection behavior, and remove development/debug files from public access.
