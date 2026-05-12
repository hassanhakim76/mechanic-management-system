<?php
/**
 * Work Order History (by VIN)
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin() && !Session::isMechanic()) {
    redirect(BASE_URL . '/index.php');
}

$woModel = new WorkOrder();

$vin = trim(get('vin', ''));
$returnWoid = (int)get('return', 0);
$source = trim((string)get('source', Session::isMechanic() && !Session::isAdmin() ? 'mechanic' : 'admin'));
if (!in_array($source, ['admin', 'mechanic'], true)) {
    $source = Session::isMechanic() && !Session::isAdmin() ? 'mechanic' : 'admin';
}

$detailUrl = $source === 'mechanic'
    ? '../mechanic/work_order_detail.php'
    : 'work_order_detail.php';
$listUrl = $source === 'mechanic'
    ? '../mechanic/work_orders.php'
    : 'work_orders.php';

if ($vin === '') {
    Session::setFlashMessage('error', 'VIN is required');
    redirect($listUrl);
}

$workOrders = $woModel->getByVin($vin);

$pageTitle = 'Work Order History - ' . $vin;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <?php if ($returnWoid > 0): ?>
                <a href="<?php echo e($detailUrl); ?>?woid=<?php echo $returnWoid; ?>">Back</a> |
            <?php else: ?>
                <a href="<?php echo e($listUrl); ?>">Back</a> |
            <?php endif; ?>
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo e($flash['message']); ?>
            </div>
        <?php endif; ?>

        <table class="data-grid">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Mileage</th>
                    <th>Plate</th>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Year</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Mechanic</th>
                    <th>Admin</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($workOrders)): ?>
                    <tr>
                        <td colspan="13" style="text-align: center; padding: 20px;">
                            No work orders found for this VIN
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($workOrders as $wo): ?>
                        <tr onclick="window.location.href='<?php echo e($detailUrl); ?>?woid=<?php echo $wo['WOID']; ?>'" style="cursor: pointer;">
                            <td><?php echo generateWONumber($wo['WOID']); ?></td>
                            <td><?php echo formatDateTime($wo['WO_Date']); ?></td>
                            <td><?php echo statusBadge($wo['WO_Status']); ?></td>
                            <td><?php echo priorityBadge($wo['Priority']); ?></td>
                            <td><?php echo formatMileage($wo['Mileage']); ?></td>
                            <td><?php echo e($wo['Plate']); ?></td>
                            <td><?php echo e($wo['Make']); ?></td>
                            <td><?php echo e($wo['Model']); ?></td>
                            <td><?php echo e($wo['Year']); ?></td>
                            <td><?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></td>
                            <td><?php echo e($wo['Phone']); ?></td>
                            <td><?php echo e($wo['Mechanic']); ?></td>
                            <td><?php echo e($wo['Admin']); ?></td>
                        </tr>
                        <tr class="sub-row">
                            <td colspan="13" style="padding: 6px 10px; color: #555;">
                                <strong>Work Items:</strong>
                                <?php
                                    $workItems = combineWorkItems($wo);
                                    echo $workItems !== '' ? e($workItems) : '<span class="text-muted">None</span>';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script src="../../public/js/main.js"></script>
</body>
</html>
