# AutoShop Project Structure

```text
autoshop/
|-- .idea/
|   |-- .gitignore
|   |-- autoshop.iml
|   |-- modules.xml
|   |-- php.xml
|   `-- workspace.xml
|-- config/
|   |-- config.php
|   |-- dump_letters_schema.php
|   |-- dump_schema.php
|   `-- setup_general_customer.php
|-- database/
|   |-- 2026_02_19_draft_governance.sql
|   |-- 2026_02_20_approve_resolution_modes.sql
|   |-- 2026_02_20_simplify_draft_status.sql
|   `-- init.sql
|-- docs/
|   `-- SCREENSHOT_ANALYSIS.md
|-- includes/
|   |-- models/
|   |   |-- Customer.php
|   |   |-- CustomerLetter.php
|   |   |-- Employee.php
|   |   |-- LetterTemplate.php
|   |   |-- User.php
|   |   |-- Vehicle.php
|   |   `-- WorkOrder.php
|   |-- bootstrap.php
|   |-- Database.php
|   |-- functions.php
|   `-- Session.php
|-- modules/
|   |-- admin/
|   |   |-- customer_detail.php
|   |   |-- registration.php
|   |   |-- templates.php
|   |   |-- work_orders.php
|   |   |-- work_order_detail.php
|   |   |-- work_order_history.php
|   |   `-- work_order_new.php
|   |-- intake/
|   |   |-- approve.php
|   |   |-- draft_view.php
|   |   `-- review_queue.php
|   |-- mechanic/
|   |   |-- work_orders.php
|   |   `-- work_order_detail.php
|   `-- shared/
|       `-- work_order_photos.php
|-- public/
|   |-- api/
|   |   |-- customer_search.php
|   |   |-- decode_vehicle.php
|   |   |-- submit_intake.php
|   |   `-- vehicle_list.php
|   |-- css/
|   |   `-- style.css
|   |-- js/
|   |   |-- intake.js
|   |   `-- main.js
|   |-- index.php
|   |-- intake.php
|   |-- login.php
|   `-- logout.php
|-- .gitignore
|-- .htaccess
|-- ClientInfo.aspx
|-- default.aspx
|-- delete.png
|-- Header.jpg
|-- index.php
|-- jquery-1.4.3.min.js
|-- plan.md
|-- plus.jpg
|-- plus.png
|-- tmp_check_subscribe.php
`-- web.config
```
