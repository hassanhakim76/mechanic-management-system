<?php
/**
 * Admin - Work Orders List
 * Main screen for administrators
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$woModel = new WorkOrder();

// Get filters from query string
$filters = [
    'hide_completed' => get('hide_completed', '1') == '1' ? 1 : 0,
    'status' => get('status', 'All'),
    'search_operator' => get('search_operator', 'Contain'),
    'search_value' => get('search_value', ''),
    'limit' => 100
];
$activeFindValue = trim((string)$filters['search_value']);
$isWorkOrderNumberFind = false;
if ($activeFindValue !== '') {
    $workOrderFindCompact = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $activeFindValue));
    $workOrderPrefixCompact = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', WO_PREFIX));
    $isWorkOrderNumberFind = (bool)preg_match('/^\d{3,}$/', $workOrderFindCompact);
    if (!$isWorkOrderNumberFind && $workOrderPrefixCompact !== '' && strpos($workOrderFindCompact, $workOrderPrefixCompact) === 0) {
        $workOrderDigits = substr($workOrderFindCompact, strlen($workOrderPrefixCompact));
        $isWorkOrderNumberFind = (bool)preg_match('/^\d{3,}$/', $workOrderDigits);
    }
}
$queryFilters = $filters;
if ($isWorkOrderNumberFind) {
    $queryFilters['hide_completed'] = 0;
}

// Get work orders
$workOrders = $woModel->getList($queryFilters);
$statusCounts = $woModel->getStatusCounts($queryFilters);
$clearFindUrl = 'work_orders.php?' . http_build_query([
    'hide_completed' => $filters['hide_completed'] ? '1' : '0',
    'status' => $filters['status']
]);

$pageTitle = 'Work Orders Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
    <body>
    <!-- Header -->
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo $pageTitle; ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?> (<?php echo e(Session::getUserRoleName()); ?>)</span>
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>


    <div class="container-fluid">
        <!-- Toolbar -->
        <div class="toolbar toolbar-work-orders">
            <div class="toolbar-work-orders__intro">
                <span class="toolbar-work-orders__eyebrow">Quick Actions</span>
                <strong class="toolbar-work-orders__title">Work Order Control Center</strong>
                <span class="toolbar-work-orders__subtitle">Search, create, and route intake activity from one place.</span>
            </div>

            <div class="toolbar-work-orders__actions">
                <button type="button" class="toolbar-action toolbar-action-primary" onclick="location.href='find.php'">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M10.5 4.5a6 6 0 1 0 0 12a6 6 0 0 0 0-12zm0-2a8 8 0 1 1 4.9 14.3l4.1 4.1l-1.4 1.4l-4.1-4.1A8 8 0 0 1 10.5 2.5z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Find</span>
                    <span class="toolbar-action__hint">Search all records</span>
                </button>
                <button type="button" class="toolbar-action toolbar-action-success" onclick="location.href='work_order_new.php'">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M11 4h2v6h6v2h-6v6h-2v-6H5v-2h6V4z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">New</span>
                    <span class="toolbar-action__hint">Create a work order</span>
                </button>
                <button type="button" class="toolbar-action" onclick="window.open('../../public/intake.php', '_blank')">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 3h8l4 4v14H7V3zm8 1.5V8h3.5L15 4.5zM9 11h8v2H9v-2zm0 4h8v2H9v-2zm0-8h4v2H9V7z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Registration</span>
                    <span class="toolbar-action__hint">Open intake in a new tab</span>
                </button>
	                <button type="button" class="toolbar-action toolbar-action-alert" onclick="location.href='../intake/review_queue.php'">
	                    <span class="toolbar-action__icon" aria-hidden="true">
	                        <svg viewBox="0 0 24 24" focusable="false">
	                            <path d="M4 5h16v2H4V5zm0 6h16v2H4v-2zm0 6h10v2H4v-2zm12-1l4 3v-8l-4 3v2z"></path>
	                        </svg>
                    </span>
	                    <span class="toolbar-action__label">Draft Queue</span>
	                    <span class="toolbar-action__hint">Review pending drafts</span>
	                </button>
			                <button type="button" class="toolbar-action toolbar-action-muted" onclick="alert('Appointment feature coming soon')">
	                    <span class="toolbar-action__icon" aria-hidden="true">
	                        <svg viewBox="0 0 24 24" focusable="false">
	                            <path d="M7 2h2v2h6V2h2v2h3v18H4V4h3V2zm11 8H6v10h12V10zM6 8h12V6H6v2zm6 3h1.5v4H10v-1.5h2V11z"></path>
                        </svg>
                    </span>
	                    <span class="toolbar-action__label">Appointment</span>
	                    <span class="toolbar-action__hint">Feature coming soon</span>
	                </button>
			                <button type="button" class="toolbar-action" onclick="location.href='settings.php'">
			                    <span class="toolbar-action__icon" aria-hidden="true">
			                        <svg viewBox="0 0 24 24" focusable="false">
			                            <path d="M19.4 13.5c.1-.5.1-1 .1-1.5s0-1-.1-1.5l2-1.5l-2-3.5l-2.4 1a7.8 7.8 0 0 0-2.6-1.5L14 2h-4l-.4 3a7.8 7.8 0 0 0-2.6 1.5l-2.4-1l-2 3.5l2 1.5c-.1.5-.1 1-.1 1.5s0 1 .1 1.5l-2 1.5l2 3.5l2.4-1a7.8 7.8 0 0 0 2.6 1.5l.4 3h4l.4-3a7.8 7.8 0 0 0 2.6-1.5l2.4 1l2-3.5l-2-1.5zM12 15.5A3.5 3.5 0 1 1 12 8a3.5 3.5 0 0 1 0 7.5z"></path>
			                        </svg>
			                    </span>
			                    <span class="toolbar-action__label">Settings</span>
			                    <span class="toolbar-action__hint">Open settings</span>
			                </button>
            </div>
	        </div>

	        <?php if ($activeFindValue !== ''): ?>
	            <div class="active-find-summary">
	                <div>
	                    <strong>Find active:</strong>
	                    <span><?php echo e($filters['search_operator']); ?> "<?php echo e($activeFindValue); ?>"</span>
	                    <?php if ($isWorkOrderNumberFind): ?>
	                        <span class="active-find-summary__note">completed records included</span>
	                    <?php endif; ?>
	                </div>
	                <a class="active-find-summary__clear" href="<?php echo e($clearFindUrl); ?>">Clear Find</a>
	            </div>
	        <?php endif; ?>
	
	        <!-- Filter Controls -->
	        <div class="filter-controls filter-controls-work-orders">
            <form method="GET" id="filterForm">


                <div class="filter-controls-work-orders__body">
                    <div class="filter-chip-group">
                        <input type="hidden" name="hide_completed" value="0">
                        <label class="filter-chip filter-chip-toggle">
                            <input type="checkbox" name="hide_completed" value="1" 
                                   <?php echo $filters['hide_completed'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>Hide Completed</span>
                        </label>
	                    </div>

<div style="display: inline-block; width: 300px;" aria-hidden="true"></div>
	
	                    <div class="filter-status-group">
	                        <span class="filter-status-group__label">Status</span>

                        <label class="filter-status-pill">
                            <input type="radio" name="status" value="All" 
                                   <?php echo ($filters['status'] == 'All') ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>All <strong class="filter-status-pill__count"><?php echo (int)($statusCounts['All'] ?? 0); ?></strong></span>
                        </label>
                        <label class="filter-status-pill">
                            <input type="radio" name="status" value="NEW" 
                                   <?php echo ($filters['status'] == 'NEW') ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>New <strong class="filter-status-pill__count"><?php echo (int)($statusCounts[STATUS_NEW] ?? 0); ?></strong></span>
                        </label>
                        <label class="filter-status-pill">
                            <input type="radio" name="status" value="PENDING" 
                                   <?php echo ($filters['status'] == 'PENDING') ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>Pending <strong class="filter-status-pill__count"><?php echo (int)($statusCounts[STATUS_PENDING] ?? 0); ?></strong></span>
                        </label>
                        <label class="filter-status-pill">
                            <input type="radio" name="status" value="BILLING" 
                                   <?php echo ($filters['status'] == 'BILLING') ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>Billing <strong class="filter-status-pill__count"><?php echo (int)($statusCounts[STATUS_BILLING] ?? 0); ?></strong></span>
                        </label>
                        <label class="filter-status-pill">
                            <input type="radio" name="status" value="<?php echo e(STATUS_ONHOLD); ?>" 
                                   <?php echo ($filters['status'] == STATUS_ONHOLD) ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                            <span>On-Hold <strong class="filter-status-pill__count"><?php echo (int)($statusCounts[STATUS_ONHOLD] ?? 0); ?></strong></span>
                        </label>
                    </div>
                </div>
                                <div class="filter-controls-work-orders__header">

                    <span class="filter-controls-work-orders__meta">            Total Work Orders: <?php echo count($workOrders); ?></span>
                </div>
            </form>
        </div>

        <!-- Work Orders Grid -->
        <div class="table-scroll">
            <table class="data-grid work-orders-grid">
                <thead>
                    <tr>
                        <th>Plate</th>
                        <th>Make</th>
                        <th>Model</th>
                        <th>Color</th>
                        <th>Year</th>
                        <th>Date</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Cell</th>
                        <th>Mileage</th>
                        <th>Status</th>
                        <th>Work Required</th>
                        <th>Customer Note</th>
                        <th>Admin Note</th>
                        <th>Shop Note</th>
                        <th>VIN</th>
                        <th>Email</th>
                        <th>Priority</th>
                        <th>Admin</th>
                        <th>Mechanic</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($workOrders)): ?>
                        <tr>
                            <td colspan="20" style="text-align: center; padding: 20px;">
                                No work orders found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($workOrders as $wo): ?>
                            <tr data-woid="<?php echo $wo['WOID']; ?>" 
                                data-status="<?php echo e($wo['WO_Status']); ?>">
                                <td><?php echo e($wo['Plate']); ?></td>
                                <td><?php echo e($wo['Make']); ?></td>
                                <td><?php echo e($wo['Model']); ?></td>
                                <td><?php echo e($wo['Color']); ?></td>
                                <td><?php echo e($wo['Year']); ?></td>
                                <td><?php echo formatDateTime($wo['WO_Date']); ?></td>
                                <td><?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></td>
                                <td><?php echo e($wo['Phone']); ?></td>
                                <td><?php echo e($wo['Cell']); ?></td>
                                <td><?php echo formatMileage($wo['Mileage']); ?></td>
                                <td><?php echo statusBadge($wo['WO_Status']); ?></td>
                                <td><?php echo e(combineWorkItems($wo)); ?></td>
                                <td><?php echo e($wo['Customer_Note']); ?></td>
                                <td><?php echo e(substr($wo['Admin_Note'] ?? '', 0, 50)); ?></td>
                                <td><?php echo e(substr($wo['Mechanic_Note'] ?? '', 0, 50)); ?></td>
                                <td><?php echo e($wo['VIN']); ?></td>
                                <td><?php echo e($wo['Email']); ?></td>
                                <td><?php echo priorityBadge($wo['Priority']); ?></td>
                                <td><?php echo e($wo['Admin']); ?></td>
                                <td><?php echo e($wo['Mechanic']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div style="margin-top: 10px; color: #666;">
            Total Work Orders: <?php echo count($workOrders); ?>
        </div>
    </div>

    <!-- Search Modal -->
    <div id="searchModal" class="modal">
        <div class="modal-dialog">
            <div class="modal-header">
                Search Work Order
                <button style="float: right; background: none; border: none; color: white; font-size: 20px; cursor: pointer;" 
                        data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form method="GET">
                    <div class="form-group">
                        <label>Operator</label>
                        <select name="search_operator" id="search_operator">
                            <option value="Contain" <?php echo $filters['search_operator'] === 'Contain' ? 'selected' : ''; ?>>Contain</option>
                            <option value="Equal" <?php echo $filters['search_operator'] === 'Equal' ? 'selected' : ''; ?>>Equal</option>
                            <option value="Start With" <?php echo $filters['search_operator'] === 'Start With' ? 'selected' : ''; ?>>Start With</option>
                            <option value="End With" <?php echo $filters['search_operator'] === 'End With' ? 'selected' : ''; ?>>End With</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Search</label>
	                        <input type="text" name="search_value" id="search_value" placeholder="Work order, WOID, customer, phone, VIN, plate, vehicle, color, note, mechanic" value="<?php echo e($filters['search_value']); ?>">
	                        <div class="help-text">Search accepts formats like PREC-007109, 7109, 613-276-5205, VIN, plate, customer name, vehicle details, notes, or mechanic.</div>
	                    </div>

                    <input type="hidden" name="hide_completed" value="<?php echo $filters['hide_completed'] ? '1' : '0'; ?>">
                    <input type="hidden" name="status" value="<?php echo e($filters['status']); ?>">
                    
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="button" class="btn" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../../public/js/main.js"></script>
</body>
</html>
