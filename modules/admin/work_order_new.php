<?php
/**
 * Admin - New Work Order
 * Create a work order for an existing customer/vehicle
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$vehicleModel = new Vehicle();
$workOrderModel = new WorkOrder();

$error = '';
$results = [];
$searchQuery = '';
$searchType = 'Plate';

if (isPost() && verifyCSRFToken(post('csrf_token'))) {
    $action = post('action', '');

    if ($action === 'search') {
        $searchQuery = trim(post('search_query', ''));
        $searchType = post('search_type', 'Plate');

        if ($searchQuery === '') {
            $error = 'Please enter a Plate or VIN to search.';
        } else {
            $normalized = strtoupper($searchQuery);
            if ($searchType === 'VIN') {
                $exact = $vehicleModel->searchByVIN($normalized);
            } else {
                $exact = $vehicleModel->searchByPlate($normalized);
            }

            if ($exact) {
                $results = [$exact];
            } else {
                $results = $vehicleModel->search($normalized);
            }
        }
    }

    if ($action === 'create_work_order') {
        $cvid = (int)post('cvid');
        $customerId = (int)post('customer_id');
        $workRequired = trim(post('work_required', ''));
        $mileage = trim(post('mileage', ''));
        $workItems = splitWorkItems($workRequired);

        if ($cvid <= 0 || $customerId <= 0) {
            $error = 'Vehicle selection is required.';
        } else {
            $woid = $workOrderModel->create([
                'CustomerID' => $customerId,
                'CVID' => $cvid,
                'Mileage' => $mileage,
                'WO_Status' => STATUS_NEW,
                'Priority' => PRIORITY_NORMAL,
                'WO_Req1' => $workItems[0],
                'WO_Req2' => $workItems[1],
                'WO_Req3' => $workItems[2],
                'WO_Req4' => $workItems[3],
                'WO_Req5' => $workItems[4],
                'Admin' => Session::getUsername()
            ]);

            if ($woid) {
                Session::setFlashMessage('success', 'Work order created successfully.');
                redirect('work_order_detail.php?woid=' . $woid);
            } else {
                $error = 'Failed to create work order.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Work Order - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">New Work Order</h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="work_orders.php">Back to List</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h3>Find Existing Vehicle</h3>
            <form method="POST" class="form-row">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="search">
                <div class="form-group" style="flex: 1;">
                    <label>Search Type</label>
                    <select name="search_type">
                        <option value="Plate" <?php echo $searchType === 'Plate' ? 'selected' : ''; ?>>Plate</option>
                        <option value="VIN" <?php echo $searchType === 'VIN' ? 'selected' : ''; ?>>VIN</option>
                    </select>
                </div>
                <div class="form-group" style="flex: 2;">
                    <label>Search Value</label>
                    <input type="text" name="search_query" value="<?php echo e($searchQuery); ?>">
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="../../public/intake.php" class="btn" target="_blank" rel="noopener">Registration</a>
                </div>
            </form>
        </div>

        <?php if (!empty($results)): ?>
            <div class="form-container" style="margin-top: 20px;">
                <h3>Search Results</h3>
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>Plate</th>
                            <th>VIN</th>
                            <th>Vehicle</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                            <tr>
                                <td><?php echo e($row['Plate']); ?></td>
                                <td><?php echo e($row['VIN']); ?></td>
                                <td><?php echo e(trim($row['Year'] . ' ' . $row['Make'] . ' ' . $row['Model'])); ?></td>
                                <td><?php echo e(getFullName($row['FirstName'], $row['LastName'])); ?></td>
                                <td><?php echo e($row['Phone']); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <?php csrfField(); ?>
                                        <input type="hidden" name="action" value="create_work_order">
                                        <input type="hidden" name="cvid" value="<?php echo e($row['CVID']); ?>">
                                        <input type="hidden" name="customer_id" value="<?php echo e($row['CustomerID']); ?>">
                                        <input type="hidden" name="mileage" value="<?php echo e($row['Mileage'] ?? ''); ?>">
                                        <input type="text" name="work_required" placeholder="Work Required (optional)" style="width: 220px;">
                                        <button type="submit" class="btn btn-success">Create WO</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($searchQuery !== ''): ?>
            <div class="alert alert-warning" style="margin-top: 20px;">
                No vehicles found. Use Registration to add a new vehicle.
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
