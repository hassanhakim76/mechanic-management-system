<?php
/**
 * Admin - Users Control
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$userModel = new User();
$employeeModel = new Employee();
$currentUserId = (int)Session::getUserId();
$passwordMinLength = max((int)PASSWORD_MIN_LENGTH, 8);
$expectedRoles = [
    ROLE_ADMIN => 'admin',
    ROLE_MECHANIC => 'mechanic',
    ROLE_FRONTDESK => 'frontdesk'
];

function auc_role_label($roleName) {
    return ucwords(str_replace(['_', '-'], ' ', (string)$roleName));
}

function auc_normalize_key($value) {
    return preg_replace('/[^a-z0-9]/', '', strtolower((string)$value));
}

function auc_suggest_username($employee) {
    $base = trim((string)($employee['Display'] ?? ''));
    if ($base === '') {
        $base = trim((string)($employee['FirstName'] ?? '') . '.' . (string)($employee['LastName'] ?? ''));
    }

    $username = strtolower($base);
    $username = preg_replace('/[^a-z0-9]+/', '.', $username);
    $username = trim($username, '.');

    if (strlen($username) < 3) {
        $username = 'mechanic' . (int)($employee['EmployeeID'] ?? 0);
    }

    return substr($username, 0, 50);
}

function auc_validate_username($username) {
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,50}$/', (string)$username);
}

function auc_validate_password($password, $confirm, $minLength) {
    if ((string)$password === '') {
        return 'Password is required.';
    }

    if (strlen((string)$password) < $minLength) {
        return 'Password must be at least ' . (int)$minLength . ' characters.';
    }

    if ((string)$password !== (string)$confirm) {
        return 'Password confirmation does not match.';
    }

    return '';
}

function auc_employee_label($employee) {
    $name = trim((string)($employee['Display'] ?? ''));
    if ($name === '') {
        $name = trim((string)($employee['FirstName'] ?? '') . ' ' . (string)($employee['LastName'] ?? ''));
    }
    $position = trim((string)($employee['Position'] ?? ''));
    return $position === '' ? $name : ($name . ' - ' . $position);
}

function auc_employee_option_label($employee, $showStatus = false) {
    $label = auc_employee_label($employee);
    if ($showStatus && (string)($employee['Status'] ?? '') !== EMPLOYEE_ACTIVE) {
        $label .= ' (inactive)';
    }
    return $label;
}

$message = '';
$messageType = 'success';

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $message = 'Security token expired. Refresh and try again.';
        $messageType = 'error';
    } else {
        $action = post('action', '');
        $roles = $userModel->getRoles();
        $roleIds = array_map('intval', array_column($roles, 'role_id'));

        if ($action === 'ensure_default_roles') {
            $ok = $userModel->ensureDefaultRoles();
            $message = $ok ? 'Default roles verified.' : 'Unable to verify default roles.';
            $messageType = $ok ? 'success' : 'error';
        } elseif ($action === 'create_user' || $action === 'create_mechanic_login') {
            $username = trim((string)post('username', ''));
            $roleId = $action === 'create_mechanic_login' ? ROLE_MECHANIC : (int)post('role_id', 0);
            $employeeId = $action === 'create_mechanic_login' ? (int)post('employee_id', 0) : (int)post('employee_id', 0);
            $password = (string)post('password', '');
            $confirm = (string)post('password_confirm', '');
            $active = post('is_active', '1') === '1' ? 1 : 0;
            $passwordError = auc_validate_password($password, $confirm, $passwordMinLength);
            $employee = $employeeId > 0 ? $employeeModel->getById($employeeId) : null;

            if (!auc_validate_username($username)) {
                $message = 'Username must be 3-50 characters and use only letters, numbers, dot, dash, or underscore.';
                $messageType = 'error';
            } elseif (!in_array($roleId, $roleIds, true)) {
                $message = 'Select a valid role.';
                $messageType = 'error';
            } elseif ($employeeId > 0 && !$employee) {
                $message = 'Select a valid employee.';
                $messageType = 'error';
            } elseif ($action === 'create_user' && $employeeId > 0 && (string)($employee['Status'] ?? '') !== EMPLOYEE_ACTIVE) {
                $message = 'Only active employees can be linked to a new user.';
                $messageType = 'error';
            } elseif ($action === 'create_mechanic_login' && (!$employee || stripos((string)($employee['Position'] ?? ''), 'mechanic') === false || (string)($employee['Status'] ?? '') !== EMPLOYEE_ACTIVE)) {
                $message = 'Select a valid active mechanic employee.';
                $messageType = 'error';
            } elseif ($passwordError !== '') {
                $message = $passwordError;
                $messageType = 'error';
            } elseif ($userModel->usernameExists($username)) {
                $message = 'Username already exists.';
                $messageType = 'error';
            } else {
                $newUserId = $userModel->create([
                    'username' => $username,
                    'password' => $password,
                    'role_id' => $roleId,
                    'employee_id' => $employeeId > 0 ? $employeeId : null,
                    'is_active' => $active
                ]);
                $message = $newUserId ? 'User created successfully.' : 'Unable to create user.';
                $messageType = $newUserId ? 'success' : 'error';
            }
        } elseif ($action === 'update_user') {
            $userId = (int)post('user_id', 0);
            $target = $userModel->getById($userId);
            $username = trim((string)post('username', ''));
            $roleId = (int)post('role_id', 0);
            $employeeId = (int)post('employee_id', 0);
            $employee = $employeeId > 0 ? $employeeModel->getById($employeeId) : null;
            $active = post('is_active', '0') === '1' ? 1 : 0;

            if (!$target) {
                $message = 'User not found.';
                $messageType = 'error';
            } elseif (!auc_validate_username($username)) {
                $message = 'Username must be 3-50 characters and use only letters, numbers, dot, dash, or underscore.';
                $messageType = 'error';
            } elseif (!in_array($roleId, $roleIds, true)) {
                $message = 'Select a valid role.';
                $messageType = 'error';
            } elseif ($employeeId > 0 && !$employee) {
                $message = 'Select a valid employee.';
                $messageType = 'error';
            } elseif ($userModel->usernameExists($username, $userId)) {
                $message = 'Username already exists.';
                $messageType = 'error';
            } elseif ($userId === $currentUserId && ($roleId !== ROLE_ADMIN || $active !== 1)) {
                $message = 'You cannot remove admin access or deactivate the account you are currently using.';
                $messageType = 'error';
            } elseif ((int)$target['role_id'] === ROLE_ADMIN && ((int)$roleId !== ROLE_ADMIN || $active !== 1) && $userModel->countActiveAdmins($userId) < 1) {
                $message = 'At least one active admin user must remain.';
                $messageType = 'error';
            } else {
                $ok = $userModel->update($userId, [
                    'username' => $username,
                    'role_id' => $roleId,
                    'employee_id' => $employeeId > 0 ? $employeeId : null,
                    'is_active' => $active
                ]);
                if ($ok && $userId === $currentUserId) {
                    Session::set('username', $username);
                }
                $message = $ok ? 'User updated successfully.' : 'Unable to update user.';
                $messageType = $ok ? 'success' : 'error';
            }
        } elseif ($action === 'set_active') {
            $userId = (int)post('user_id', 0);
            $active = post('is_active', '0') === '1' ? 1 : 0;
            $target = $userModel->getById($userId);

            if (!$target) {
                $message = 'User not found.';
                $messageType = 'error';
            } elseif ($userId === $currentUserId && $active !== 1) {
                $message = 'You cannot deactivate the account you are currently using.';
                $messageType = 'error';
            } elseif ((int)$target['role_id'] === ROLE_ADMIN && $active !== 1 && $userModel->countActiveAdmins($userId) < 1) {
                $message = 'At least one active admin user must remain.';
                $messageType = 'error';
            } else {
                $ok = $userModel->setActive($userId, $active);
                $message = $ok ? ($active ? 'User activated.' : 'User deactivated.') : 'Unable to update user status.';
                $messageType = $ok ? 'success' : 'error';
            }
        } elseif ($action === 'reset_password') {
            $userId = (int)post('user_id', 0);
            $password = (string)post('password', '');
            $confirm = (string)post('password_confirm', '');
            $target = $userModel->getById($userId);
            $passwordError = auc_validate_password($password, $confirm, $passwordMinLength);

            if (!$target) {
                $message = 'User not found.';
                $messageType = 'error';
            } elseif ($passwordError !== '') {
                $message = $passwordError;
                $messageType = 'error';
            } else {
                $ok = $userModel->update($userId, ['password' => $password]);
                $message = $ok ? 'Password reset successfully.' : 'Unable to reset password.';
                $messageType = $ok ? 'success' : 'error';
            }
        }
    }
}

$roles = $userModel->getRoles();
$roleMap = [];
foreach ($roles as $role) {
    $roleMap[(int)$role['role_id']] = $role['role_name'];
}

$missingRoles = [];
foreach ($expectedRoles as $roleId => $roleName) {
    if (!isset($roleMap[(int)$roleId])) {
        $missingRoles[(int)$roleId] = $roleName;
    }
}

$users = $userModel->getAll() ?: [];
$roleSummary = $userModel->getSummaryByRole();
$employees = $employeeModel->getAll() ?: [];
$activeEmployees = $employeeModel->getActive() ?: [];
$activeMechanics = $employeeModel->getMechanics() ?: [];
$activeUsers = array_values(array_filter($users, function ($user) {
    return (int)$user['is_active'] === 1;
}));
$activeAdmins = array_values(array_filter($users, function ($user) {
    return (int)$user['is_active'] === 1 && (int)$user['role_id'] === ROLE_ADMIN;
}));
$mechanicLoginUsers = array_values(array_filter($users, function ($user) {
    return (int)$user['is_active'] === 1 && (int)$user['role_id'] === ROLE_MECHANIC;
}));

$usersByEmployeeId = [];
$usersByNormalizedName = [];
foreach ($users as $user) {
    if (!empty($user['employee_id'])) {
        $usersByEmployeeId[(int)$user['employee_id']] = $user;
    }
    $usersByNormalizedName[auc_normalize_key($user['username'])] = $user;
}

$pageTitle = 'Users Control';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .users-shell { display: grid; gap: 16px; padding-bottom: 24px; }
        .users-panel { background: #fff; border: 1px solid #d9e0e8; border-radius: 10px; padding: 18px; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .users-panel--create { background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%); }
        .users-panel-header { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; margin-bottom: 14px; }
        .users-panel-title { margin: 0; color: #173a6a; font-size: 20px; }
        .users-panel-subtitle { margin: 4px 0 0; color: #667085; font-size: 13px; }
        .users-summary-grid { display: grid; grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 10px; }
        .users-summary-card { padding: 16px; border: 1px solid #d9e0e8; border-radius: 10px; background: linear-gradient(180deg, #fff, #f8fafc); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.85); }
        .users-summary-card strong { display: block; color: #173a6a; font-size: 28px; line-height: 1; margin-bottom: 6px; }
        .users-summary-card span { color: #344054; font-weight: 700; }
        .users-panel input[type="text"],
        .users-panel input[type="password"],
        .users-panel select { width: 100%; min-height: 38px; box-sizing: border-box; padding: 8px 10px; border: 1px solid #b7c5d8; border-radius: 6px; background: #fff; }
        .users-panel input[type="text"]:focus,
        .users-panel input[type="password"]:focus,
        .users-panel select:focus { outline: none; border-color: #4472c4; box-shadow: 0 0 0 3px rgba(68, 114, 196, 0.16); }
        .users-form-grid { display: grid; grid-template-columns: minmax(180px, 1.1fr) minmax(145px, 0.7fr) minmax(210px, 1.1fr) minmax(170px, 0.9fr) minmax(170px, 0.9fr) 130px; gap: 12px; align-items: end; }
        .users-form-grid .form-group { margin: 0; }
        .users-form-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
        .users-checkline { display: flex; align-items: center; gap: 7px; min-height: 20px; color: #344054; font-weight: 700; }
        .users-checkline input { margin: 0; }
        .users-panel .btn { box-sizing: border-box; margin-right: 0; white-space: nowrap; }
        .users-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 620px), 1fr)); gap: 14px; }
        .user-card { min-width: 0; border: 1px solid #d9e0e8; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .user-card__head { display: flex; justify-content: space-between; gap: 10px; align-items: flex-start; padding: 14px 16px; background: linear-gradient(180deg, #fbfdff 0%, #f3f7fc 100%); border-bottom: 1px solid #e4eaf1; }
        .user-card__name { margin: 0; color: #1f2d3d; font-size: 18px; }
        .user-card__meta { margin-top: 3px; color: #667085; font-size: 12px; }
        .user-card__badges { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 6px; }
        .user-card__body,
        .user-card form,
        .user-edit-grid > *,
        .password-grid > * { min-width: 0; }
        .user-card__body { display: grid; gap: 14px; padding: 16px; }
        .user-edit-grid { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(130px, 0.75fr) minmax(180px, 1fr); gap: 10px; align-items: end; }
        .user-card-actions { display: flex; justify-content: space-between; gap: 10px; align-items: center; margin-top: 10px; }
        .user-card-actions .btn,
        .password-actions .btn { min-width: 130px; }
        .password-grid { display: grid; grid-template-columns: minmax(160px, 1fr) minmax(160px, 1fr); gap: 10px; align-items: end; padding-top: 12px; border-top: 1px solid #eef2f6; }
        .password-actions { display: flex; justify-content: flex-end; margin-top: 10px; }
        .status-pill { display: inline-block; border-radius: 999px; padding: 4px 9px; font-size: 12px; font-weight: 700; }
        .status-pill--active { color: #087443; background: #dcf4e8; }
        .status-pill--inactive { color: #5d6675; background: #eef2f6; }
        .role-pill { display: inline-block; border-radius: 999px; padding: 4px 9px; color: #173a6a; background: #e9f0fb; font-size: 12px; font-weight: 700; }
        .users-role-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
        .role-card { border: 1px solid #d9e0e8; border-radius: 10px; padding: 14px; background: #f8fafc; }
        .role-card strong { display: block; color: #173a6a; margin-bottom: 6px; }
        .users-table-wrap { overflow-x: auto; border: 1px solid #d9e0e8; border-radius: 10px; }
        .users-table-wrap .data-grid { margin: 0; border: 0; }
        .mechanic-table td, .mechanic-table th { vertical-align: middle; }
        .mechanic-login-form { display: grid; grid-template-columns: minmax(150px, 1fr) minmax(150px, 1fr) minmax(150px, 1fr) auto; gap: 8px; align-items: center; min-width: 620px; }
        .inline-form { display: inline; }
        .help-text { color: #667085; font-size: 12px; margin-top: 8px; }
        @media (max-width: 980px) {
            .users-summary-grid, .users-form-grid, .user-edit-grid, .password-grid { grid-template-columns: 1fr; }
            .users-panel-header { flex-direction: column; }
            .users-card-grid { grid-template-columns: 1fr; }
            .user-card-actions, .password-actions { align-items: flex-start; flex-direction: column; }
            .mechanic-login-form { min-width: 0; grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <a href="settings.php">Settings</a> |
            <a href="work_orders.php">Work Orders</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="users-shell">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo e($messageType); ?>"><?php echo e($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($missingRoles)): ?>
                <div class="alert alert-warning">
                    Missing expected role(s): <?php echo e(implode(', ', array_map('auc_role_label', $missingRoles))); ?>.
                    <form method="POST" class="inline-form">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="ensure_default_roles">
                        <button type="submit" class="btn">Repair Default Roles</button>
                    </form>
                </div>
            <?php endif; ?>

            <section class="users-panel users-panel--create">
                <div class="users-panel-header">
                    <div>
                        <h2 class="users-panel-title">User Access Overview</h2>
                        <p class="users-panel-subtitle">Control who can sign in, what role they have, and whether their account is active.</p>
                    </div>
                </div>
                <div class="users-summary-grid">
                    <div class="users-summary-card"><strong><?php echo count($users); ?></strong><span>Total Users</span></div>
                    <div class="users-summary-card"><strong><?php echo count($activeUsers); ?></strong><span>Active Users</span></div>
                    <div class="users-summary-card"><strong><?php echo count($activeAdmins); ?></strong><span>Active Admins</span></div>
                    <div class="users-summary-card"><strong><?php echo count($mechanicLoginUsers); ?></strong><span>Mechanic Logins</span></div>
                </div>
            </section>

            <section class="users-panel">
                <div class="users-panel-header">
                    <div>
                        <h2 class="users-panel-title">Create User</h2>
                        <p class="users-panel-subtitle">Passwords are stored as bcrypt hashes. New usernames must be unique.</p>
                    </div>
                </div>
                <form method="POST">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="create_user">
                    <div class="users-form-grid">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" name="username" required maxlength="50" placeholder="e.g. loway">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role_id" required>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo (int)$role['role_id']; ?>"><?php echo e(auc_role_label($role['role_name'])); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Employee Link</label>
                            <select name="employee_id">
                                <option value="">No employee link</option>
                                <?php foreach ($activeEmployees as $employee): ?>
                                    <option value="<?php echo (int)$employee['EmployeeID']; ?>"><?php echo e(auc_employee_option_label($employee)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Password</label>
                            <input type="password" name="password" required minlength="<?php echo (int)$passwordMinLength; ?>">
                        </div>
                        <div class="form-group">
                            <label>Confirm Password</label>
                            <input type="password" name="password_confirm" required minlength="<?php echo (int)$passwordMinLength; ?>">
                        </div>
                        <div class="users-form-actions">
                            <label class="users-checkline"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                            <button type="submit" class="btn btn-success">Create User</button>
                        </div>
                    </div>
                    <div class="help-text">Minimum password length on this page: <?php echo (int)$passwordMinLength; ?> characters. New users can only be linked to active employees.</div>
                </form>
            </section>

            <section class="users-panel">
                <div class="users-panel-header">
                    <div>
                        <h2 class="users-panel-title">Existing Users</h2>
                        <p class="users-panel-subtitle">Edit role/status or reset passwords. Deactivate users instead of deleting them.</p>
                    </div>
                </div>
                <div class="users-card-grid">
                    <?php foreach ($users as $user): ?>
                        <?php $isSelf = (int)$user['user_id'] === $currentUserId; ?>
                        <article class="user-card">
                            <div class="user-card__head">
                                <div>
                                    <h3 class="user-card__name"><?php echo e($user['username']); ?><?php echo $isSelf ? ' (you)' : ''; ?></h3>
                                    <div class="user-card__meta">
                                        Created <?php echo e(formatDateTime($user['created_at'])); ?>
                                        <?php if (!empty($user['last_login_at'])): ?>
                                            | Last login <?php echo e(formatDateTime($user['last_login_at'])); ?>
                                        <?php else: ?>
                                            | Never logged in
                                        <?php endif; ?>
                                        <?php if (!empty($user['employee_display'])): ?>
                                            | Employee: <?php echo e($user['employee_display']); ?><?php echo (string)($user['employee_status'] ?? '') !== EMPLOYEE_ACTIVE ? ' (inactive)' : ''; ?>
                                        <?php else: ?>
                                            | No employee link
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="user-card__badges">
                                    <span class="role-pill"><?php echo e(auc_role_label($user['role_name'])); ?></span>
                                    <span class="status-pill <?php echo (int)$user['is_active'] === 1 ? 'status-pill--active' : 'status-pill--inactive'; ?>">
                                        <?php echo (int)$user['is_active'] === 1 ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="user-card__body">
                                <form method="POST">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="update_user">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                    <div class="user-edit-grid">
                                        <div class="form-group">
                                            <label>Username</label>
                                            <input type="text" name="username" value="<?php echo e($user['username']); ?>" required maxlength="50">
                                        </div>
                                        <div class="form-group">
                                            <label>Role</label>
                                            <select name="role_id" required>
                                                <?php foreach ($roles as $role): ?>
                                                    <option value="<?php echo (int)$role['role_id']; ?>" <?php echo (int)$role['role_id'] === (int)$user['role_id'] ? 'selected' : ''; ?>>
                                                        <?php echo e(auc_role_label($role['role_name'])); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Employee Link</label>
                                            <select name="employee_id">
                                                <option value="">No employee link</option>
                                                <?php foreach ($employees as $employee): ?>
                                                    <option value="<?php echo (int)$employee['EmployeeID']; ?>" <?php echo (int)($user['employee_id'] ?? 0) === (int)$employee['EmployeeID'] ? 'selected' : ''; ?>>
                                                        <?php echo e(auc_employee_option_label($employee, true)); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="user-card-actions">
                                        <label class="users-checkline"><input type="checkbox" name="is_active" value="1" <?php echo (int)$user['is_active'] === 1 ? 'checked' : ''; ?>> Active</label>
                                        <button type="submit" class="btn">Save User</button>
                                    </div>
                                </form>
                                <form method="POST">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="reset_password">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user['user_id']; ?>">
                                    <div class="password-grid">
                                        <div class="form-group">
                                            <label>New Password</label>
                                            <input type="password" name="password" minlength="<?php echo (int)$passwordMinLength; ?>" placeholder="Leave private">
                                        </div>
                                        <div class="form-group">
                                            <label>Confirm Password</label>
                                            <input type="password" name="password_confirm" minlength="<?php echo (int)$passwordMinLength; ?>">
                                        </div>
                                    </div>
                                    <div class="password-actions">
                                        <button type="submit" class="btn">Reset Password</button>
                                    </div>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="users-panel">
                <div class="users-panel-header">
                    <div>
                        <h2 class="users-panel-title">Mechanic Employee Login Coverage</h2>
                        <p class="users-panel-subtitle">Active mechanic employees are listed here. If a mechanic has no matching login, create one directly.</p>
                    </div>
                </div>
                <div class="users-table-wrap">
                    <table class="data-grid mechanic-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Position</th>
                                <th>Matching Login</th>
                                <th>Create Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeMechanics as $employee): ?>
                                <?php
                                    $matchedUser = $usersByEmployeeId[(int)$employee['EmployeeID']] ?? null;
                                    $possibleUser = null;
                                    if (!$matchedUser) {
                                        $candidateKeys = array_filter([
                                            auc_normalize_key($employee['Display'] ?? ''),
                                            auc_normalize_key($employee['FirstName'] ?? ''),
                                            auc_normalize_key(trim(($employee['FirstName'] ?? '') . ' ' . ($employee['LastName'] ?? '')))
                                        ]);
                                        foreach ($candidateKeys as $candidateKey) {
                                            if (isset($usersByNormalizedName[$candidateKey])) {
                                                $possibleUser = $usersByNormalizedName[$candidateKey];
                                                break;
                                            }
                                        }
                                    }
                                    $suggestedUsername = auc_suggest_username($employee);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($employee['Display'] ?: trim(($employee['FirstName'] ?? '') . ' ' . ($employee['LastName'] ?? ''))); ?></strong>
                                        <div class="text-muted">Employee ID <?php echo (int)$employee['EmployeeID']; ?></div>
                                    </td>
                                    <td><?php echo e($employee['Position']); ?></td>
                                    <td>
                                        <?php if ($matchedUser): ?>
                                            <span class="status-pill status-pill--active"><?php echo e($matchedUser['username']); ?> / <?php echo e(auc_role_label($matchedUser['role_name'])); ?></span>
                                        <?php elseif ($possibleUser): ?>
                                            <span class="status-pill status-pill--inactive">Possible unlinked: <?php echo e($possibleUser['username']); ?></span>
                                        <?php else: ?>
                                            <span class="status-pill status-pill--inactive">No matching login</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$matchedUser && !$possibleUser && isset($roleMap[ROLE_MECHANIC])): ?>
                                            <form method="POST" class="mechanic-login-form">
                                                <?php csrfField(); ?>
                                                <input type="hidden" name="action" value="create_mechanic_login">
                                                <input type="hidden" name="employee_id" value="<?php echo (int)$employee['EmployeeID']; ?>">
                                                <input type="text" name="username" value="<?php echo e($suggestedUsername); ?>" required maxlength="50">
                                                <input type="password" name="password" placeholder="Password" required minlength="<?php echo (int)$passwordMinLength; ?>">
                                                <input type="password" name="password_confirm" placeholder="Confirm" required minlength="<?php echo (int)$passwordMinLength; ?>">
                                                <button type="submit" class="btn btn-success">Create</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted"><?php echo $possibleUser ? 'Link the existing user card above.' : 'No action needed'; ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="users-panel">
                <div class="users-panel-header">
                    <div>
                        <h2 class="users-panel-title">Roles</h2>
                        <p class="users-panel-subtitle">Roles control access level. Keep at least one active admin at all times.</p>
                    </div>
                </div>
                <div class="users-role-grid">
                    <?php foreach ($roleSummary as $role): ?>
                        <div class="role-card">
                            <strong><?php echo e(auc_role_label($role['role_name'])); ?></strong>
                            <div>Total users: <?php echo (int)$role['total_users']; ?></div>
                            <div>Active: <?php echo (int)$role['active_users']; ?></div>
                            <div>Inactive: <?php echo (int)$role['inactive_users']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </div>
</body>
</html>
