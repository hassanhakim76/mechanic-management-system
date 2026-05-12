<?php
/**
 * Mechanic - Work Orders Dashboard
 * Split-Grid View:
 * 1. Top: NEW / Unassigned (Available work)
 * 2. Bottom: PENDING / Assigned to Me (My active work)
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

// Redirect if not mechanic (optional, or just allow admin too)
if (!Session::isMechanic() && !Session::isAdmin()) {
    redirect(BASE_URL . '/index.php');
}

$woModel = new WorkOrder();
$currentUser = Session::getUsername();

// Fetch filtered work orders
// If Admin is viewing, they see their own assigned or all?
// For mechanic module, we assume we show work for the logged-in user.
$mechanicName = Session::isMechanic() ? $currentUser : ''; 

// The model method getMechanicWorkOrders handles the split logic
$workOrders = $woModel->getMechanicWorkOrders($mechanicName);

$latestVisibleWoid = 0;
$visibleWorkOrders = array_merge($workOrders['new'], $workOrders['pending']);
foreach ($visibleWorkOrders as $visibleWo) {
    $latestVisibleWoid = max($latestVisibleWoid, (int)$visibleWo['WOID']);
}

$lastSeenNewWoid = (int)Session::get('mechanic_last_seen_new_woid', 0);
if ($lastSeenNewWoid <= 0) {
    // First dashboard visit baseline: avoid flagging historical backlog as "new".
    $lastSeenNewWoid = $latestVisibleWoid;
    Session::set('mechanic_last_seen_new_woid', $lastSeenNewWoid);
}

if (get('ack_new', '') === '1') {
    $acknowledgedCount = 0;
    foreach ($visibleWorkOrders as $visibleWo) {
        if ((int)$visibleWo['WOID'] > $lastSeenNewWoid) {
            $acknowledgedCount++;
        }
    }

    Session::set('mechanic_last_seen_new_woid', $latestVisibleWoid);
    if ($acknowledgedCount > 0) {
        Session::setFlashMessage('success', 'Acknowledged ' . $acknowledgedCount . ' new work order(s).');
    } else {
        Session::setFlashMessage('info', 'There were no new work orders to acknowledge.');
    }
    redirect('work_orders.php');
}

$newLiveCount = 0;
$newUnassignedCount = 0;
foreach ($visibleWorkOrders as $visibleWo) {
    if ((int)$visibleWo['WOID'] > $lastSeenNewWoid) {
        $newLiveCount++;
    }
}
foreach ($workOrders['new'] as $newWo) {
    if ((int)$newWo['WOID'] > $lastSeenNewWoid) {
        $newUnassignedCount++;
    }
}
$newQueueCount = count($workOrders['new']);
$pendingQueueCount = count($workOrders['pending']);

// Helper for status badge
function getStatusBadge($status) {
    $class = 'secondary';
    switch($status) {
        case STATUS_NEW: $class = 'info'; break;
        case STATUS_PENDING: $class = 'warning'; break;
        case STATUS_BILLING: $class = 'success'; break;
        case STATUS_COMPLETED: $class = 'secondary'; break;
    }
    return '<span class="badge badge-'.$class.'">'.$status.'</span>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanic Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <meta http-equiv="refresh" content="60"> <!-- Auto Refresh every 60s -->
</head>
<body class="mechanic-dashboard-page">
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Mechanic Dashboard</h1>
        <div class="user-info">
            <span>Mechanic: <?php echo e($currentUser); ?></span>
            <a href="../../public/logout.php" style="margin-left: 15px; color: white;">Logout</a>
        </div>
    </div>
    
    <div class="container-fluid">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo $flash['type']; ?>"><?php echo $flash['message']; ?></div>
        <?php endif; ?>

        <div class="toolbar toolbar-work-orders mechanic-dashboard-toolbar">
            <div class="toolbar-work-orders__intro">
                <strong class="toolbar-work-orders__title">Daily Workbench</strong>
                <span class="toolbar-work-orders__subtitle">Track fresh arrivals and monitor your assigned jobs.</span>
            </div>

            <div class="toolbar-work-orders__actions">
                <button type="button" class="toolbar-action" onclick="window.location.reload()">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 5a7 7 0 0 1 6.3 4H16v2h6V5h-2v2.2A9 9 0 1 0 21 12h-2a7 7 0 1 1-7-7z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Refresh</span>
                    <span class="toolbar-action__hint">Reload both queues</span>
                </button>

                <button type="button" class="toolbar-action toolbar-action-primary mechanic-toolbar-action-highlight" onclick="window.location.href='work_orders.php?ack_new=1'">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.9V19h-2v-2.1A6.5 6.5 0 0 1 5.1 11H3v-2h2.1A6.5 6.5 0 0 1 11 3.1V1h2v2.1A6.5 6.5 0 0 1 18.9 9H21v2h-2.1A6.5 6.5 0 0 1 13 16.9z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Acknowledge New</span>
                    <span class="toolbar-action__hint">Reset the live new counter</span>
                    <?php if ($newLiveCount > 0): ?>
                        <span class="mechanic-toolbar-action__badge"><?php echo (int)$newLiveCount; ?></span>
                    <?php endif; ?>
                </button>

                <button type="button" class="toolbar-action" onclick="alert('Not implemented yet')">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M13 3a9 9 0 1 0 8.95 10h-2.02A7 7 0 1 1 13 5v4l5-5l-5-5v4z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">History</span>
                    <span class="toolbar-action__hint">Open repair history tools</span>
                </button>

                <button type="button" class="toolbar-action toolbar-action-success" onclick="window.open('../../public/intake.php', '_blank')">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 3h8l4 4v14H7V3zm8 1.5V8h3.5L15 4.5zM9 11h8v2H9v-2zm0 4h8v2H9v-2zm0-8h4v2H9V7z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Registration</span>
                    <span class="toolbar-action__hint">Open intake in a new tab</span>
                </button>

                <button type="button" class="toolbar-action toolbar-action-alert" onclick="window.location.href='../intake/review_queue.php'">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M4 5h16v2H4V5zm0 6h16v2H4v-2zm0 6h10v2H4v-2zm12-1l4 3v-8l-4 3v2z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Draft Queue</span>
                    <span class="toolbar-action__hint">Review pending drafts</span>
                </button>

                <button type="button" class="toolbar-action toolbar-action-muted" onclick="alert('Not implemented yet')">
                    <span class="toolbar-action__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M7 2h2v2h6V2h2v2h3v18H4V4h3V2zm11 8H6v10h12V10zM6 8h12V6H6v2zm6 3h1.5v4H10v-1.5h2V11z"></path>
                        </svg>
                    </span>
                    <span class="toolbar-action__label">Appointment</span>
                    <span class="toolbar-action__hint">Feature coming soon</span>
                </button>
            </div>
        </div>

        <div class="filter-controls filter-controls-work-orders mechanic-dashboard-summary">
            <div class="mechanic-dashboard-summary__body">
                <div class="mechanic-dashboard-metric">
                    <span class="mechanic-dashboard-metric__label">Live New Since Ack</span>
                    <strong class="mechanic-dashboard-metric__value"><?php echo (int)$newLiveCount; ?></strong>
                </div>
                <div class="mechanic-dashboard-metric">
                    <span class="mechanic-dashboard-metric__label">Unassigned Queue</span>
                    <strong class="mechanic-dashboard-metric__value"><?php echo (int)$newQueueCount; ?></strong>
                </div>
                <div class="mechanic-dashboard-metric">
                    <span class="mechanic-dashboard-metric__label">My Pending Jobs</span>
                    <strong class="mechanic-dashboard-metric__value"><?php echo (int)$pendingQueueCount; ?></strong>
                </div>
                <div class="mechanic-dashboard-metric mechanic-dashboard-metric--small">
                    <span class="mechanic-dashboard-metric__label">Auto Refresh</span>
                    <strong class="mechanic-dashboard-metric__value">60s</strong>
                </div>
            </div>
        </div>

        <?php if ($newLiveCount > 0): ?>
            <div class="alert alert-warning mechanic-live-banner">
                <?php echo (int)$newLiveCount; ?> new live work order(s) since your last acknowledgement.
            </div>
        <?php endif; ?>

        <div class="split-grid-container mechanic-queue-layout">
            
            <!-- Top Grid: NEW / Unassigned -->
            <section class="grid-section mechanic-queue-panel mechanic-queue-panel--new">
                <div class="mechanic-queue-panel__header">
                    <div class="mechanic-queue-panel__title-group">
                        <h2 class="mechanic-queue-panel__title">New Work Orders</h2>
                        <span class="mechanic-queue-panel__subtitle">Unassigned jobs waiting for pickup.</span>
                    </div>
                    <div class="mechanic-queue-panel__meta">
                        <span class="mechanic-queue-pill"><?php echo (int)$newQueueCount; ?> Total</span>
                        <?php if ($newUnassignedCount > 0): ?>
                            <span class="mechanic-queue-pill mechanic-queue-pill--alert">+<?php echo (int)$newUnassignedCount; ?> New</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-scroll mechanic-queue-panel__table">
                    <table class="data-grid mechanic-queue-table mechanic-queue-table--new">
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Year</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Work Required</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($workOrders['new'])): ?>
                                <tr><td colspan="9" class="text-center text-muted mechanic-queue-empty">No new work orders available.</td></tr>
                            <?php else: ?>
                                <?php foreach ($workOrders['new'] as $wo): ?>
                                <?php $isLiveNew = (int)$wo['WOID'] > $lastSeenNewWoid; ?>
                                <tr class="mechanic-queue-row"
                                    data-woid="<?php echo (int)$wo['WOID']; ?>"
                                    data-status="<?php echo e($wo['WO_Status']); ?>"
                                    data-priority="<?php echo e(strtoupper((string)($wo['Priority'] ?? PRIORITY_NORMAL))); ?>"
                                    data-live-new="<?php echo $isLiveNew ? '1' : '0'; ?>"
                                    data-open-on-tap="1"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Open work order <?php echo (int)$wo['WOID']; ?>">
                                    <td><?php echo e($wo['Plate']); ?></td>
                                    <td><?php echo e($wo['Make']); ?></td>
                                    <td><?php echo e($wo['Model']); ?></td>
                                    <td><?php echo e($wo['Year']); ?></td>
                                    <td><?php echo formatDateTime($wo['WO_Date']); ?></td>
                                    <td><?php echo e($wo['FirstName'] . ' ' . $wo['LastName']); ?></td>
                                    <td><?php echo e($wo['Phone']); ?></td>
                                    <td><?php echo e(substr($wo['WO_Req1'] . ' ' . $wo['WO_Req2'], 0, 50)); ?>...</td>
                                    <td><?php echo getStatusBadge($wo['WO_Status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Bottom Grid: PENDING / My Assignments -->
            <section class="grid-section mechanic-queue-panel mechanic-queue-panel--pending">
                <div class="mechanic-queue-panel__header">
                    <div class="mechanic-queue-panel__title-group">
                        <h2 class="mechanic-queue-panel__title">My Pending Jobs</h2>
                        <span class="mechanic-queue-panel__subtitle">Assigned to <?php echo e($currentUser); ?>.</span>
                    </div>
                    <div class="mechanic-queue-panel__meta">
                        <span class="mechanic-queue-pill mechanic-queue-pill--pending"><?php echo (int)$pendingQueueCount; ?> Active</span>
                    </div>
                </div>
                <div class="table-scroll mechanic-queue-panel__table">
                    <table class="data-grid mechanic-queue-table mechanic-queue-table--pending">
                        <thead>
                            <tr>
                                <th>Plate</th>
                                <th>Make</th>
                                <th>Model</th>
                                <th>Year</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Phone</th>
                                <th>Work Required</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($workOrders['pending'])): ?>
                                <tr><td colspan="9" class="text-center text-muted mechanic-queue-empty">No pending jobs assigned to you.</td></tr>
                            <?php else: ?>
                                <?php foreach ($workOrders['pending'] as $wo): ?>
                                <?php $isLiveNew = (int)$wo['WOID'] > $lastSeenNewWoid; ?>
                                <tr class="mechanic-queue-row"
                                    data-woid="<?php echo (int)$wo['WOID']; ?>"
                                    data-status="<?php echo e($wo['WO_Status']); ?>"
                                    data-priority="<?php echo e(strtoupper((string)($wo['Priority'] ?? PRIORITY_NORMAL))); ?>"
                                    data-live-new="<?php echo $isLiveNew ? '1' : '0'; ?>"
                                    data-open-on-tap="1"
                                    tabindex="0"
                                    role="button"
                                    aria-label="Open work order <?php echo (int)$wo['WOID']; ?>">
                                    <td><?php echo e($wo['Plate']); ?></td>
                                    <td><?php echo e($wo['Make']); ?></td>
                                    <td><?php echo e($wo['Model']); ?></td>
                                    <td><?php echo e($wo['Year']); ?></td>
                                    <td><?php echo formatDateTime($wo['WO_Date']); ?></td>
                                    <td><?php echo e($wo['FirstName'] . ' ' . $wo['LastName']); ?></td>
                                    <td><?php echo e($wo['Phone']); ?></td>
                                    <td><?php echo e(substr($wo['WO_Req1'] . ' ' . $wo['WO_Req2'], 0, 50)); ?>...</td>
                                    <td><?php echo getStatusBadge($wo['WO_Status']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

	        </div>
	    </div>
    <script src="../../public/js/main.js"></script>
</body>
</html>
