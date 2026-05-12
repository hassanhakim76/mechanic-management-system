# Project Analysis

Date: 2026-04-23

## Scope

This note captures a static architecture and codebase review of the `autoshop` project so it can be reused later without re-running the full analysis.

This review was based on reading the current PHP, SQL, JS, and markdown files in the workspace. It does not include full runtime testing, PHP linting across every file, or database migration execution.

## High-Level Overview

This is a custom PHP/MySQL shop-management application built as a VB/Access clone rather than a framework-based app.

The structure is centered around:

- manual bootstrap and dependency loading in `includes/bootstrap.php`
- role/session handling in `includes/Session.php`
- PDO database access in `includes/Database.php`
- hand-written model classes in `includes/models/`
- page-based modules under `modules/admin`, `modules/mechanic`, and `modules/intake`
- public entry points and JSON endpoints under `public/`

The intent of the system is documented in `plan.md`, which describes the app as a 1:1 PHP/MySQL clone of an older VB/Access workflow.

## Main Functional Areas

### 1. Core Work Order System

The main work-order system is split by role:

- `modules/admin/work_orders.php`
- `modules/admin/work_order_detail.php`
- `modules/mechanic/work_orders.php`
- `modules/mechanic/work_order_detail.php`

Admin screens provide broader work-order control, while mechanic screens focus on taking ownership of work, updating requests/notes, and submitting jobs to billing.

There is already a dedicated comparison note for these two screens in:

- `work_order_admin_vs_mechanic.md`

### 2. Intake / Draft Workflow

The intake flow is more modern than the legacy work-order screens. It creates draft customer, vehicle, and work-order records before promotion to permanent records.

Key files:

- `public/intake.php`
- `public/js/intake.js`
- `public/api/submit_intake.php`
- `modules/intake/review_queue.php`
- `modules/intake/draft_view.php`
- `modules/intake/approve.php`

This flow includes:

- draft-only intake creation
- readiness validation
- escalation for incomplete drafts
- duplicate customer and vehicle detection
- approval and cancellation flows
- audit logging

### 3. Database Governance Layer

The newer intake flow relies heavily on database procedures and triggers, not just PHP logic.

Important SQL files:

- `database/2026_03_27_refresh_draft_governance_safe.sql`
- `database/2026_03_27_refresh_approve_draft_intake_with_mileage.sql`

These files add:

- approval and cancellation stored procedures
- readiness and escalation fields on `draft_work_orders`
- minimum-field enforcement triggers for permanent records
- draft status logging

This is one of the stronger parts of the project because it protects important workflows at the database layer as well as the PHP layer.

## Project Shape

The codebase is relatively compact and easy to reason about:

- 39 PHP files
- 7 SQL files
- 3 JS files
- no `composer.json`
- no `phpunit.xml`
- no Git repository at the current folder level

This means the project is approachable, but also depends on manual discipline rather than modern package management, automated testing, or version-control visibility from this directory.

## Strengths

### Clear Functional Separation

The project layout is understandable. The split between admin, mechanic, and intake workflows is visible at the folder level and matches the business process fairly well.

### Consistent PDO Usage

Database access goes through PDO with prepared statements and emulated prepares disabled in `includes/Database.php`. That is a good base compared with ad hoc SQL execution.

### Stronger New Intake Workflow

The intake path is more defensive and better governed than the older work-order screens. It has:

- CSRF checks in important intake forms and APIs
- readiness validation
- duplicate detection
- promotion/cancellation procedures
- database-backed guardrails

### Useful Existing Documentation

The repository already contains helpful notes:

- `README.md`
- `plan.md`
- `work_order_admin_vs_mechanic.md`
- `docs/SCREENSHOT_ANALYSIS.md`

That makes ongoing maintenance easier than a typical undocumented legacy PHP project.

## Weaknesses and Risks

### 1. Hardcoded Secrets and Debug Settings

`config/config.php` currently contains:

- hardcoded database credentials
- a fallback vehicle decode API key
- `display_errors = 1`
- `DEBUG_MODE = true`

This is the highest-priority security concern in the current codebase.

### 2. Database Errors Can Leak Sensitive Information

`includes/Database.php` can output database error details and SQL directly to the browser when debug mode is enabled. That is useful in development, but risky if it ever reaches a shared or production environment.

### 3. Login Form Has a CSRF Token but Does Not Verify It

`public/login.php` renders a CSRF field but the POST handler does not validate it. That creates an inconsistency between what the form suggests and what the server actually enforces.

### 4. Mechanic Detail Save Flow Lacks CSRF Validation

The admin work-order detail screen validates CSRF tokens, but the mechanic work-order detail screen processes POST requests without the same check.

Relevant files:

- `modules/admin/work_order_detail.php`
- `modules/mechanic/work_order_detail.php`

### 5. Session Cookie Hardening Is Minimal

`includes/Session.php` starts sessions and sets cookie lifetime, but does not explicitly set stronger cookie options such as:

- `httponly`
- `secure`
- `SameSite`

### 6. Business Rules Are Split Across Multiple Layers

Workflow rules currently live in several places at once:

- page controllers in `modules/...`
- helper functions in `includes/functions.php`
- model logic in `includes/models/WorkOrder.php`
- stored procedures and triggers in `database/*.sql`

That makes the application workable, but harder to safely evolve because a rule may need to be updated in more than one place.

### 7. Config Path/URL Handling Is Slightly Fragile

`config/config.php` mixes URL and path conventions in a way that may be brittle across environments. For example:

- localhost `BASE_PATH` is concatenated without a slash
- production `BASE_URL` already includes a trailing slash

These may not be breaking today, but they are worth normalizing.

### 8. Legacy and Transitional Artifacts Still Exist

The repo still contains:

- `default.aspx`
- `ClientInfo.aspx`
- `jquery-1.4.3.min.js`

Those files are useful as references, but they also signal that the codebase is still in a transition period between cloned legacy behavior and the newer PHP implementation.

### 9. Some UI Actions Are Still Placeholders

There are still unimplemented or placeholder features such as:

- history button in `modules/mechanic/work_orders.php`
- appointment buttons in admin/mechanic dashboards
- future logging note in `includes/functions.php`

These are not critical defects, but they show where the product is still incomplete.

## Architectural Read

The project currently feels like a hybrid of two generations:

### Older Layer

The core work-order area is classic hand-written PHP:

- page controller and view logic mixed in single files
- business rules partially embedded in screen handlers
- light model abstraction

### Newer Layer

The intake/draft system is more structured:

- staged data flow
- validation snapshots
- audit logging
- DB-enforced promotion rules

If the project continues to grow, the main maintainability challenge will be bringing the older work-order flows closer to the discipline of the intake flow without overcomplicating the app.

## Recommended Priorities

### Priority 1: Security Hardening

- move DB credentials and decode API key out of `config/config.php`
- disable browser error output outside local development
- add CSRF verification to `public/login.php`
- add CSRF verification to `modules/mechanic/work_order_detail.php`
- harden session cookie options in `includes/Session.php`

### Priority 2: Config Cleanup

- normalize `BASE_URL` and `BASE_PATH`
- reduce environment-specific assumptions in config

### Priority 3: Rule Consolidation

- centralize work-order transition rules where possible
- reduce duplication between admin/mechanic handlers and `WorkOrder.php`

### Priority 4: Tooling and Operability

- add a lightweight testing or verification approach
- add repeatable lint/check scripts
- ensure the project is tracked from a Git repo root

## Bottom Line

This is a workable custom PHP application with a clear business purpose and a decent amount of structure for a hand-rolled system.

The best part of the codebase is the newer intake/draft workflow, which already shows better validation, auditability, and governance. The main risks are security configuration, inconsistent CSRF protection, and the spread of business rules across controllers, models, and SQL procedures.

If future work focuses first on security hardening and then on consolidating workflow logic, the project should become much safer and easier to maintain.

