<?php
/**
 * Admin - Employee Control
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$employeeModel = new Employee();
$userModel = new User();

function aec_status_label($status) {
    return (string)$status === EMPLOYEE_ACTIVE ? 'Active' : 'Inactive';
}

function aec_role_label($roleName) {
    return ucwords(str_replace(['_', '-'], ' ', (string)$roleName));
}

function aec_employee_display(array $employee) {
    $display = trim((string)($employee['Display'] ?? ''));
    if ($display !== '') {
        return $display;
    }

    $name = trim((string)($employee['FirstName'] ?? '') . ' ' . (string)($employee['LastName'] ?? ''));
    return $name !== '' ? $name : ('Employee #' . (int)($employee['EmployeeID'] ?? 0));
}

function aec_normalize_status($status) {
    return (string)$status === EMPLOYEE_INACTIVE ? EMPLOYEE_INACTIVE : EMPLOYEE_ACTIVE;
}

function aec_employee_form_data() {
    return [
        'FirstName' => trim((string)post('FirstName', '')),
        'LastName' => trim((string)post('LastName', '')),
        'Display' => trim((string)post('Display', '')),
        'Phone' => trim((string)post('Phone', '')),
        'Cell' => trim((string)post('Cell', '')),
        'Email' => trim((string)post('Email', '')),
        'Address' => trim((string)post('Address', '')),
        'City' => trim((string)post('City', '')),
        'Province' => strtoupper(trim((string)post('Province', ''))),
        'PostalCode' => trim((string)post('PostalCode', '')),
        'Position' => trim((string)post('Position', '')),
        'Status' => aec_normalize_status(post('Status', EMPLOYEE_ACTIVE))
    ];
}

function aec_validate_employee(array $data) {
    $hasName = trim((string)$data['Display']) !== ''
        || trim((string)$data['FirstName']) !== ''
        || trim((string)$data['LastName']) !== '';

    if (!$hasName) {
        return 'Enter at least a first name, last name, or display name.';
    }

    if (trim((string)$data['Position']) === '') {
        return 'Position is required.';
    }

    if (trim((string)$data['Email']) !== '' && !filter_var($data['Email'], FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }

    if (!in_array((string)$data['Status'], [EMPLOYEE_ACTIVE, EMPLOYEE_INACTIVE], true)) {
        return 'Select a valid employee status.';
    }

    return '';
}

$message = '';
$messageType = 'success';

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $message = 'Security token expired. Refresh and try again.';
        $messageType = 'error';
    } else {
        $action = (string)post('action', '');

        if ($action === 'create_employee') {
            $data = aec_employee_form_data();
            $validation = aec_validate_employee($data);

            if ($validation !== '') {
                $message = $validation;
                $messageType = 'error';
            } else {
                $employeeId = $employeeModel->create($data);
                $message = $employeeId ? 'Employee created successfully.' : 'Unable to create employee.';
                $messageType = $employeeId ? 'success' : 'error';
            }
        } elseif ($action === 'update_employee') {
            $employeeId = (int)post('employee_id', 0);
            $employee = $employeeModel->getById($employeeId);
            $data = aec_employee_form_data();
            $validation = aec_validate_employee($data);

            if (!$employee) {
                $message = 'Employee not found.';
                $messageType = 'error';
            } elseif ($validation !== '') {
                $message = $validation;
                $messageType = 'error';
            } else {
                $ok = $employeeModel->update($employeeId, $data);
                $message = $ok ? 'Employee updated successfully.' : 'Unable to update employee.';
                $messageType = $ok ? 'success' : 'error';
            }
        } elseif ($action === 'set_status') {
            $employeeId = (int)post('employee_id', 0);
            $status = aec_normalize_status(post('Status', EMPLOYEE_ACTIVE));
            $employee = $employeeModel->getById($employeeId);

            if (!$employee) {
                $message = 'Employee not found.';
                $messageType = 'error';
            } else {
                $ok = $status === EMPLOYEE_ACTIVE
                    ? $employeeModel->activate($employeeId)
                    : $employeeModel->delete($employeeId);
                $message = $ok ? ('Employee ' . strtolower(aec_status_label($status)) . '.') : 'Unable to update employee status.';
                $messageType = $ok ? 'success' : 'error';
            }
        }
    }
}

$employees = $employeeModel->getAll() ?: [];
$users = $userModel->getAll() ?: [];
$usersByEmployeeId = [];
foreach ($users as $user) {
    $employeeId = (int)($user['employee_id'] ?? 0);
    if ($employeeId > 0) {
        if (!isset($usersByEmployeeId[$employeeId])) {
            $usersByEmployeeId[$employeeId] = [];
        }
        $usersByEmployeeId[$employeeId][] = $user;
    }
}

$positions = [];
foreach ($employees as $employee) {
    $position = trim((string)($employee['Position'] ?? ''));
    if ($position !== '') {
        $positions[$position] = $position;
    }
}
natcasesort($positions);

$filterSearch = trim((string)get('q', ''));
$filterStatus = (string)get('status', '');
$filterPosition = trim((string)get('position', ''));

$filteredEmployees = array_values(array_filter($employees, function ($employee) use ($filterSearch, $filterStatus, $filterPosition) {
    if ($filterStatus !== '' && (string)($employee['Status'] ?? '') !== $filterStatus) {
        return false;
    }

    if ($filterPosition !== '' && strcasecmp((string)($employee['Position'] ?? ''), $filterPosition) !== 0) {
        return false;
    }

    if ($filterSearch !== '') {
        $haystack = implode(' ', [
            $employee['EmployeeID'] ?? '',
            $employee['FirstName'] ?? '',
            $employee['LastName'] ?? '',
            $employee['Display'] ?? '',
            $employee['Phone'] ?? '',
            $employee['Cell'] ?? '',
            $employee['Email'] ?? '',
            $employee['Position'] ?? ''
        ]);

        if (stripos($haystack, $filterSearch) === false) {
            return false;
        }
    }

    return true;
}));

$activeEmployees = array_values(array_filter($employees, function ($employee) {
    return (string)($employee['Status'] ?? '') === EMPLOYEE_ACTIVE;
}));
$inactiveEmployees = array_values(array_filter($employees, function ($employee) {
    return (string)($employee['Status'] ?? '') !== EMPLOYEE_ACTIVE;
}));
$mechanicEmployees = array_values(array_filter($activeEmployees, function ($employee) {
    return stripos((string)($employee['Position'] ?? ''), 'mechanic') !== false;
}));
$linkedEmployees = array_values(array_filter($employees, function ($employee) use ($usersByEmployeeId) {
    return isset($usersByEmployeeId[(int)$employee['EmployeeID']]);
}));

$pageTitle = 'Employee Control';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .employee-shell { display: grid; gap: 16px; padding-bottom: 24px; }
        .employee-panel { background: #fff; border: 1px solid #d9e0e8; border-radius: 10px; padding: 18px; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .employee-panel--create { background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); }
        .employee-panel-header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
        .employee-panel-title { margin: 0; color: #173a6a; font-size: 20px; }
        .employee-panel-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .employee-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(150px, 1fr)); gap: 10px; }
        .employee-summary-card { padding: 16px; border: 1px solid #d9e0e8; border-radius: 10px; background: linear-gradient(180deg, #fff, #f8fafc); }
        .employee-summary-card strong { display: block; color: #173a6a; font-size: 28px; line-height: 1; margin-bottom: 6px; }
        .employee-summary-card span { color: #344054; font-weight: 700; }
        .employee-panel input[type="text"],
        .employee-panel input[type="email"],
        .employee-panel select,
        .employee-card input[type="text"],
        .employee-card input[type="email"],
        .employee-card select { width: 100%; min-height: 40px; box-sizing: border-box; padding: 9px 11px; border: 1px solid #b7c5d8; border-radius: 7px; background: #fff; font-size: 14px; }
        .employee-panel label,
        .employee-card label { display: block; margin-bottom: 5px; color: #344054; font-weight: 700; font-size: 12px; }
        .employee-panel input:focus,
        .employee-panel select:focus,
        .employee-card input:focus,
        .employee-card select:focus { outline: none; border-color: #4472c4; box-shadow: 0 0 0 3px rgba(68, 114, 196, 0.16); }
        .employee-panel .btn,
        .employee-card .btn { box-sizing: border-box; margin-right: 0; white-space: nowrap; min-height: 38px; border-radius: 6px; }
        .employee-form-grid { display: grid; grid-template-columns: repeat(4, minmax(160px, 1fr)) 140px; gap: 12px; align-items: end; }
        .employee-form-grid .span-2 { grid-column: span 2; }
        .employee-form-grid .span-3 { grid-column: span 3; }
        .employee-actions { display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
        .employee-filter-grid { display: grid; grid-template-columns: minmax(220px, 1.4fr) minmax(160px, 0.8fr) minmax(180px, 0.9fr) auto auto; gap: 10px; align-items: end; }
        .employee-card-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
        .employee-card { min-width: 0; border: 1px solid #d9e0e8; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); overflow: hidden; }
        .employee-card__head {
            display: grid;
            grid-template-columns: minmax(230px, 1fr) minmax(200px, 0.9fr) minmax(250px, 1fr) auto auto;
            gap: 14px;
            align-items: center;
            padding: 12px 16px;
            background: linear-gradient(180deg, #fbfdff 0%, #f3f7fc 100%);
            border-radius: 10px;
            cursor: pointer;
            list-style: none;
        }
        .employee-card[open] .employee-card__head { border-bottom: 1px solid #e4eaf1; border-radius: 10px 10px 0 0; }
        .employee-card__head::-webkit-details-marker { display: none; }
        .employee-card__head::after {
            content: 'Edit';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 32px;
            padding: 0 12px;
            border: 1px solid #b7c5d8;
            border-radius: 999px;
            background: #fff;
            color: #1f4a86;
            font-weight: 700;
            font-size: 12px;
            white-space: nowrap;
        }
        .employee-card[open] .employee-card__head::after { content: 'Close'; }
        .employee-card__name { margin: 0; color: #1f2d3d; font-size: 18px; }
        .employee-card__meta { margin-top: 3px; color: #667085; font-size: 12px; }
        .employee-card__badges { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
        .employee-row-field { min-width: 0; color: #344054; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .employee-row-field strong { display: block; margin-bottom: 3px; color: #5f6f86; font-size: 11px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .employee-row-login { display: flex; flex-wrap: wrap; gap: 5px; align-items: center; }
        .employee-card__body { display: grid; gap: 14px; padding: 16px; }
        .employee-card__body,
        .employee-card form,
        .employee-edit-grid > *,
        .employee-contact-grid > *,
        .employee-address-grid > * { min-width: 0; }
        .employee-card form { display: grid; gap: 12px; }
        .employee-card-section { padding: 12px; border: 1px solid #eef2f6; border-radius: 8px; background: #fbfdff; }
        .employee-section-title { margin: 0 0 10px; color: #173a6a; font-size: 12px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .employee-edit-grid { display: grid; grid-template-columns: repeat(3, minmax(150px, 1fr)); gap: 12px; align-items: end; }
        .employee-contact-grid { display: grid; grid-template-columns: minmax(145px, 0.8fr) minmax(145px, 0.8fr) minmax(220px, 1.2fr); gap: 12px; align-items: end; }
        .employee-address-grid { display: grid; grid-template-columns: minmax(220px, 1.4fr) minmax(140px, 0.8fr) minmax(90px, 0.45fr) minmax(120px, 0.65fr); gap: 12px; align-items: end; }
        .employee-save-row { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding-top: 12px; border-top: 1px solid #eef2f6; }
        .employee-save-row--plain { padding-top: 0; border-top: 0; }
        .employee-status-row { display: grid; grid-template-columns: minmax(170px, 1fr) minmax(130px, 0.55fr); gap: 12px; align-items: end; min-width: 330px; }
        .status-pill { display: inline-block; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 700; }
        .status-pill--active { color: #087443; background: #dcf4e8; }
        .status-pill--inactive { color: #5d6675; background: #eef2f6; }
        .role-pill { display: inline-block; border-radius: 999px; padding: 4px 9px; color: #173a6a; background: #e9f0fb; font-size: 12px; font-weight: 700; }
        .login-chip { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 4px 9px; color: #344054; background: #f2f4f7; font-size: 12px; font-weight: 700; }
        .employee-empty { margin: 0; color: #667085; }
        .help-text { color: #667085; font-size: 12px; margin-top: 8px; }
        .inline-form { display: inline; }
        @media (max-width: 980px) {
            .employee-summary-grid,
            .employee-form-grid,
            .employee-filter-grid,
            .employee-edit-grid,
            .employee-contact-grid,
            .employee-address-grid { grid-template-columns: 1fr; }
            .employee-form-grid .span-2,
            .employee-form-grid .span-3 { grid-column: auto; }
            .employee-panel-header,
            .employee-save-row { flex-direction: column; align-items: flex-start; }
            .employee-status-row { grid-template-columns: 1fr; min-width: 0; width: 100%; }
            .employee-card__head { grid-template-columns: 1fr; align-items: flex-start; }
            .employee-card__badges { justify-content: flex-start; }
            .employee-row-field { white-space: normal; }
            .employee-actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <a href="settings.php">Settings</a> |
            <a href="users.php">Users</a> |
            <a href="work_orders.php">Work Orders</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="employee-shell">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <section class="employee-panel employee-panel--create">
                <div class="employee-panel-header">
                    <div>
                        <h2 class="employee-panel-title">Employee Overview</h2>
                        <p class="employee-panel-subtitle">Employees are real shop staff. Users are login accounts linked to employees when system access is needed.</p>
                    </div>
                    <a class="btn" href="users.php">Open Users Control</a>
                </div>
                <div class="employee-summary-grid">
                    <div class="employee-summary-card"><strong><?php echo count($employees); ?></strong><span>Total Employees</span></div>
                    <div class="employee-summary-card"><strong><?php echo count($activeEmployees); ?></strong><span>Active</span></div>
                    <div class="employee-summary-card"><strong><?php echo count($inactiveEmployees); ?></strong><span>Inactive</span></div>
                    <div class="employee-summary-card"><strong><?php echo count($mechanicEmployees); ?></strong><span>Active Mechanics</span></div>
                    <div class="employee-summary-card"><strong><?php echo count($linkedEmployees); ?></strong><span>Linked Logins</span></div>
                </div>
            </section>

            <section class="employee-panel">
                <div class="employee-panel-header">
                    <div>
                        <h2 class="employee-panel-title">Create Employee</h2>
                        <p class="employee-panel-subtitle">Create the staff record first, then create or link a user login only if this employee needs system access.</p>
                    </div>
                </div>
                <form method="POST">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="create_employee">
                    <div class="employee-form-grid">
                        <div>
                            <label>First Name</label>
                            <input type="text" name="FirstName" maxlength="50">
                        </div>
                        <div>
                            <label>Last Name</label>
                            <input type="text" name="LastName" maxlength="50">
                        </div>
                        <div>
                            <label>Display Name</label>
                            <input type="text" name="Display" maxlength="100" placeholder="Optional">
                        </div>
                        <div>
                            <label>Position</label>
                            <input type="text" name="Position" list="positionOptions" maxlength="80" required placeholder="Mechanic, Frontdesk...">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="Status">
                                <option value="<?php echo EMPLOYEE_ACTIVE; ?>">Active</option>
                                <option value="<?php echo EMPLOYEE_INACTIVE; ?>">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label>Phone</label>
                            <input type="text" name="Phone" maxlength="30">
                        </div>
                        <div>
                            <label>Cell</label>
                            <input type="text" name="Cell" maxlength="30">
                        </div>
                        <div class="span-2">
                            <label>Email</label>
                            <input type="email" name="Email" maxlength="120">
                        </div>
                        <div class="span-2">
                            <label>Address</label>
                            <input type="text" name="Address" maxlength="160">
                        </div>
                        <div>
                            <label>City</label>
                            <input type="text" name="City" maxlength="80">
                        </div>
                        <div>
                            <label>Province</label>
                            <input type="text" name="Province" maxlength="20">
                        </div>
                        <div>
                            <label>Postal Code</label>
                            <input type="text" name="PostalCode" maxlength="20">
                        </div>
                        <div class="employee-actions">
                            <button type="submit" class="btn btn-success">Create Employee</button>
                        </div>
                    </div>
                    <div class="help-text">Use Position names consistently. Mechanic employees appear in mechanic dropdowns when their position contains "mechanic".</div>
                </form>
            </section>

            <section class="employee-panel">
                <div class="employee-panel-header">
                    <div>
                        <h2 class="employee-panel-title">Employee Directory</h2>
                        <p class="employee-panel-subtitle">Search, edit, activate, deactivate, and review login coverage.</p>
                    </div>
                </div>
                <form method="GET" class="employee-filter-grid">
                    <div>
                        <label>Search</label>
                        <input type="text" name="q" value="<?php echo e($filterSearch); ?>" placeholder="Name, phone, email, position, ID">
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="<?php echo EMPLOYEE_ACTIVE; ?>" <?php echo $filterStatus === EMPLOYEE_ACTIVE ? 'selected' : ''; ?>>Active</option>
                            <option value="<?php echo EMPLOYEE_INACTIVE; ?>" <?php echo $filterStatus === EMPLOYEE_INACTIVE ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label>Position</label>
                        <select name="position">
                            <option value="">All Positions</option>
                            <?php foreach ($positions as $position): ?>
                                <option value="<?php echo e($position); ?>" <?php echo strcasecmp($filterPosition, $position) === 0 ? 'selected' : ''; ?>><?php echo e($position); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn">Filter</button>
                    <a class="btn" href="employees.php">Clear</a>
                </form>

                <datalist id="positionOptions">
                    <option value="Mechanic">
                    <option value="Frontdesk">
                    <option value="Service Advisor">
                    <option value="Manager">
                    <option value="Admin">
                    <option value="Parts">
                    <option value="Detailer">
                </datalist>
            </section>

            <section class="employee-card-grid">
                <?php if (empty($filteredEmployees)): ?>
                    <div class="employee-panel">
                        <p class="employee-empty">No employees match the current filters.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($filteredEmployees as $employee): ?>
                    <?php
                        $employeeId = (int)$employee['EmployeeID'];
                        $linkedUsers = $usersByEmployeeId[$employeeId] ?? [];
                        $isActive = (string)($employee['Status'] ?? '') === EMPLOYEE_ACTIVE;
                    ?>
                    <details class="employee-card">
                        <summary class="employee-card__head">
                            <div>
                                <h3 class="employee-card__name"><?php echo e(aec_employee_display($employee)); ?></h3>
                                <div class="employee-card__meta">
                                    Employee ID <?php echo $employeeId; ?>
                                    <?php if (!empty($employee['Position'])): ?>
                                        | <?php echo e($employee['Position']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="employee-row-field">
                                <strong>Contact</strong>
                                Phone: <?php echo e($employee['Phone'] ?? ''); ?> | Cell: <?php echo e($employee['Cell'] ?? ''); ?>
                            </div>
                            <div class="employee-row-field">
                                <strong>Login</strong>
                                <span class="employee-row-login">
                                    <?php if (!empty($linkedUsers)): ?>
                                        <?php foreach ($linkedUsers as $linkedUser): ?>
                                            <span class="login-chip"><?php echo e($linkedUser['username']); ?> / <?php echo e(aec_role_label($linkedUser['role_name'])); ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        No linked login
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="employee-card__badges">
                                <span class="status-pill <?php echo $isActive ? 'status-pill--active' : 'status-pill--inactive'; ?>"><?php echo e(aec_status_label($employee['Status'] ?? '')); ?></span>
                                <?php if (!empty($linkedUsers)): ?>
                                    <span class="role-pill">User Linked</span>
                                <?php endif; ?>
                            </div>
                        </summary>
                        <div class="employee-card__body">
                            <form method="POST">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="update_employee">
                                <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">

                                <div class="employee-card-section">
                                    <div class="employee-section-title">Identity</div>
                                    <div class="employee-edit-grid">
                                        <div>
                                            <label>First Name</label>
                                            <input type="text" name="FirstName" value="<?php echo e($employee['FirstName'] ?? ''); ?>" maxlength="50">
                                        </div>
                                        <div>
                                            <label>Last Name</label>
                                            <input type="text" name="LastName" value="<?php echo e($employee['LastName'] ?? ''); ?>" maxlength="50">
                                        </div>
                                        <div>
                                            <label>Display Name</label>
                                            <input type="text" name="Display" value="<?php echo e($employee['Display'] ?? ''); ?>" maxlength="100">
                                        </div>
                                    </div>
                                </div>

                                <div class="employee-card-section">
                                    <div class="employee-section-title">Contact</div>
                                    <div class="employee-contact-grid">
                                        <div>
                                            <label>Phone</label>
                                            <input type="text" name="Phone" value="<?php echo e($employee['Phone'] ?? ''); ?>" maxlength="30">
                                        </div>
                                        <div>
                                            <label>Cell</label>
                                            <input type="text" name="Cell" value="<?php echo e($employee['Cell'] ?? ''); ?>" maxlength="30">
                                        </div>
                                        <div>
                                            <label>Email</label>
                                            <input type="email" name="Email" value="<?php echo e($employee['Email'] ?? ''); ?>" maxlength="120">
                                        </div>
                                    </div>
                                </div>

                                <div class="employee-card-section">
                                    <div class="employee-section-title">Address</div>
                                    <div class="employee-address-grid">
                                        <div>
                                            <label>Address</label>
                                            <input type="text" name="Address" value="<?php echo e($employee['Address'] ?? ''); ?>" maxlength="160">
                                        </div>
                                        <div>
                                            <label>City</label>
                                            <input type="text" name="City" value="<?php echo e($employee['City'] ?? ''); ?>" maxlength="80">
                                        </div>
                                        <div>
                                            <label>Province</label>
                                            <input type="text" name="Province" value="<?php echo e($employee['Province'] ?? ''); ?>" maxlength="20">
                                        </div>
                                        <div>
                                            <label>Postal Code</label>
                                            <input type="text" name="PostalCode" value="<?php echo e($employee['PostalCode'] ?? ''); ?>" maxlength="20">
                                        </div>
                                    </div>
                                </div>

                                <div class="employee-card-section">
                                    <div class="employee-section-title">Work Status</div>
                                    <div class="employee-save-row employee-save-row--plain">
                                    <div class="employee-status-row">
                                        <div>
                                            <label>Position</label>
                                            <input type="text" name="Position" list="positionOptions" value="<?php echo e($employee['Position'] ?? ''); ?>" maxlength="80" required>
                                        </div>
                                        <div>
                                            <label>Status</label>
                                            <select name="Status">
                                                <option value="<?php echo EMPLOYEE_ACTIVE; ?>" <?php echo $isActive ? 'selected' : ''; ?>>Active</option>
                                                <option value="<?php echo EMPLOYEE_INACTIVE; ?>" <?php echo !$isActive ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn">Save Employee</button>
                                    </div>
                                </div>
                            </form>

                            <div class="employee-save-row">
                                <div class="help-text">
                                    <?php if (!empty($linkedUsers)): ?>
                                        Manage this employee's login from Users Control.
                                    <?php else: ?>
                                        Create a linked login from Users Control if this employee needs access.
                                    <?php endif; ?>
                                </div>
                                <div class="employee-actions">
                                    <a class="btn" href="users.php">Users Control</a>
                                    <form method="POST" class="inline-form">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="set_status">
                                        <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>">
                                        <input type="hidden" name="Status" value="<?php echo $isActive ? EMPLOYEE_INACTIVE : EMPLOYEE_ACTIVE; ?>">
                                        <button type="submit" class="btn <?php echo $isActive ? 'btn-danger' : 'btn-success'; ?>">
                                            <?php echo $isActive ? 'Deactivate' : 'Reactivate'; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
</body>
</html>
