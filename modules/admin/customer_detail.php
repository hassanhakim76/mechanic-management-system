<?php
/**
 * Admin/FrontDesk - Customer Detail (Real Customer Edit)
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin() && !Session::isFrontDesk()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$customerModel = new Customer();
$customerId = (int)get('id', (int)get('customer_id', 0));
$error = '';
$customer = null;
$vehicles = [];
$workOrders = [];

if ($customerId <= 0) {
    $error = 'Customer ID is required.';
} else {
    $customer = $customerModel->getById($customerId);
    if (!$customer) {
        $error = 'Customer not found.';
    }
}

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $error = 'Invalid CSRF token.';
    } elseif ($customer && $customerId > 0) {
        $data = [
            'FirstName' => post('FirstName', ''),
            'LastName' => post('LastName', ''),
            'Phone' => post('Phone', ''),
            'Cell' => post('Cell', ''),
            'Email' => post('Email', ''),
            'Address' => post('Address', ''),
            'City' => post('City', ''),
            'Province' => post('Province', ''),
            'PostalCode' => post('PostalCode', ''),
            'PhoneExt' => post('PhoneExt', ''),
            'subscribe' => post('subscribe')
        ];

        if ($customerModel->update($customerId, $data)) {
            Session::setFlashMessage('success', 'Customer updated successfully.');
            redirect('customer_detail.php?id=' . $customerId);
        } else {
            $error = 'Failed to update customer.';
        }
    }
}

if ($customerId > 0) {
    $customer = $customerModel->getById($customerId);
    if ($customer) {
        $vehicles = $customerModel->getVehicles($customerId);
        $workOrders = $customerModel->getWorkOrders($customerId, 30);
    }
}

$pageTitle = 'Customer : ' . $customer['FirstName'].'  '.$customer['LastName'];
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
            <a href="work_orders.php">Work Orders</a> |
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <?php if ($customer): ?>
            <form method="POST" class="form-container">
                <?php csrfField(); ?>

                <h3>Customer Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Customer ID</label>
                        <input type="text" value="<?php echo (int)$customer['CustomerID']; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="FirstName" value="<?php echo e($customer['FirstName']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="LastName" value="<?php echo e($customer['LastName']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="Phone" value="<?php echo e($customer['Phone']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Cell</label>
                        <input type="text" name="Cell" value="<?php echo e($customer['Cell']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone Ext</label>
                        <input type="text" name="PhoneExt" value="<?php echo e($customer['PhoneExt']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="Email" value="<?php echo e($customer['Email']); ?>">
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label>Address</label>
                        <input type="text" name="Address" value="<?php echo e($customer['Address']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="City" value="<?php echo e($customer['City']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Province</label>
                        <input type="text" name="Province" value="<?php echo e($customer['Province']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="PostalCode" value="<?php echo e($customer['PostalCode']); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="subscribe" value="1" <?php echo !empty($customer['subscribe']) ? 'checked' : ''; ?>>
                            Subscribe to updates
                        </label>
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn btn-success">Save Customer</button>
                </div>
            </form>

            <div class="form-container" style="margin-top: 14px;">
                <h3>Vehicles</h3>
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>CVID</th>
                            <th>Plate</th>
                            <th>VIN</th>
                            <th>Vehicle</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vehicles)): ?>
                            <tr><td colspan="5" style="text-align:center; padding:10px;">No vehicles found</td></tr>
                        <?php else: ?>
                            <?php foreach ($vehicles as $v): ?>
                                <tr>
                                    <td><?php echo (int)$v['CVID']; ?></td>
                                    <td><?php echo e($v['Plate']); ?></td>
                                    <td><?php echo e($v['VIN']); ?></td>
                                    <td><?php echo e(trim($v['Year'] . ' ' . $v['Make'] . ' ' . $v['Model'])); ?></td>
                                    <td><?php echo e($v['Status']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-container" style="margin-top: 14px;">
                <h3>Recent Work Orders</h3>
                <table class="data-grid">
                    <thead>
                        <tr>
                            <th>WO#</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Plate</th>
                            <th>Vehicle</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($workOrders)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:10px;">No work orders found</td></tr>
                        <?php else: ?>
                            <?php foreach ($workOrders as $wo): ?>
                                <tr>
                                    <td><?php echo generateWONumber($wo['WOID']); ?></td>
                                    <td><?php echo formatDateTime($wo['WO_Date']); ?></td>
                                    <td><?php echo e($wo['WO_Status']); ?></td>
                                    <td><?php echo e($wo['Plate']); ?></td>
                                    <td><?php echo e(trim($wo['Year'] . ' ' . $wo['Make'] . ' ' . $wo['Model'])); ?></td>
                                    <td><a class="btn" href="work_order_detail.php?woid=<?php echo (int)$wo['WOID']; ?>">Open</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
