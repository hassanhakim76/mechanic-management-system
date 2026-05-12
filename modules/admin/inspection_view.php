<?php
/**
 * Admin - Multi-Point Inspection View
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$woid = (int)get('woid', 0);
if ($woid <= 0) {
    Session::setFlashMessage('error', 'Invalid work order.');
    redirect('work_orders.php');
}

$woModel = new WorkOrder();
$inspectionModel = new VehicleInspection();
$wo = $woModel->getById($woid);
if (!$wo) {
    Session::setFlashMessage('error', 'Work order not found.');
    redirect('work_orders.php');
}

$inspection = $inspectionModel->getByWorkOrder($woid);
$normalizedWorkOrderStatus = strtoupper(trim((string)($wo['WO_Status'] ?? '')));
$isPendingWorkOrder = $normalizedWorkOrderStatus === strtoupper(STATUS_PENDING);

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $error = 'Security token expired. Refresh and try again.';
    } elseif (post('action') === 'start_inspection' && !$inspection) {
        if (!$isPendingWorkOrder) {
            $error = 'Inspection can only be started when the work order status is PENDING. Current status: ' . ($wo['WO_Status'] ?? 'Unknown') . '.';
        } elseif ($inspectionModel->getOrCreateForWorkOrder($woid)) {
            Session::setFlashMessage('success', 'Inspection started.');
            redirect('inspection_view.php?woid=' . $woid);
        } else {
            $error = $inspectionModel->getLastError() ?: 'Unable to start inspection.';
        }
    } elseif ($inspection && post('action') === 'reopen') {
        if (!$isPendingWorkOrder) {
            $error = 'Completed inspections can only be reopened while the work order is PENDING.';
        } elseif ($inspectionModel->reopen((int)$inspection['inspection_id'])) {
            Session::setFlashMessage('success', 'Inspection reopened.');
            redirect('inspection_view.php?woid=' . $woid);
        } else {
            $error = 'Unable to reopen inspection.';
        }
    }
    $inspection = $inspectionModel->getByWorkOrder($woid);
}

$itemsByCategory = $inspection ? $inspectionModel->getItemsByCategory((int)$inspection['inspection_id']) : [];
$summary = $inspection ? $inspectionModel->getSummaryCounts((int)$inspection['inspection_id']) : ['good' => 0, 'watch' => 0, 'repair' => 0, 'na' => 0, 'unrated' => 0];
$recommendations = $inspection ? $inspectionModel->getRecommendations((int)$inspection['inspection_id']) : [];
$previousInspections = $inspection
    ? $inspectionModel->getPreviousByVehicle((int)($inspection['CVID'] ?? 0), (int)$inspection['inspection_id'])
    : $inspectionModel->getPreviousByVehicle((int)($wo['CVID'] ?? 0));
$flash = Session::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inspection - <?php echo e(generateWONumber($woid)); ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .mpi-action-panel { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin: 14px 0; padding: 14px 16px; background: #fff; border: 1px solid #d9e0e8; border-radius: 8px; }
        .mpi-action-title { margin: 0; color: #173a6a; font-size: 18px; }
        .mpi-action-subtitle { margin-top: 3px; color: #667085; font-size: 13px; }
        .mpi-action-buttons { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: 8px; }
        .mpi-summary-grid { display: grid; grid-template-columns: repeat(5, minmax(110px, 1fr)); gap: 10px; margin: 12px 0 16px; }
        .mpi-summary-tile { border: 1px solid #d9e0e8; border-radius: 8px; padding: 13px 14px; background: linear-gradient(180deg, #fff, #f8fafc); box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .mpi-summary-tile strong { display: block; font-size: 26px; line-height: 1; margin-bottom: 6px; }
        .mpi-summary-tile span { color: #344054; font-weight: 700; }
        .mpi-good { color: #178a55; }
        .mpi-watch { color: #a66f00; }
        .mpi-repair { color: #bf2f38; }
        .mpi-na { color: #667085; }
        .mpi-unrated { color: #4a5565; }
        .mpi-rating-pill { display: inline-block; min-width: 70px; text-align: center; padding: 4px 7px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .mpi-rating-good { color: #178a55; background: #e7f6ee; }
        .mpi-rating-watch { color: #a66f00; background: #fff4d6; }
        .mpi-rating-repair { color: #bf2f38; background: #fde8ea; }
        .mpi-rating-na { color: #667085; background: #eef1f5; }
        .mpi-rating-unrated { color: #4a5565; background: #eef1f5; }
        .mpi-category-card { margin-bottom: 14px; background: #fff; border: 1px solid #d9e0e8; border-radius: 8px; overflow: hidden; }
        .mpi-category-card h2 { margin: 0; padding: 10px 12px; background: #edf2f7; border-bottom: 1px solid #d9e0e8; font-size: 16px; }
        .mpi-recommendations { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px; }
        .mpi-recommendation-card { background: #fff; border: 1px solid #d9e0e8; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
        .mpi-recommendation-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e4eaf1; }
        .mpi-recommendation-header h2 { margin: 0; font-size: 18px; }
        .mpi-recommendation-count { color: #667085; font-size: 12px; font-weight: 700; }
        .mpi-recommendation-list { list-style: none; margin: 0; padding: 0; }
        .mpi-recommendation-list li { display: grid; grid-template-columns: minmax(180px, 260px) minmax(0, 1fr); gap: 10px; padding: 10px 14px; border-top: 1px solid #eef2f6; }
        .mpi-recommendation-list li:first-child { border-top: 0; }
        .mpi-recommendation-item { font-weight: 700; color: #1f2d3d; }
        .mpi-recommendation-note { color: #344054; }
        .mpi-work-order-summary { display: grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap: 18px; }
        .mpi-summary-line { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 10px; align-items: center; margin-bottom: 8px; }
        .mpi-summary-label { color: #596779; font-weight: 700; }
        .mpi-summary-value { font-weight: 700; min-width: 0; }
        @media (max-width: 900px) {
            .mpi-action-panel { align-items: stretch; flex-direction: column; }
            .mpi-action-buttons { justify-content: flex-start; }
            .mpi-summary-grid, .mpi-recommendations, .mpi-work-order-summary { grid-template-columns: 1fr; }
            .mpi-summary-line { grid-template-columns: 95px minmax(0, 1fr); }
            .mpi-recommendation-list li { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Multi-Point Inspection</h1>
        <div class="user-info">
            <a href="work_order_detail.php?woid=<?php echo (int)$woid; ?>">Back to Work Order</a> |
            <a href="work_orders.php">Work Orders</a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <div class="mpi-work-order-summary">
                <div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">Work Order</span><span class="mpi-summary-value"><?php echo e(generateWONumber($woid)); ?></span></div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">Status</span><span class="mpi-summary-value"><?php echo statusBadge($wo['WO_Status'] ?? STATUS_NEW); ?></span></div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">Customer</span><span class="mpi-summary-value"><?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></span></div>
                </div>
                <div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">Vehicle</span><span class="mpi-summary-value"><?php echo e(trim($wo['Year'] . ' ' . $wo['Make'] . ' ' . $wo['Model'])); ?></span></div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">VIN</span><span class="mpi-summary-value"><?php echo e($wo['VIN'] ?? ''); ?></span></div>
                    <div class="mpi-summary-line"><span class="mpi-summary-label">Color</span><span class="mpi-summary-value"><?php echo e($wo['Color'] ?? ''); ?></span></div>
                </div>
            </div>
        </div>

        <?php if (!$inspection): ?>
            <div class="alert alert-warning">
                No multi-point inspection has been started for this work order yet.
                <?php if ($isPendingWorkOrder): ?>
                    <form method="POST" style="display:inline;">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="start_inspection">
                        <button type="submit" class="btn">Start Inspection</button>
                    </form>
                <?php else: ?>
                    A new inspection can only be started when the work order is PENDING. Current status: <?php echo e($wo['WO_Status'] ?? 'Unknown'); ?>.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mpi-action-panel">
                <div>
                    <h2 class="mpi-action-title">Inspection Results</h2>
                    <div class="mpi-action-subtitle">
                        <?php echo e(ucwords(str_replace('_', ' ', $inspection['status'] ?? 'in_progress'))); ?>
                        <?php if (!empty($inspection['completed_at'])): ?>
                            - Completed <?php echo e(formatDateTime($inspection['completed_at'])); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mpi-action-buttons">
                    <button type="button" class="btn btn-primary" onclick="window.open('inspection_pdf.php?inspection_id=<?php echo (int)$inspection['inspection_id']; ?>', '_blank')">Inspection PDF</button>
                    <button type="button" class="btn" onclick="window.location.href='../mechanic/inspection.php?woid=<?php echo (int)$woid; ?>&source=admin'">Open Form</button>
                    <?php if (($inspection['status'] ?? '') === 'completed' && $isPendingWorkOrder): ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reopen this completed inspection for editing?');">
                            <?php csrfField(); ?>
                            <input type="hidden" name="action" value="reopen">
                            <button type="submit" class="btn">Reopen</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mpi-summary-grid">
                <div class="mpi-summary-tile"><strong class="mpi-good"><?php echo (int)$summary['good']; ?></strong><span>Good</span></div>
                <div class="mpi-summary-tile"><strong class="mpi-watch"><?php echo (int)$summary['watch']; ?></strong><span>Watch</span></div>
                <div class="mpi-summary-tile"><strong class="mpi-repair"><?php echo (int)$summary['repair']; ?></strong><span>Repair</span></div>
                <div class="mpi-summary-tile"><strong class="mpi-na"><?php echo (int)$summary['na']; ?></strong><span>N/A</span></div>
                <div class="mpi-summary-tile"><strong class="mpi-unrated"><?php echo (int)$summary['unrated']; ?></strong><span>Unrated</span></div>
            </div>

            <?php if (!empty($recommendations)): ?>
                <?php
                    $repairCount = 0;
                    $watchCount = 0;
                    foreach ($recommendations as $item) {
                        if ($item['rating'] === 'repair') {
                            $repairCount++;
                        } elseif ($item['rating'] === 'watch') {
                            $watchCount++;
                        }
                    }
                ?>
                <div class="mpi-recommendations">
                    <div class="mpi-recommendation-card">
                        <div class="mpi-recommendation-header">
                            <h2 class="mpi-repair">Repair Now</h2>
                            <span class="mpi-recommendation-count"><?php echo (int)$repairCount; ?> item<?php echo $repairCount === 1 ? '' : 's'; ?></span>
                        </div>
                        <ul class="mpi-recommendation-list">
		                            <?php foreach ($recommendations as $item): ?>
		                                <?php if ($item['rating'] === 'repair'): ?>
		                                    <li><span class="mpi-recommendation-item"><?php echo e(VehicleInspection::formatItemCode($item)); ?> <?php echo e($item['item_label']); ?></span><span class="mpi-recommendation-note"><?php echo e($item['note']); ?></span></li>
		                                <?php endif; ?>
		                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="mpi-recommendation-card">
                        <div class="mpi-recommendation-header">
                            <h2 class="mpi-watch">Watch Soon</h2>
                            <span class="mpi-recommendation-count"><?php echo (int)$watchCount; ?> item<?php echo $watchCount === 1 ? '' : 's'; ?></span>
                        </div>
                        <ul class="mpi-recommendation-list">
		                            <?php foreach ($recommendations as $item): ?>
		                                <?php if ($item['rating'] === 'watch'): ?>
		                                    <li><span class="mpi-recommendation-item"><?php echo e(VehicleInspection::formatItemCode($item)); ?> <?php echo e($item['item_label']); ?></span><span class="mpi-recommendation-note"><?php echo e($item['note']); ?></span></li>
		                                <?php endif; ?>
		                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($itemsByCategory as $categoryName => $items): ?>
                <section class="mpi-category-card">
                    <h2><?php echo e($categoryName); ?></h2>
                    <table class="data-grid">
                        <thead>
                            <tr>
                                <th style="width: 42%;">Item</th>
                                <th style="width: 16%;">Rating</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <?php $rating = $item['rating'] ?: 'unrated'; ?>
                                <tr>
	                                    <td>
	                                        <strong><?php echo e(VehicleInspection::formatItemCode($item)); ?> <?php echo e($item['item_label']); ?></strong>
	                                        <div class="text-muted"><?php echo e($item['check_description']); ?></div>
	                                    </td>
                                    <td><span class="mpi-rating-pill mpi-rating-<?php echo e($rating); ?>"><?php echo e(ucwords(str_replace('_', ' ', $rating))); ?></span></td>
                                    <td><?php echo nl2br(e($item['note'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            <?php endforeach; ?>

            <?php if (!empty($previousInspections)): ?>
                <div class="form-container">
                    <h2>Previous Inspections for This Vehicle</h2>
                    <table class="data-grid">
                        <thead><tr><th>Date</th><th>Status</th><th>Work Order</th><th>Completed</th></tr></thead>
                        <tbody>
                            <?php foreach ($previousInspections as $previous): ?>
                                <tr>
                                    <td><?php echo e(formatDateTime($previous['created_at'])); ?></td>
                                    <td><?php echo e($previous['status']); ?></td>
                                    <td><?php echo e(generateWONumber((int)$previous['WOID'])); ?></td>
                                    <td><?php echo e(formatDateTime($previous['completed_at'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
