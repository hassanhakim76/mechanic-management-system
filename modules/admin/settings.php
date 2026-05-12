<?php
/**
 * Admin - Settings
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$pageTitle = 'Settings';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .settings-shell {
            display: grid;
            gap: 14px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 12px;
        }

        .settings-card {
            display: block;
            min-height: 118px;
            padding: 16px;
            border: 1px solid #d9e0e8;
            border-radius: 8px;
            background: #fff;
            color: #20324a;
            text-decoration: none;
            transition: border-color 0.18s ease, box-shadow 0.18s ease, transform 0.18s ease;
        }

        .settings-card:hover {
            border-color: #8ea8cb;
            box-shadow: 0 10px 18px rgba(31, 55, 92, 0.12);
            transform: translateY(-2px);
        }

        .settings-card strong {
            display: block;
            margin-bottom: 6px;
            color: #173a6a;
            font-size: 18px;
        }

        .settings-card span {
            display: block;
            color: #667085;
            font-size: 13px;
            line-height: 1.35;
        }

        .settings-panel {
            background: #fff;
            border: 1px solid #d9e0e8;
            border-radius: 8px;
            padding: 16px;
        }

        .settings-panel h2 {
            margin: 0 0 8px;
            color: #173a6a;
            font-size: 18px;
        }

        .settings-empty {
            margin: 0;
            color: #667085;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo $pageTitle; ?></h1>
        <div class="user-info">
            <a href="work_orders.php">Work Orders</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="settings-shell">
            <section class="settings-panel">
                <h2>Settings</h2>
                <div class="settings-grid">
	                    <a class="settings-card" href="inspection_settings.php">
	                        <strong>Inspection Settings</strong>
	                        <span>Control inspection categories and items. Add, rename, reorder, deactivate, or reactivate checklist content.</span>
	                    </a>
		                    <a class="settings-card" href="users.php">
		                        <strong>Users Control</strong>
		                        <span>Create users, assign roles, reset passwords, activate or deactivate access, and create mechanic logins.</span>
		                    </a>
		                    <a class="settings-card" href="employees.php">
		                        <strong>Employee Control</strong>
		                        <span>Create employees, edit staff details, manage active status, and review which employees are linked to user logins.</span>
		                    </a>
		                </div>
	            </section>
        </div>
    </div>
</body>
</html>
