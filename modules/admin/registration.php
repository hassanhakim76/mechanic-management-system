<?php
/**
 * Admin - Registration
 * Search vehicle, or register new customer/vehicle and create work order
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$vehicleModel = new Vehicle();
$customerModel = new Customer();
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

    if ($action === 'create_work_order_existing') {
        $cvid = (int)post('cvid');
        $customerId = (int)post('customer_id');
        $mileage = trim(post('mileage', ''));

        if ($cvid <= 0 || $customerId <= 0) {
            $error = 'Vehicle selection is required.';
        } else {
            $woid = $workOrderModel->create([
                'CustomerID' => $customerId,
                'CVID' => $cvid,
                'Mileage' => $mileage,
                'WO_Status' => STATUS_NEW,
                'Priority' => PRIORITY_NORMAL,
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

    if ($action === 'register_new') {
        $plate = trim(post('plate', ''));
        $make = trim(post('make', ''));
        $model = trim(post('model', ''));

        if ($plate === '' || $make === '' || $model === '') {
            $error = 'Plate, Make, and Model are required.';
        } else {
            $hasCustomerInfo = trim(post('first_name', '')) !== ''
                || trim(post('last_name', '')) !== ''
                || trim(post('phone', '')) !== ''
                || trim(post('cell', '')) !== ''
                || trim(post('email', '')) !== '';

            if ($hasCustomerInfo) {
                $customerId = $customerModel->create([
                    'FirstName' => post('first_name', ''),
                    'LastName' => post('last_name', ''),
                    'Phone' => post('phone', ''),
                    'Cell' => post('cell', ''),
                    'Email' => post('email', ''),
                    'Address' => post('address', ''),
                    'City' => post('city', ''),
                    'Province' => post('province', ''),
                    'PostalCode' => post('postal_code', ''),
                    'PhoneExt' => post('phone_ext', '')
                ]);
            } else {
                $customerId = SYSTEM_CUSTOMER_ID;
            }

            if (!$customerId) {
                $error = 'Failed to create or link customer.';
            } else {
                $cvid = $vehicleModel->create([
                    'CustomerID' => $customerId,
                    'Plate' => $plate,
                    'VIN' => post('vin', ''),
                    'Make' => $make,
                    'Model' => $model,
                    'Year' => post('year', ''),
                    'Color' => post('color', ''),
                    'Engine' => post('engine', ''),
                    'Detail' => post('detail', '')
                ]);

                if (!$cvid) {
                    $error = 'Failed to create vehicle.';
                } else {
                    $workItems = splitWorkItems(post('work_required', ''));

                    $woid = $workOrderModel->create([
                        'CustomerID' => $customerId,
                        'CVID' => $cvid,
                        'Mileage' => post('mileage', ''),
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
                        Session::setFlashMessage('success', 'Registration completed. Work order created.');
                        redirect('work_order_detail.php?woid=' . $woid);
                    } else {
                        $error = 'Failed to create work order.';
                    }
                }
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
    <title>Registration - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Registration</h1>
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
            <h3>Search Vehicle</h3>
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
                </div>
            </form>
        </div>

        <?php if (!empty($results)): ?>
            <div class="form-container" style="margin-top: 20px;">
                <h3>Existing Vehicles</h3>
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
                                        <input type="hidden" name="action" value="create_work_order_existing">
                                        <input type="hidden" name="cvid" value="<?php echo e($row['CVID']); ?>">
                                        <input type="hidden" name="customer_id" value="<?php echo e($row['CustomerID']); ?>">
                                        <input type="hidden" name="mileage" value="<?php echo e($row['Mileage'] ?? ''); ?>">
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
                No vehicles found. Use the form below to register a new customer and vehicle.
            </div>
        <?php endif; ?>

        <div class="form-container" style="margin-top: 20px;">
            <h3>New Registration</h3>
            <form method="POST">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="register_new">

                <div class="form-row">
                    <div class="form-group">
                        <label>Plate *</label>
                        <input type="text" name="plate" required>
                    </div>
                    <div class="form-group">
                        <label>VIN</label>
                        <input type="text" name="vin">
                    </div>
                    <div class="form-group">
                        <label>Year</label>
                        <input type="text" name="year">
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="text" name="color">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Make *</label>
                        <input type="text" name="make" required>
                    </div>
                    <div class="form-group">
                        <label>Model *</label>
                        <input type="text" name="model" required>
                    </div>
                    <div class="form-group">
                        <label>Engine</label>
                        <input type="text" name="engine">
                    </div>
                    <div class="form-group">
                        <label>Detail</label>
                        <input type="text" name="detail">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Mileage</label>
                        <input type="text" name="mileage">
                    </div>
                    <div class="form-group" style="flex: 2;">
                        <label>Work Required</label>
                        <input type="text" name="work_required" placeholder="Oil change, brakes, etc.">
                    </div>
                </div>

                <hr>

                <h4>Customer Information (Optional)</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone">
                    </div>
                    <div class="form-group">
                        <label>Cell</label>
                        <input type="text" name="cell">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Phone Ext</label>
                        <input type="text" name="phone_ext">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Address</label>
                        <input type="text" name="address">
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city">
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" name="province">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 10px;">
                    <button type="submit" class="btn btn-success">Register & Create Work Order</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
