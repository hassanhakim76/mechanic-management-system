<?php
/**
 * Admin - Advanced Find
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$db = Database::getInstance()->getConnection();
$woModel = new WorkOrder();

function af_clean_scope($scope) {
    $allowed = ['all', 'work_orders', 'customers', 'vehicles', 'drafts'];
    return in_array($scope, $allowed, true) ? $scope : 'all';
}

function af_clean_operator($operator) {
    $allowed = ['Contain', 'Equal', 'Start With', 'End With'];
    return in_array($operator, $allowed, true) ? $operator : 'Contain';
}

function af_append_condition(array &$parts, array &$params, $expr, $operator, $value) {
    $collatedExpr = '(' . $expr . ') COLLATE utf8mb4_general_ci';
    $collatedParam = '(? COLLATE utf8mb4_general_ci)';

    switch ($operator) {
        case 'Equal':
            $parts[] = "$collatedExpr = $collatedParam";
            $params[] = $value;
            break;
        case 'Start With':
            $parts[] = "$collatedExpr LIKE $collatedParam";
            $params[] = $value . '%';
            break;
        case 'End With':
            $parts[] = "$collatedExpr LIKE $collatedParam";
            $params[] = '%' . $value;
            break;
        case 'Contain':
        default:
            $parts[] = "$collatedExpr LIKE $collatedParam";
            $params[] = '%' . $value . '%';
            break;
    }
}

function af_build_condition(array $targets, $operator, $value, array &$params, array $compactTargets = [], array $phoneTargets = []) {
    $value = trim((string)$value);
    if ($value === '') {
        return '1=0';
    }

    $parts = [];
    foreach ($targets as $expr) {
        af_append_condition($parts, $params, $expr, $operator, $value);
    }

    $digitsOnly = preg_replace('/\D+/', '', $value);
    $looksNumeric = (bool)preg_match('/^[\d\s().+\-]+$/', $value);
    if ($looksNumeric && $digitsOnly !== '') {
        foreach ($phoneTargets as $expr) {
            af_append_condition($parts, $params, $expr, $operator, $digitsOnly);
        }
    }

    $compact = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value));
    if ($compact !== '') {
        foreach ($compactTargets as $expr) {
            af_append_condition($parts, $params, $expr, $operator, $compact);
        }
    }

    return empty($parts) ? '1=0' : '(' . implode(' OR ', $parts) . ')';
}

function af_search_customers(PDO $db, $operator, $value) {
    $params = [];
    $where = af_build_condition(
        [
            "CAST(c.CustomerID AS CHAR)",
            "COALESCE(c.FirstName, '')",
            "COALESCE(c.LastName, '')",
            "CONCAT_WS(' ', c.FirstName, c.LastName)",
            "CONCAT_WS(' ', c.LastName, c.FirstName)",
            "COALESCE(c.Phone, '')",
            "COALESCE(c.Cell, '')",
            "COALESCE(c.Email, '')",
            "COALESCE(c.Address, '')",
            "COALESCE(c.City, '')",
            "COALESCE(c.PostalCode, '')"
        ],
        $operator,
        $value,
        $params,
        [],
        ["COALESCE(c.Phone, '')", "COALESCE(c.Cell, '')"]
    );

    $sql = "
        SELECT c.*,
               (SELECT COUNT(*) FROM customer_vehicle cv WHERE cv.CustomerID = c.CustomerID AND cv.Status = ?) AS vehicle_count,
               (SELECT COUNT(*) FROM work_order wo WHERE wo.CustomerID = c.CustomerID) AS wo_count
        FROM customers c
        WHERE $where
        ORDER BY c.CustomerID DESC
        LIMIT 50
    ";

    array_unshift($params, VEHICLE_ACTIVE);
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function af_search_vehicles(PDO $db, $operator, $value) {
    $params = [];
    $where = af_build_condition(
        [
            "CAST(cv.CVID AS CHAR)",
            "CAST(cv.CustomerID AS CHAR)",
            "COALESCE(cv.Plate, '')",
            "COALESCE(cv.VIN, '')",
            "COALESCE(cv.Make, '')",
            "COALESCE(cv.Model, '')",
            "COALESCE(cv.Year, '')",
            "COALESCE(cv.Color, '')",
            "CONCAT_WS(' ', cv.Year, cv.Make, cv.Model)",
            "CONCAT_WS(' ', cv.Make, cv.Model)",
            "CONCAT_WS(' ', c.FirstName, c.LastName)",
            "COALESCE(c.Phone, '')",
            "COALESCE(c.Cell, '')",
            "COALESCE(c.Email, '')"
        ],
        $operator,
        $value,
        $params,
        [],
        ["COALESCE(c.Phone, '')", "COALESCE(c.Cell, '')"]
    );

    $sql = "
        SELECT cv.*, c.FirstName, c.LastName, c.Phone, c.Cell, c.Email,
               (SELECT COUNT(*) FROM work_order wo WHERE wo.CVID = cv.CVID) AS wo_count
        FROM customer_vehicle cv
        JOIN customers c ON cv.CustomerID = c.CustomerID
        WHERE $where
        ORDER BY CASE WHEN cv.Status = ? THEN 0 ELSE 1 END, cv.CVID DESC
        LIMIT 50
    ";

    $params[] = VEHICLE_ACTIVE;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function af_search_drafts(PDO $db, $operator, $value) {
    $params = [];
    $where = af_build_condition(
        [
            "CAST(dwo.draft_wo_id AS CHAR)",
            "CAST(dc.draft_customer_id AS CHAR)",
            "CAST(dv.draft_vehicle_id AS CHAR)",
            "COALESCE(dwo.status, '')",
            "COALESCE(dwo.wo_status, '')",
            "COALESCE(dwo.priority, '')",
            "COALESCE(dwo.wo_note, '')",
            "COALESCE(dwo.missing_reasons, '')",
            "COALESCE(dc.first_name, '')",
            "COALESCE(dc.last_name, '')",
            "CONCAT_WS(' ', dc.first_name, dc.last_name)",
            "COALESCE(dc.phone, '')",
            "COALESCE(dc.cell, '')",
            "COALESCE(dc.email, '')",
            "COALESCE(dv.plate, '')",
            "COALESCE(dv.vin, '')",
            "COALESCE(dv.make, '')",
            "COALESCE(dv.model, '')",
            "COALESCE(dv.year, '')",
            "COALESCE(u.username, '')"
        ],
        $operator,
        $value,
        $params,
        [],
        ["COALESCE(dc.phone, '')", "COALESCE(dc.cell, '')"]
    );

    $sql = "
        SELECT dwo.draft_wo_id, dwo.status AS draft_wo_status, dwo.created_at, dwo.wo_status,
               dwo.priority, dwo.mileage, dwo.wo_note, dwo.readiness_state, dwo.escalation_level,
               dc.draft_customer_id, dc.first_name, dc.last_name, dc.phone, dc.cell, dc.email,
               dv.draft_vehicle_id, dv.plate, dv.vin, dv.make, dv.model, dv.year,
               u.username AS created_by_username
        FROM draft_work_orders dwo
        JOIN draft_customers dc ON dc.draft_customer_id = dwo.draft_customer_id
        LEFT JOIN draft_vehicles dv ON dv.draft_vehicle_id = dwo.draft_vehicle_id
        LEFT JOIN users u ON u.user_id = dwo.created_by_user_id
        WHERE $where
        ORDER BY dwo.created_at DESC, dwo.draft_wo_id DESC
        LIMIT 50
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function af_scope_label($scope) {
    $labels = [
        'all' => 'All',
        'work_orders' => 'Work Orders',
        'customers' => 'Customers',
        'vehicles' => 'Vehicles',
        'drafts' => 'Draft Intake'
    ];
    return $labels[$scope] ?? 'All';
}

function af_vehicle_status_label($status) {
    return (string)$status === VEHICLE_ACTIVE ? 'Active vehicle' : 'Inactive vehicle';
}

function af_vehicle_status_class($status) {
    return (string)$status === VEHICLE_ACTIVE ? 'find-status-active' : 'find-status-inactive';
}

$query = trim((string)get('q', ''));
$scope = af_clean_scope((string)get('scope', 'all'));
$operator = af_clean_operator((string)get('operator', 'Contain'));
$hasSearch = $query !== '';

$workOrderResults = [];
$customerResults = [];
$vehicleResults = [];
$draftResults = [];

if ($hasSearch && ($scope === 'all' || $scope === 'work_orders')) {
    $workOrderResults = $woModel->getList([
        'hide_completed' => 0,
        'status' => 'All',
        'search_operator' => $operator,
        'search_value' => $query,
        'limit' => 50
    ]) ?: [];
}

if ($hasSearch && ($scope === 'all' || $scope === 'customers')) {
    $customerResults = af_search_customers($db, $operator, $query);
}

if ($hasSearch && ($scope === 'all' || $scope === 'vehicles')) {
    $vehicleResults = af_search_vehicles($db, $operator, $query);
}

if ($hasSearch && ($scope === 'all' || $scope === 'drafts')) {
    $draftResults = af_search_drafts($db, $operator, $query);
}

$totalResults = count($workOrderResults) + count($customerResults) + count($vehicleResults) + count($draftResults);
$workOrderGridUrl = 'work_orders.php?' . http_build_query([
    'search_operator' => $operator,
    'search_value' => $query,
    'hide_completed' => '0',
    'status' => 'All'
]);

$pageTitle = 'Advanced Find';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .find-shell { display: grid; gap: 14px; padding-bottom: 24px; }
        .find-panel { background: #fff; border: 1px solid #d9e0e8; border-radius: 10px; padding: 18px; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .find-hero { background: linear-gradient(135deg, #ffffff 0%, #f5f8fc 60%, #edf3fb 100%); }
        .find-title { margin: 0; color: #173a6a; font-size: 22px; }
        .find-subtitle { margin: 5px 0 0; color: #667085; font-size: 13px; }
        .find-form-grid { display: grid; grid-template-columns: minmax(260px, 1.4fr) minmax(150px, 0.6fr) minmax(160px, 0.7fr) auto auto; gap: 10px; align-items: end; margin-top: 16px; }
        .find-panel label { display: block; margin-bottom: 5px; color: #344054; font-weight: 700; font-size: 12px; }
        .find-panel input[type="text"],
        .find-panel select { width: 100%; min-height: 40px; box-sizing: border-box; padding: 9px 11px; border: 1px solid #b7c5d8; border-radius: 7px; background: #fff; font-size: 14px; }
        .find-panel input:focus,
        .find-panel select:focus { outline: none; border-color: #4472c4; box-shadow: 0 0 0 3px rgba(68, 114, 196, 0.16); }
        .find-panel .btn { margin-right: 0; min-height: 38px; border-radius: 6px; white-space: nowrap; }
        .find-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(130px, 1fr)); gap: 10px; }
        .find-summary-card { padding: 13px 14px; border: 1px solid #d9e0e8; border-radius: 10px; background: #f8fafc; }
        .find-summary-card strong { display: block; color: #173a6a; font-size: 24px; line-height: 1; margin-bottom: 5px; }
        .find-summary-card span { color: #344054; font-weight: 700; font-size: 12px; }
        .find-section-header { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin-bottom: 12px; }
        .find-section-title { margin: 0; color: #173a6a; font-size: 18px; }
        .find-section-count { display: inline-flex; align-items: center; justify-content: center; min-height: 26px; padding: 0 9px; border-radius: 999px; color: #47648c; background: #e8eef8; font-weight: 700; font-size: 12px; }
        .find-status-active { color: #087443; background: #dff7e8; }
        .find-status-inactive { color: #596579; background: #eef2f6; }
        .find-card-grid { display: grid; grid-template-columns: 1fr; gap: 7px; }
        .find-result-card {
            display: grid;
            grid-template-columns: minmax(190px, 0.8fr) minmax(230px, 1fr) minmax(270px, 1.15fr) minmax(300px, 1.3fr) auto;
            gap: 12px;
            align-items: center;
            min-height: 54px;
            padding: 8px 12px;
            border: 1px solid #d9e0e8;
            border-radius: 8px;
            background: #fbfdff;
            box-shadow: inset 4px 0 0 #dbe7f5;
        }
        .find-result-card__top { grid-column: 1; display: flex; justify-content: flex-start; gap: 10px; align-items: center; min-width: 0; }
        .find-result-card__top > div:first-child { min-width: 0; }
        .find-result-card__title { margin: 0 0 2px; color: #1f2d3d; font-size: 15px; line-height: 1.15; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .find-result-card__meta { color: #667085; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .find-result-card__details { display: contents; }
        .find-result-card__field { min-width: 0; color: #344054; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .find-result-card__field strong { color: #5f6f86; font-size: 11px; font-weight: 700; letter-spacing: 0.04em; text-transform: uppercase; }
        .find-result-card__line { display: none; }
        .find-result-card__actions {
            grid-column: 5;
            grid-row: auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: 8px;
            min-width: 180px;
        }
        .find-empty { margin: 0; color: #667085; }
        @media (max-width: 980px) {
            .find-form-grid,
            .find-summary-grid { grid-template-columns: 1fr; }
            .find-section-header,
            .find-result-card__top { flex-direction: column; align-items: flex-start; }
            .find-result-card { grid-template-columns: 1fr; }
            .find-result-card__details { display: grid; gap: 4px; }
            .find-result-card__field,
            .find-result-card__actions { grid-column: 1; grid-row: auto; justify-content: flex-start; min-width: 0; padding-top: 2px; white-space: normal; }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <a href="work_orders.php">Work Orders</a> |
            <a href="settings.php">Settings</a> |
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container-fluid">
        <div class="find-shell">
            <section class="find-panel find-hero">
                <h2 class="find-title">Advanced Find</h2>
                <p class="find-subtitle">Search work orders, customers, vehicles, and draft intake from one place.</p>

                <form method="GET" class="find-form-grid">
                    <div>
                        <label>Search</label>
                        <input type="text" name="q" value="<?php echo e($query); ?>" placeholder="Work order, customer, phone, VIN, plate, vehicle, draft">
                    </div>
                    <div>
                        <label>Search In</label>
                        <select name="scope">
                            <option value="all" <?php echo $scope === 'all' ? 'selected' : ''; ?>>All</option>
                            <option value="work_orders" <?php echo $scope === 'work_orders' ? 'selected' : ''; ?>>Work Orders</option>
                            <option value="customers" <?php echo $scope === 'customers' ? 'selected' : ''; ?>>Customers</option>
                            <option value="vehicles" <?php echo $scope === 'vehicles' ? 'selected' : ''; ?>>Vehicles</option>
                            <option value="drafts" <?php echo $scope === 'drafts' ? 'selected' : ''; ?>>Draft Intake</option>
                        </select>
                    </div>
                    <div>
                        <label>Operator</label>
                        <select name="operator">
                            <option value="Contain" <?php echo $operator === 'Contain' ? 'selected' : ''; ?>>Contain</option>
                            <option value="Equal" <?php echo $operator === 'Equal' ? 'selected' : ''; ?>>Equal</option>
                            <option value="Start With" <?php echo $operator === 'Start With' ? 'selected' : ''; ?>>Start With</option>
                            <option value="End With" <?php echo $operator === 'End With' ? 'selected' : ''; ?>>End With</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a class="btn" href="find.php">Clear</a>
                </form>
            </section>

            <?php if ($hasSearch): ?>
                <section class="find-panel">
                    <div class="find-section-header">
                        <div>
                            <h2 class="find-section-title">Search Summary</h2>
                            <div class="find-result-card__meta"><?php echo e($operator); ?> "<?php echo e($query); ?>" in <?php echo e(af_scope_label($scope)); ?></div>
                        </div>
                        <?php if (!empty($workOrderResults)): ?>
                            <a class="btn" href="<?php echo e($workOrderGridUrl); ?>">Open Work Order Grid</a>
                        <?php endif; ?>
                    </div>
                    <div class="find-summary-grid">
                        <div class="find-summary-card"><strong><?php echo (int)$totalResults; ?></strong><span>Total</span></div>
                        <div class="find-summary-card"><strong><?php echo count($workOrderResults); ?></strong><span>Work Orders</span></div>
                        <div class="find-summary-card"><strong><?php echo count($customerResults); ?></strong><span>Customers</span></div>
                        <div class="find-summary-card"><strong><?php echo count($vehicleResults); ?></strong><span>Vehicles</span></div>
                        <div class="find-summary-card"><strong><?php echo count($draftResults); ?></strong><span>Drafts</span></div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!$hasSearch): ?>
                <section class="find-panel">
                    <p class="find-empty">Enter a search term to find work orders, customers, vehicles, or draft intake records.</p>
                </section>
            <?php elseif ($totalResults === 0): ?>
                <section class="find-panel">
                    <p class="find-empty">No matches found.</p>
                </section>
            <?php endif; ?>

            <?php if ($hasSearch && ($scope === 'all' || $scope === 'work_orders')): ?>
                <section class="find-panel">
                    <div class="find-section-header">
                        <h2 class="find-section-title">Work Orders</h2>
                        <span class="find-section-count"><?php echo count($workOrderResults); ?></span>
                    </div>
                    <div class="find-card-grid">
                        <?php foreach ($workOrderResults as $wo): ?>
                            <article class="find-result-card">
                                <div class="find-result-card__top">
                                    <div>
                                        <h3 class="find-result-card__title"><?php echo e(generateWONumber((int)$wo['WOID'])); ?></h3>
                                        <div class="find-result-card__meta"><?php echo e(formatDateTime($wo['WO_Date'])); ?> | <?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></div>
                                    </div>
                                    <div><?php echo statusBadge($wo['WO_Status']); ?></div>
                                </div>
                                <div class="find-result-card__details">
                                    <div class="find-result-card__field"><strong>Vehicle</strong> <?php echo e(trim(($wo['Year'] ?? '') . ' ' . ($wo['Make'] ?? '') . ' ' . ($wo['Model'] ?? ''))); ?> <?php echo !empty($wo['Color']) ? '| ' . e($wo['Color']) : ''; ?></div>
                                    <div class="find-result-card__field"><strong>Plate/VIN</strong> <?php echo e($wo['Plate']); ?> | <?php echo e($wo['VIN']); ?></div>
                                    <div class="find-result-card__field"><strong>Work</strong> <?php echo e(combineWorkItems($wo)); ?></div>
                                </div>
                                <div class="find-result-card__actions">
                                    <a class="btn" href="work_order_detail.php?woid=<?php echo (int)$wo['WOID']; ?>">Open Work Order</a>
                                    <?php if (!empty($wo['VIN'])): ?>
                                        <a class="btn" href="work_order_history.php?vin=<?php echo urlencode($wo['VIN']); ?>&return=<?php echo (int)$wo['WOID']; ?>&source=admin">History</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($workOrderResults)): ?><p class="find-empty">No work order matches.</p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($hasSearch && ($scope === 'all' || $scope === 'customers')): ?>
                <section class="find-panel">
                    <div class="find-section-header">
                        <h2 class="find-section-title">Customers</h2>
                        <span class="find-section-count"><?php echo count($customerResults); ?></span>
                    </div>
                    <div class="find-card-grid">
                        <?php foreach ($customerResults as $customer): ?>
                            <article class="find-result-card">
                                <div class="find-result-card__top">
                                    <div>
                                        <h3 class="find-result-card__title"><?php echo e(getFullName($customer['FirstName'], $customer['LastName'])); ?></h3>
                                        <div class="find-result-card__meta">Customer ID <?php echo (int)$customer['CustomerID']; ?></div>
                                    </div>
                                    <span class="find-section-count"><?php echo (int)$customer['wo_count']; ?> WO</span>
                                </div>
                                <div class="find-result-card__details">
                                    <div class="find-result-card__field"><strong>Contact</strong> Phone: <?php echo e($customer['Phone']); ?> | Cell: <?php echo e($customer['Cell']); ?></div>
                                    <div class="find-result-card__field"><strong>Email</strong> <?php echo e($customer['Email']); ?></div>
                                    <div class="find-result-card__field"><strong>History</strong> Vehicles: <?php echo (int)$customer['vehicle_count']; ?> | Work Orders: <?php echo (int)$customer['wo_count']; ?></div>
                                </div>
                                <div class="find-result-card__actions">
                                    <a class="btn" href="customer_detail.php?id=<?php echo (int)$customer['CustomerID']; ?>">Open Customer</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($customerResults)): ?><p class="find-empty">No customer matches.</p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($hasSearch && ($scope === 'all' || $scope === 'vehicles')): ?>
                <section class="find-panel">
                    <div class="find-section-header">
                        <h2 class="find-section-title">Vehicles</h2>
                        <span class="find-section-count"><?php echo count($vehicleResults); ?></span>
                    </div>
                    <div class="find-card-grid">
                        <?php foreach ($vehicleResults as $vehicle): ?>
                            <article class="find-result-card">
                                <div class="find-result-card__top">
                                    <div>
                                        <h3 class="find-result-card__title"><?php echo e(trim(($vehicle['Year'] ?? '') . ' ' . ($vehicle['Make'] ?? '') . ' ' . ($vehicle['Model'] ?? ''))); ?></h3>
                                        <div class="find-result-card__meta">CVID <?php echo (int)$vehicle['CVID']; ?> | <?php echo e(getFullName($vehicle['FirstName'], $vehicle['LastName'])); ?></div>
                                    </div>
                                    <span class="find-section-count <?php echo e(af_vehicle_status_class($vehicle['Status'])); ?>"><?php echo e(af_vehicle_status_label($vehicle['Status'])); ?></span>
                                </div>
                                <div class="find-result-card__details">
                                    <div class="find-result-card__field"><strong>Owner</strong> <?php echo e(getFullName($vehicle['FirstName'], $vehicle['LastName'])); ?></div>
                                    <div class="find-result-card__field"><strong>Plate/VIN</strong> <?php echo e($vehicle['Plate']); ?> | <?php echo e($vehicle['VIN']); ?></div>
                                    <div class="find-result-card__field"><strong>Details</strong> Color: <?php echo e($vehicle['Color']); ?> | Work orders: <?php echo (int)$vehicle['wo_count']; ?></div>
                                </div>
                                <div class="find-result-card__actions">
                                    <a class="btn" href="customer_detail.php?id=<?php echo (int)$vehicle['CustomerID']; ?>">Open Customer</a>
                                    <?php if (!empty($vehicle['VIN'])): ?>
                                        <a class="btn" href="work_order_history.php?vin=<?php echo urlencode($vehicle['VIN']); ?>&source=admin">History</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($vehicleResults)): ?><p class="find-empty">No vehicle matches.</p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($hasSearch && ($scope === 'all' || $scope === 'drafts')): ?>
                <section class="find-panel">
                    <div class="find-section-header">
                        <h2 class="find-section-title">Draft Intake</h2>
                        <span class="find-section-count"><?php echo count($draftResults); ?></span>
                    </div>
                    <div class="find-card-grid">
                        <?php foreach ($draftResults as $draft): ?>
                            <article class="find-result-card">
                                <div class="find-result-card__top">
                                    <div>
                                        <h3 class="find-result-card__title">Draft #<?php echo (int)$draft['draft_wo_id']; ?></h3>
                                        <div class="find-result-card__meta"><?php echo e(formatDateTime($draft['created_at'])); ?> | <?php echo e(trim(($draft['first_name'] ?? '') . ' ' . ($draft['last_name'] ?? ''))); ?></div>
                                    </div>
                                    <span class="find-section-count"><?php echo e($draft['draft_wo_status']); ?></span>
                                </div>
                                <div class="find-result-card__details">
                                    <div class="find-result-card__field"><strong>Contact</strong> Phone: <?php echo e($draft['phone']); ?> | Cell: <?php echo e($draft['cell']); ?></div>
                                    <div class="find-result-card__field"><strong>Vehicle</strong> <?php echo e(trim(($draft['year'] ?? '') . ' ' . ($draft['make'] ?? '') . ' ' . ($draft['model'] ?? ''))); ?> | Plate: <?php echo e($draft['plate']); ?></div>
                                    <div class="find-result-card__field"><strong>VIN</strong> <?php echo e($draft['vin']); ?></div>
                                </div>
                                <div class="find-result-card__actions">
                                    <a class="btn" href="../intake/draft_view.php?draft_wo_id=<?php echo (int)$draft['draft_wo_id']; ?>">Open Draft</a>
                                    <?php if ((string)$draft['draft_wo_status'] === 'draft'): ?>
                                        <a class="btn btn-success" href="../intake/approve.php?draft_wo_id=<?php echo (int)$draft['draft_wo_id']; ?>">Approve</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if (empty($draftResults)): ?><p class="find-empty">No draft matches.</p><?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
