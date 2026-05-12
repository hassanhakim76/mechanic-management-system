# Users and Employees Control

## Purpose

This document explains the new user and employee management workflow.

The system now separates two different concepts:

- **Employee**: a real person who works in the shop.
- **User**: a login account that can access the system.

This separation is important because not every employee needs a login, and not every login should be treated as a mechanic or staff record without a clear employee link.

## Main Idea

Create the employee first, then create or link the user login if that employee needs access.

Examples:

- A mechanic should be an employee and usually should also have a mechanic user login.
- A frontdesk staff member should be an employee and should have a frontdesk user login.
- A helper, driver, or inactive staff member may exist as an employee without a user login.
- Old staff should be deactivated, not deleted.

## Files

### Employee Control

```text
modules/admin/employees.php
```

Admin page for creating and managing employee records.

### Users Control

```text
modules/admin/users.php
```

Admin page for creating and managing login accounts.

### Settings Page

```text
modules/admin/settings.php
```

Contains links to:

- Inspection Settings
- Users Control
- Employee Control

### Models

```text
includes/models/Employee.php
includes/models/User.php
```

`Employee.php` handles employee records.

`User.php` handles login accounts and now joins to employee records through `users.employee_id`.

### Database Migration

```text
database/2026_05_03_users_employee_link.sql
```

Adds the `employee_id` link to the `users` table.

## Database Change

The users table now has:

```sql
ALTER TABLE users
ADD COLUMN employee_id INT NULL AFTER role_id;
```

Index:

```sql
ALTER TABLE users
ADD KEY idx_users_employee_id (employee_id);
```

Foreign key:

```sql
ALTER TABLE users
ADD CONSTRAINT fk_users_employee
FOREIGN KEY (employee_id)
REFERENCES employees (EmployeeID)
ON DELETE SET NULL
ON UPDATE CASCADE;
```

This means:

- A user may be linked to one employee.
- The link is optional.
- If an employee is deleted at the database level, the user remains but `employee_id` becomes `NULL`.
- In normal app workflow, employees should be deactivated instead of deleted.

## Employee Table

The employee table already existed before this work.

The app uses fields such as:

```text
EmployeeID
FirstName
LastName
Display
Phone
Cell
Email
Address
City
Province
PostalCode
Position
Status
```

Employee statuses:

```php
EMPLOYEE_ACTIVE = 'A'
EMPLOYEE_INACTIVE = 'I'
```

## User Roles

Defined in:

```text
config/config.php
```

Current roles:

```php
ROLE_ADMIN = 1
ROLE_MECHANIC = 2
ROLE_FRONTDESK = 3
```

## Employee Control Features

The Employee Control page can:

- Create employees.
- Edit employee name.
- Edit display name.
- Edit phone, cell, and email.
- Edit address, city, province, and postal code.
- Edit position.
- Activate employees.
- Deactivate employees.
- Filter employees by status.
- Filter employees by position.
- Search by name, phone, email, position, or employee ID.
- Show whether an employee has a linked login.
- Open Users Control to create or manage the login.

Employee Control uses an accordion list layout:

- Each employee appears as one compact row.
- The row shows identity, contact, linked login, and status.
- Clicking the row opens the full edit form.
- The expanded form keeps the same identity, contact, address, and work status fields.

## Users Control Features

The Users Control page can:

- Create login users.
- Assign role:
  - Admin
  - Mechanic
  - Frontdesk
- Link a user to an employee.
- Edit username.
- Change role.
- Change linked employee.
- Activate/deactivate user login.
- Reset password.
- Create mechanic login from active mechanic employee.
- Show mechanic employee login coverage.
- Show user totals by role.

New user creation only lists active employees in the Employee Link dropdown.

Inactive employees are not valid for new login creation. Existing user records can still show an inactive linked employee for history or review, but the label should clearly include:

```text
(inactive)
```

## Correct Workflow

### Create a New Mechanic

1. Go to:

```text
Settings -> Employee Control
```

2. Create employee:

```text
First Name
Last Name
Display Name
Position = Mechanic
Status = Active
```

3. Go to:

```text
Settings -> Users Control
```

4. Create login:

```text
Username
Role = Mechanic
Employee Link = the mechanic employee
Password
Active = checked
```

Or use the Mechanic Employee Login Coverage section to create the login quickly.

### Create a New Frontdesk User

1. Create employee in Employee Control:

```text
Position = Frontdesk
Status = Active
```

2. Create user in Users Control:

```text
Role = Frontdesk
Employee Link = the frontdesk employee
```

### Create Admin User

1. Create employee if this admin is also a staff member.
2. Create user with:

```text
Role = Admin
Employee Link = optional
```

Important:

- The system protects against removing the last active admin.
- The current admin cannot deactivate their own account or remove their own admin role.

## Why Employee and User Are Separate

Employee records are used by shop operations:

- Mechanic dropdowns
- Staff display
- Employee status
- Future appointments
- Future frontdesk workflow
- Future time/labor tracking

User records are used by system security:

- Login
- Password
- Role
- Access control
- Active/inactive account status

This is cleaner than using the login username as the mechanic identity.

## Mechanic Logic

The app already uses employee records for mechanic selection.

`Employee::getMechanics()` returns active employees whose position contains:

```text
mechanic
```

This means mechanic employees should have a position like:

```text
Mechanic
Lead Mechanic
Senior Mechanic
```

Avoid unrelated position names if the employee should appear in mechanic dropdowns.

## Active First Sorting

Employee lists now show active employees before inactive employees.

This is handled in:

```text
includes/models/Employee.php
```

The previous sort used `Status DESC`, which caused inactive employees (`I`) to appear before active employees (`A`). The sort is now explicit:

```sql
ORDER BY
    CASE WHEN Status = 'A' THEN 0 ELSE 1 END,
    LastName,
    FirstName,
    Display
```

## Security Rules

Only Admin can access:

```text
modules/admin/users.php
modules/admin/employees.php
modules/admin/settings.php
```

Users Control security:

- Passwords are stored as bcrypt hashes.
- Password minimum length is enforced.
- Username uniqueness is enforced.
- CSRF tokens are checked.
- At least one active admin must remain.
- Current admin cannot deactivate their own account.
- Current admin cannot remove their own admin role.

Employee Control security:

- CSRF tokens are checked.
- Employee is deactivated instead of deleted.
- Email format is validated when provided.
- Position is required.
- At least one name/display field is required.

## Upload / Deployment Checklist

When uploading to live:

1. Upload changed code:

```text
modules/admin/users.php
modules/admin/employees.php
modules/admin/settings.php
includes/models/User.php
includes/models/Employee.php
```

2. Run this migration on the live database:

```text
database/2026_05_03_users_employee_link.sql
```

3. Verify the migration output:

```sql
SELECT user_id, username, role_id, employee_id
FROM users
ORDER BY username;
```

4. Open:

```text
Settings -> Employee Control
Settings -> Users Control
```

5. Link existing users to the correct employees.

## Important Notes

### Do Not Delete Employees

Use deactivate/reactivate.

Deleting real staff records can damage historical links and future reports.

### Link Users Carefully

The employee link tells the system which real person owns the login.

This will matter more when adding:

- Frontdesk dashboard
- Appointment ownership
- Mechanic labor tracking
- Approval records
- Audit logs
- Time tracking

### User Role Does Not Replace Position

A user can have role `Mechanic`, but the employee position should still be `Mechanic`.

Role controls access.

Position describes the person’s job in the shop.

## Current Limitations

- Employee Control does not yet create user login directly inside the employee card.
- Users Control is still the correct place to create login accounts.
- There is no detailed permission matrix beyond Admin / Mechanic / Frontdesk roles yet.
- Frontdesk role exists, but Frontdesk dashboard is planned separately.
- No audit log yet for user/employee changes.

## Future Improvements

Recommended later:

- Add "Create Login" button directly from an employee card.
- Add "Open Linked User" button when employee already has a login.
- Add employee photo/profile image.
- Add employee type/department dropdown instead of free-text position.
- Add audit log for user and employee changes.
- Add last modified by / last modified at.
- Add frontdesk employee dashboard.
- Add mechanic labor time tracking by linked employee.
- Add signature/authorization tracking by employee/user.

## Final Design Rule

Use this mental model:

```text
Employee = who the person is in the shop
User     = how the person logs into the system
Role     = what the login is allowed to do
Position = what job the person performs
```
