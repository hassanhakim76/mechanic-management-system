<?php
/**
 * Mechanic - Multi-Point Vehicle Inspection
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isMechanic() && !Session::isAdmin()) {
    redirect(BASE_URL . '/index.php');
}

$woid = (int)get('woid', 0);
$isAdminSource = Session::isAdmin() && get('source') === 'admin';
if ($woid <= 0) {
    Session::setFlashMessage('error', 'Invalid work order.');
    redirect($isAdminSource ? '../admin/work_orders.php' : 'work_orders.php');
}

$sourceSuffix = $isAdminSource ? '&source=admin' : '';
$selfUrl = 'inspection.php?woid=' . $woid . $sourceSuffix;
$workOrderUrl = $isAdminSource
    ? '../admin/work_order_detail.php?woid=' . $woid
    : 'work_order_detail.php?woid=' . $woid;

$woModel = new WorkOrder();
$wo = $woModel->getById($woid);
if (!$wo) {
    Session::setFlashMessage('error', 'Work order not found.');
    redirect($isAdminSource ? '../admin/work_orders.php' : 'work_orders.php');
}

$normalizedWorkOrderStatus = strtoupper(trim((string)($wo['WO_Status'] ?? '')));
$isPendingWorkOrder = $normalizedWorkOrderStatus === strtoupper(STATUS_PENDING);

$inspectionModel = new VehicleInspection();
$inspection = $inspectionModel->getByWorkOrder($woid);

if (isPost() && post('action') === 'start_inspection') {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $error = 'Security token expired. Refresh and try again.';
    } elseif ($inspection) {
        redirect($selfUrl);
    } elseif (!$isPendingWorkOrder) {
        $error = 'Inspection can only be started when the work order status is PENDING. Current status: ' . ($wo['WO_Status'] ?? 'Unknown') . '.';
    } else {
        $inspection = $inspectionModel->getOrCreateForWorkOrder($woid);
        if ($inspection) {
            Session::setFlashMessage('success', 'Inspection started.');
            redirect($selfUrl);
        }
        $error = $inspectionModel->getLastError() ?: 'Unable to start inspection.';
    }
}

$itemsByCategory = [];
$summary = ['good' => 0, 'watch' => 0, 'repair' => 0, 'na' => 0, 'unrated' => 0];
$previousInspections = [];

if ($inspection) {
    $canEditInspection = $isPendingWorkOrder && ($inspection['status'] ?? '') !== 'completed';
    if ($canEditInspection && $inspectionModel->syncActiveTemplateItems((int)$inspection['inspection_id'])) {
        $inspection = $inspectionModel->getByWorkOrder($woid);
    }

    if (isPost() && post('action') !== 'start_inspection') {
        if (!verifyCSRFToken(post('csrf_token'))) {
            $error = 'Security token expired. Refresh and try again.';
        } elseif (!$canEditInspection) {
            $error = 'Inspection can only be edited while the work order is PENDING and the inspection is not completed.';
        } else {
            $action = post('action', 'save');
            $items = post('items', []);
            $overallNotes = post('overall_notes', '');

            if ($action === 'complete') {
                if ($inspectionModel->complete((int)$inspection['inspection_id'], is_array($items) ? $items : [], $overallNotes)) {
                    Session::setFlashMessage('success', 'Inspection completed.');
                    redirect($selfUrl);
                } else {
                    $validationErrors = $inspectionModel->getLastValidationErrors();
                    $error = !empty($validationErrors)
                        ? implode("\n", $validationErrors)
                        : ($inspectionModel->getLastError() ?: 'Unable to complete inspection.');
                }
            } else {
                if ($inspectionModel->save((int)$inspection['inspection_id'], is_array($items) ? $items : [], $overallNotes)) {
                    Session::setFlashMessage('success', 'Inspection saved.');
                    redirect($selfUrl);
                } else {
                    $error = $inspectionModel->getLastError() ?: 'Unable to save inspection.';
                }
            }
        }
        $inspection = $inspectionModel->getByWorkOrder($woid);
    }

    $itemsByCategory = $inspectionModel->getItemsByCategory((int)$inspection['inspection_id']);
    $summary = $inspectionModel->getSummaryCounts((int)$inspection['inspection_id']);
    $previousInspections = $inspectionModel->getPreviousByVehicle(
        (int)($inspection['CVID'] ?? 0),
        (int)$inspection['inspection_id']
    );
}

$flash = Session::getFlashMessage();
$isCompleted = $inspection && ($inspection['status'] ?? '') === 'completed';
$canEditInspection = $inspection && $isPendingWorkOrder && !$isCompleted;
$ratingLabels = [
    'good' => 'Good',
    'watch' => 'Watch',
    'repair' => 'Repair',
    'na' => 'N/A'
];

function mpi_rating_label($rating) {
    $map = [
        'good' => 'Good',
        'watch' => 'Watch',
        'repair' => 'Repair',
        'na' => 'N/A'
    ];
    return $map[$rating] ?? 'Unrated';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Point Inspection - <?php echo e(generateWONumber($woid)); ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
    <style>
        .mpi-layout { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 14px; align-items: start; }
        .mpi-sidebar, .mpi-main-card, .mpi-category { background: #fff; border: 1px solid #d9e0e8; border-radius: 8px; }
        .mpi-sidebar { padding: 14px; position: sticky; top: 10px; }
        .mpi-main-card { padding: 14px; margin-bottom: 14px; }
        .mpi-summary-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 12px; }
        .mpi-summary-tile { border: 1px solid #d9e0e8; border-radius: 8px; padding: 9px; background: #f8fafc; }
        .mpi-summary-tile strong { display: block; font-size: 20px; }
        .mpi-good { color: #178a55; }
        .mpi-watch { color: #a66f00; }
        .mpi-repair { color: #bf2f38; }
        .mpi-na { color: #667085; }
        .mpi-unrated { color: #4a5565; }
        .mpi-category { margin-bottom: 14px; overflow: hidden; }
        .mpi-category-header { display: flex; justify-content: space-between; gap: 10px; padding: 10px 12px; background: #edf2f7; border-bottom: 1px solid #d9e0e8; }
        .mpi-category-header h2 { margin: 0; font-size: 16px; }
        .mpi-row { display: grid; grid-template-columns: minmax(220px, 1fr) minmax(280px, 1fr) minmax(260px, 1fr); gap: 12px; padding: 12px; border-top: 1px solid #e5eaf0; }
        .mpi-row:first-of-type { border-top: 0; }
        .mpi-item-title { font-weight: 700; }
        .mpi-item-desc { color: #637083; font-size: 12px; margin-top: 3px; }
        .mpi-rating-group { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 6px; }
        .mpi-rating { position: relative; display: block; }
        .mpi-rating input { position: absolute; opacity: 0; pointer-events: none; }
        .mpi-rating span { display: block; text-align: center; padding: 8px 6px; border: 1px solid #cbd5df; border-radius: 6px; background: #fff; font-weight: 700; font-size: 12px; }
        .mpi-rating input:checked + span { outline: 2px solid #1d6fb8; outline-offset: 1px; }
        .mpi-rating-good span { color: #178a55; background: #e7f6ee; border-color: #9fd6bb; }
        .mpi-rating-watch span { color: #a66f00; background: #fff4d6; border-color: #e4c468; }
        .mpi-rating-repair span { color: #bf2f38; background: #fde8ea; border-color: #eba4aa; }
        .mpi-rating-na span { color: #667085; background: #eef1f5; border-color: #c4ccd6; }
        .mpi-note textarea { min-height: 58px; width: 100%; }
        .mpi-required-note { color: #bf2f38; font-size: 12px; font-weight: 700; margin-top: 4px; display: none; }
        .mpi-note.is-required .mpi-required-note { display: block; }
        .mpi-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
        .mpi-previous { margin-top: 14px; }
        .mpi-previous ul { padding-left: 18px; margin: 8px 0 0; }
        .mpi-status-pill { display: inline-block; border-radius: 999px; padding: 3px 8px; background: #edf2f7; color: #344054; font-size: 12px; font-weight: 700; }
        .mpi-readonly-note { margin-bottom: 12px; }
        .mpi-start-card { background: #fff; border: 1px solid #d9e0e8; border-radius: 8px; padding: 16px; max-width: 760px; }
        .mpi-start-card h2 { margin-top: 0; color: #173a6a; }
        .mpi-start-grid { display: grid; grid-template-columns: repeat(2, minmax(180px, 1fr)); gap: 10px; margin: 12px 0; }
        .mpi-start-block { border: 1px solid #d9e0e8; border-radius: 8px; padding: 10px; background: #f8fafc; }
        @media (max-width: 980px) {
            .mpi-layout { grid-template-columns: 1fr; }
            .mpi-sidebar { position: static; }
            .mpi-row { grid-template-columns: 1fr; }
            .mpi-start-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Multi-Point Inspection</h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="<?php echo e($workOrderUrl); ?>" style="margin-left: 15px; color: white;">Back to Work Order</a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo nl2br(e($error)); ?></div>
        <?php endif; ?>

        <?php if (!$inspection): ?>
            <section class="mpi-start-card">
                <h2>Start Multi-Point Inspection</h2>
                <div class="mpi-start-grid">
                    <div class="mpi-start-block">
                        <span class="wo-summary-label">Work Order</span>
                        <div><strong><?php echo e(generateWONumber($woid)); ?></strong></div>
                        <div class="text-muted">Status: <?php echo e($wo['WO_Status'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="mpi-start-block">
                        <span class="wo-summary-label">Vehicle</span>
                        <div><strong><?php echo e(trim(($wo['Year'] ?? '') . ' ' . ($wo['Make'] ?? '') . ' ' . ($wo['Model'] ?? ''))); ?></strong></div>
                        <div class="text-muted">VIN: <?php echo e($wo['VIN'] ?? ''); ?></div>
                    </div>
                </div>
                <?php if ($isPendingWorkOrder): ?>
                    <form method="POST">
                        <?php csrfField(); ?>
                        <input type="hidden" name="action" value="start_inspection">
                        <button type="submit" class="btn btn-success">Start Inspection</button>
                        <a class="btn" href="<?php echo e($workOrderUrl); ?>">Back to Work Order</a>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        A new multi-point inspection can only be started when the work order is PENDING.
                    </div>
                    <a class="btn" href="<?php echo e($workOrderUrl); ?>">Back to Work Order</a>
                <?php endif; ?>
            </section>
        <?php else: ?>
            <form method="POST" id="mpiForm">
                <?php csrfField(); ?>
                <div class="mpi-layout">
                    <aside class="mpi-sidebar">
                        <div>
                            <span class="wo-summary-label">Work Order</span>
                            <div><strong><?php echo e(generateWONumber($woid)); ?></strong></div>
                        </div>
                        <hr>
                        <div>
                            <span class="wo-summary-label">Customer</span>
                            <div><strong><?php echo e(getFullName($inspection['FirstName'], $inspection['LastName'])); ?></strong></div>
                        </div>
                        <div style="margin-top: 8px;">
                            <span class="wo-summary-label">Vehicle</span>
                            <div><strong><?php echo e(trim($inspection['Year'] . ' ' . $inspection['Make'] . ' ' . $inspection['Model'])); ?></strong></div>
                            <div class="text-muted">VIN: <?php echo e($inspection['VIN']); ?></div>
                        </div>
                        <div style="margin-top: 8px;">
                            <span class="wo-summary-label">Inspection Status</span>
                            <div><span class="mpi-status-pill"><?php echo e(strtoupper(str_replace('_', ' ', $inspection['status']))); ?></span></div>
                        </div>
                        <div style="margin-top: 8px;">
                            <span class="wo-summary-label">Work Order Status</span>
                            <div><span class="mpi-status-pill"><?php echo e($wo['WO_Status'] ?? 'Unknown'); ?></span></div>
                        </div>

                        <div class="mpi-summary-grid">
                            <div class="mpi-summary-tile"><strong class="mpi-good"><?php echo (int)$summary['good']; ?></strong><span>Good</span></div>
                            <div class="mpi-summary-tile"><strong class="mpi-watch"><?php echo (int)$summary['watch']; ?></strong><span>Watch</span></div>
                            <div class="mpi-summary-tile"><strong class="mpi-repair"><?php echo (int)$summary['repair']; ?></strong><span>Repair</span></div>
                            <div class="mpi-summary-tile"><strong class="mpi-na"><?php echo (int)$summary['na']; ?></strong><span>N/A</span></div>
                            <div class="mpi-summary-tile"><strong class="mpi-unrated"><?php echo (int)$summary['unrated']; ?></strong><span>Unrated</span></div>
                        </div>

                        <?php if (!empty($previousInspections)): ?>
                            <div class="mpi-previous">
                                <strong>Previous inspections</strong>
                                <ul>
                                    <?php foreach ($previousInspections as $previous): ?>
                                        <li>
                                            <?php echo e(formatDateTime($previous['created_at'])); ?>
                                            <span class="text-muted">(<?php echo e($previous['status']); ?>)</span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </aside>

                    <section>
                        <div class="mpi-main-card">
                            <?php if ($isCompleted): ?>
                                <div class="alert alert-success mpi-readonly-note">This inspection is completed and locked. Ask an admin to reopen it if changes are required.</div>
                            <?php elseif (!$isPendingWorkOrder): ?>
                                <div class="alert alert-warning mpi-readonly-note">This inspection is read-only because the work order is not PENDING. Current status: <?php echo e($wo['WO_Status'] ?? 'Unknown'); ?>.</div>
                            <?php endif; ?>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Mileage at Inspect</label>
                                    <input type="text" value="<?php echo e($inspection['mileage_at_inspect']); ?>" readonly>
                                </div>
                                <div class="form-group">
                                    <label>Mechanic</label>
                                    <input type="text" value="<?php echo e($inspection['mechanic']); ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Overall Notes</label>
                                <textarea name="overall_notes" <?php echo !$canEditInspection ? 'readonly' : ''; ?>><?php echo e($inspection['overall_notes']); ?></textarea>
                            </div>
                        </div>

                        <?php foreach ($itemsByCategory as $categoryName => $items): ?>
                            <?php
                                $ratedCount = 0;
                                foreach ($items as $item) {
                                    if (!empty($item['rating'])) {
                                        $ratedCount++;
                                    }
                                }
                            ?>
                            <section class="mpi-category">
                                <div class="mpi-category-header">
                                    <h2><?php echo e($categoryName); ?></h2>
                                    <span class="text-muted"><?php echo (int)$ratedCount; ?> of <?php echo count($items); ?> rated</span>
                                </div>
                                <?php foreach ($items as $item): ?>
                                    <?php $rating = (string)($item['rating'] ?? ''); ?>
	                                    <div class="mpi-row" data-inspection-row>
	                                        <div>
	                                            <div class="mpi-item-title"><?php echo e(VehicleInspection::formatItemCode($item)); ?> <?php echo e($item['item_label']); ?></div>
	                                            <div class="mpi-item-desc"><?php echo e($item['check_description']); ?></div>
	                                        </div>
                                        <div class="mpi-rating-group">
                                            <?php foreach ($ratingLabels as $ratingValue => $label): ?>
                                                <label class="mpi-rating mpi-rating-<?php echo e($ratingValue); ?>">
                                                    <input
                                                        type="radio"
                                                        name="items[<?php echo (int)$item['inspection_item_id']; ?>][rating]"
                                                        value="<?php echo e($ratingValue); ?>"
                                                        <?php echo $rating === $ratingValue ? 'checked' : ''; ?>
                                                        <?php echo !$canEditInspection ? 'disabled' : ''; ?>
                                                        data-rating-input
                                                    >
                                                    <span><?php echo e($label); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="mpi-note" data-note-wrap>
                                            <textarea
                                                name="items[<?php echo (int)$item['inspection_item_id']; ?>][note]"
                                                placeholder="Required for Watch or Repair"
                                                <?php echo !$canEditInspection ? 'readonly' : ''; ?>
                                            ><?php echo e($item['note']); ?></textarea>
                                            <span class="mpi-required-note">Note required for Watch / Repair</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <?php if ($canEditInspection): ?>
                            <div class="mpi-actions">
                                <button type="submit" name="action" value="save" class="btn">Save Partial</button>
                                <button type="submit" name="action" value="complete" class="btn btn-success">Complete Inspection</button>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
	            </form>
	        <?php endif; ?>
    </div>

    <script>
        (function () {
            var syncRequiredNotes = function () {
                document.querySelectorAll('[data-inspection-row]').forEach(function (row) {
                    var selected = row.querySelector('[data-rating-input]:checked');
                    var noteWrap = row.querySelector('[data-note-wrap]');
                    if (!noteWrap) {
                        return;
                    }
                    var value = selected ? selected.value : '';
                    noteWrap.classList.toggle('is-required', value === 'watch' || value === 'repair');
                });
            };

            document.querySelectorAll('[data-rating-input]').forEach(function (input) {
                input.addEventListener('change', syncRequiredNotes);
            });
            syncRequiredNotes();
        })();
    </script>
</body>
</html>
