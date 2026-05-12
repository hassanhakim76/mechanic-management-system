<?php
/**
 * Admin - Work Order Detail
 * View/Edit individual work order
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$woModel = new WorkOrder();
$photoModel = new WorkOrderPhoto();
$employeeModel = new Employee();

$woid = (int)get('woid', 0);
$wo = null;

if ($woid > 0) {
    $wo = $woModel->getById($woid);
    if (!$wo) {
        Session::setFlashMessage('error', 'Work order not found');
        redirect('work_orders.php');
    }
}

$normalizedCurrentStatus = '';
$isCompletedStatus = false;
$isBillingStatus = false;
$isOnHoldStatus = false;
if ($wo) {
    $normalizedCurrentStatus = strtoupper(trim((string)($wo['WO_Status'] ?? '')));
    $isCompletedStatus = in_array($normalizedCurrentStatus, [strtoupper(STATUS_COMPLETED), 'COMPLETE'], true);
    $isBillingStatus = $normalizedCurrentStatus === strtoupper(STATUS_BILLING);
    $isOnHoldStatus = $normalizedCurrentStatus === strtoupper(STATUS_ONHOLD);
}
$reopenStatusValue = trim((string)post('ReopenStatus', STATUS_PENDING));
$reopenReasonValue = trim((string)post('ReopenReason', ''));
$reopenChecked = post('ReopenCompleted') === '1';
$reopenConfirmed = post('ConfirmReopen') === '1';
$statusOverrideReasonValue = trim((string)post('StatusOverrideReason', ''));
$statusOverrideTargetValue = trim((string)post('StatusOverrideTarget', STATUS_BILLING));
$statusOverrideChecked = post('MoveStatusOverride') === '1';
$pendingReturnReasonValue = trim((string)post('ReturnToPendingReason', ''));
$pendingReturnChecked = post('ReturnToPendingOverride') === '1';

// Get mechanics for dropdown
$mechanics = $employeeModel->getMechanics();
$photoCounts = $woid > 0 ? $photoModel->countByWorkOrder($woid) : [];
$generalPhotoCount = (int)($photoCounts['general'] ?? 0);

// Handle form submission
if (isPost() && verifyCSRFToken(post('csrf_token'))) {
    $requestedStatus = (string)($wo['WO_Status'] ?? STATUS_NEW);
    $isReopenRequested = post('ReopenCompleted') === '1';
    $isBillingCompletedRequested = post('BillingCompleted') === '1';
    $isStatusOverrideRequested = post('MoveStatusOverride') === '1';
    $isPendingReturnRequested = post('ReturnToPendingOverride') === '1';
    $reopenReason = trim((string)post('ReopenReason', ''));
    $reopenStatus = trim((string)post('ReopenStatus', STATUS_PENDING));
    $statusOverrideReason = trim((string)post('StatusOverrideReason', ''));
    $statusOverrideTarget = trim((string)post('StatusOverrideTarget', STATUS_BILLING));
    $pendingReturnReason = trim((string)post('ReturnToPendingReason', ''));
    $selectedMechanic = trim((string)post('Mechanic', ''));
    $updateOptions = [];

    if (!$isCompletedStatus && $isBillingStatus && $isBillingCompletedRequested && $isPendingReturnRequested) {
        $error = 'Choose either Billing Completed or Return to PENDING, not both.';
    } elseif ($isCompletedStatus && $isReopenRequested) {
        $requestedStatus = $reopenStatus;
    } elseif (!$isCompletedStatus && $isBillingCompletedRequested) {
        if ($isBillingStatus) {
            $requestedStatus = STATUS_COMPLETED;
        } else {
            $error = 'Billing can only be completed when the work order status is BILLING.';
        }
    } elseif (!$isCompletedStatus && $isPendingReturnRequested) {
        if ($isBillingStatus || $isOnHoldStatus) {
            if ($pendingReturnReason === '') {
                $currentStatusLabel = $isBillingStatus ? STATUS_BILLING : STATUS_ONHOLD;
                $error = 'Reason is required to return an ' . $currentStatusLabel . ' work order to PENDING.';
            } else {
                $requestedStatus = STATUS_PENDING;
                $updateOptions = [
                    'allow_pending_return' => true,
                    'pending_return_reason' => $pendingReturnReason
                ];
            }
        } else {
            $error = 'Return to PENDING is only available when the current status is BILLING or ON-HOLD.';
        }
    } elseif (!$isCompletedStatus && $isStatusOverrideRequested) {
        if ($normalizedCurrentStatus === strtoupper(STATUS_PENDING)) {
            $allowedOverrideStatuses = [STATUS_BILLING, STATUS_ONHOLD];
            if (!in_array($statusOverrideTarget, $allowedOverrideStatuses, true)) {
                $error = 'Select a valid admin override status.';
            } elseif ($statusOverrideReason === '') {
                $error = 'Reason is required for admin status move.';
            } else {
                $requestedStatus = $statusOverrideTarget;
            }
        } else {
            $error = 'Admin status move is only available when the current status is PENDING.';
        }
    } elseif (!$isCompletedStatus && $normalizedCurrentStatus === strtoupper(STATUS_NEW) && $selectedMechanic !== '') {
        $requestedStatus = STATUS_PENDING;
    } elseif ($isCompletedStatus) {
        $requestedStatus = (string)($wo['WO_Status'] ?? STATUS_COMPLETED);
    }

    $allowedReopenStatuses = [STATUS_NEW, STATUS_PENDING, STATUS_BILLING, STATUS_ONHOLD];

    if ($isCompletedStatus && $isReopenRequested) {
        if (post('ConfirmReopen') !== '1') {
            $error = 'Confirm reopen is required to change a completed work order.';
        } elseif ($reopenReason === '') {
            $error = 'Reopen reason is required.';
        } elseif (!in_array($requestedStatus, $allowedReopenStatuses, true)) {
            $error = 'Select a valid reopen status.';
        } else {
            $updateOptions = [
                'allow_reopen' => true,
                'reopen_reason' => $reopenReason
            ];
        }
    }

    $data = [
        'CustomerID' => (int)post('CustomerID'),
        'CVID' => (int)post('CVID'),
        'Mileage' => post('Mileage', ''),
        'WO_Status' => $requestedStatus,
        'Priority' => post('Priority', PRIORITY_NORMAL),
        'WO_Req1' => post('WO_Req1', ''),
        'WO_Req2' => post('WO_Req2', ''),
        'WO_Req3' => post('WO_Req3', ''),
        'WO_Req4' => post('WO_Req4', ''),
        'WO_Req5' => post('WO_Req5', ''),
        'WO_Action1' => post('WO_Action1', ''),
        'WO_Action2' => post('WO_Action2', ''),
        'WO_Action3' => post('WO_Action3', ''),
        'WO_Action4' => post('WO_Action4', ''),
        'WO_Action5' => post('WO_Action5', ''),
        'WO_Note' => post('WO_Note', ''),
        'Customer_Note' => post('Customer_Note', ''),
        'Admin_Note' => post('Admin_Note', ''),
        'Mechanic' => $selectedMechanic,
        'TestDrive' => post('TestDrive'),
        'Req1' => post('Req1'),
        'Req2' => post('Req2'),
        'Req3' => post('Req3'),
        'Req4' => post('Req4'),
        'Req5' => post('Req5')
    ];

    // Shop Note (Mechanic_Note) append with user + timestamp
    $noteMode = post('Mechanic_Note_Mode', 'append');
    $submittedNote = trim((string)post('Mechanic_Note_Add', ''));
    $existingNote = trim((string)($wo['Mechanic_Note'] ?? ''));
    $normalize = function ($text) {
        return str_replace(["\r\n", "\r"], "\n", $text);
    };
    $submittedNorm = $normalize($submittedNote);
    $existingNorm = $normalize($existingNote);
    $newPart = '';
    if ($existingNorm !== '' && str_starts_with($submittedNorm, $existingNorm)) {
        $newPart = ltrim(substr($submittedNorm, strlen($existingNorm)));
    } elseif ($submittedNorm !== $existingNorm) {
        $newPart = $submittedNorm;
    }

    if ($noteMode === 'edit') {
        $data['Mechanic_Note'] = post('Mechanic_Note', $wo['Mechanic_Note']);
    } else {
        if ($newPart === '') {
            $data['Mechanic_Note'] = $wo['Mechanic_Note'];
        } else {
            $stamp = Session::getUsername() . ' - ' . date('Y-m-d H:i') . ': ' . trim($newPart);
            $data['Mechanic_Note'] = $existingNote === '' ? $stamp : ($existingNote . PHP_EOL . $stamp);
        }
    }

    if (!isset($error) && !$isCompletedStatus && $isStatusOverrideRequested && in_array($requestedStatus, [STATUS_BILLING, STATUS_ONHOLD], true)) {
        $actor = trim((string)Session::getUsername());
        if ($actor === '') {
            $actor = 'system';
        }
        $statusStamp = '[MOVED TO ' . strtoupper($requestedStatus) . ' ' . date('Y-m-d H:i') . ' by ' . $actor . '] ' . $statusOverrideReason;
        $adminNoteBase = trim((string)($data['Admin_Note'] ?? ''));
        $data['Admin_Note'] = $adminNoteBase === '' ? $statusStamp : ($adminNoteBase . PHP_EOL . $statusStamp);
    }
    
    if ($woid > 0) {
        // Update existing
        if (!isset($error) && $woModel->update($woid, $data, $updateOptions)) {
            Session::setFlashMessage('success', 'Work order updated successfully');
            redirect('work_order_detail.php?woid=' . $woid);
        } else {
            if (!isset($error)) {
                $error = $woModel->getLastError() ?: 'Failed to update work order';
            }
        }
    }
}

$pageTitle = $wo ? 'Work Order ' . generateWONumber($wo['WOID']) : 'New Work Order';
$flash = Session::getFlashMessage();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body class="work-order-detail">
    <div class="page-toast-stack" id="pageToastStack" aria-live="polite" aria-atomic="true"></div>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="work_orders.php">Back to List</a> | 
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash): ?>
            <noscript>
                <div class="alert alert-<?php echo e($flash['type']); ?>">
                    <?php echo e($flash['message']); ?>
                </div>
            </noscript>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <noscript>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            </noscript>
        <?php endif; ?>

	        <form method="POST" id="adminForm" onsubmit="return AutoShop.validateAdminWorkOrderDetailForm()">
            <?php csrfField(); ?>
            
            <!-- Header Section -->
            <div class="wo-header wo-header--admin-summary">
                <div class="wo-summary-grid">
                    <div class="wo-summary-block">
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Work Order</span>
                            <span class="wo-summary-value"><?php echo $wo ? generateWONumber($wo['WOID']) : 'PREC-[New]'; ?></span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Date/Time</span>
                            <span class="wo-summary-value"><?php echo $wo ? formatDateTime($wo['WO_Date']) : formatDateTime(now()); ?></span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Status</span>
                            <span class="wo-summary-value"><?php echo statusBadge($wo['WO_Status'] ?? STATUS_NEW); ?></span>
                        </div>
                    </div>

                    <div class="wo-summary-block">
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Customer</span>
                            <span class="wo-summary-value">
                                <?php if ($wo): ?>
                                    <a class="wo-summary-link" href="customer_detail.php?id=<?php echo (int)$wo['CustomerID']; ?>"><?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></a>
                                    <span class="wo-summary-meta">(ID: <a class="wo-summary-link" href="customer_detail.php?id=<?php echo (int)$wo['CustomerID']; ?>"><?php echo e($wo['CustomerID']); ?></a>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Contact</span>
                            <span class="wo-summary-value">
                                Phone: <?php echo $wo ? e($wo['Phone']) : ''; ?>
                                <span class="wo-summary-separator">|</span>
                                Cell: <?php echo $wo ? e($wo['Cell']) : ''; ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Email</span>
                            <span class="wo-summary-value"><?php echo $wo ? e($wo['Email']) : ''; ?></span>
                        </div>
                    </div>

                    <div class="wo-summary-block">
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Vehicle</span>
                            <span class="wo-summary-value">
                                <?php echo $wo ? e(trim($wo['Year'] . ' ' . $wo['Make'] . ' ' . $wo['Model'])) : ''; ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Details</span>
                            <span class="wo-summary-value">
                                Plate: <?php echo $wo ? e($wo['Plate']) : ''; ?>
                                <span class="wo-summary-separator">|</span>
                                VIN: <?php echo $wo ? e($wo['VIN']) : ''; ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Specs</span>
                            <span class="wo-summary-value">
                                Color: <?php echo $wo ? e($wo['Color']) : ''; ?>
                                <?php if ($wo && trim((string)($wo['Engine'] ?? '')) !== ''): ?>
                                    <span class="wo-summary-separator">|</span>
                                    Engine: <?php echo e($wo['Engine']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Form -->
            <div class="form-container">
                <div class="work-items-split">
                    <div class="work-items-left">
                        <div class="form-row half-width">
                            <div class="form-group form-group-inline">
                                <label>Priority:</label>
                                <select name="Priority">
                                    <?php echo selectOptions(getPriorityOptions(), $wo['Priority'] ?? PRIORITY_NORMAL, false); ?>
                                </select>
                            </div>

                            <div class="form-group form-group-inline">
                                <label>Mileage:</label>
                                <input type="text" name="Mileage" value="<?php echo e($wo['Mileage'] ?? ''); ?>">
                            </div>
                        </div>
                        <input type="hidden" name="WO_Status" value="<?php echo $wo['WO_Status'] ?? STATUS_NEW; ?>">

                        <!-- Work Items -->
                        <div class="work-items">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
	                                <?php
	                                    $requestValue = (string)($wo['WO_Req' . $i] ?? '');
	                                    $actionValue = (string)($wo['WO_Action' . $i] ?? '');
	                                    $hasRequestValue = trim($requestValue) !== '';
	                                    $photoCount = (int)($photoCounts[$i] ?? 0);
	                                ?>
                                <div class="work-item work-item-half">
                                    <label>W.I. <?php echo $i; ?></label>
                                    <input
                                        type="checkbox"
                                        name="Req<?php echo $i; ?>"
                                        value="1"
                                        data-work-item-checkbox
                                        <?php echo ($wo && $wo['Req' . $i] && $hasRequestValue) ? 'checked' : ''; ?>
                                        <?php echo $hasRequestValue ? '' : 'disabled'; ?>
                                    >
	                                    <input
	                                        type="text"
	                                        name="WO_Req<?php echo $i; ?>"
	                                        data-work-item-text
	                                        value="<?php echo e($requestValue); ?>"
	                                    >
	                                    <button
	                                        type="button"
	                                        class="work-item-photo-button"
	                                        data-work-item-photo-button
	                                        data-work-item-index="<?php echo $i; ?>"
	                                        <?php echo $hasRequestValue ? '' : 'disabled'; ?>
	                                    ><?php echo $photoCount > 0 ? 'Photos ' . $photoCount : 'Photos'; ?></button>
	                                    <input
	                                        type="text"
	                                        name="WO_Action<?php echo $i; ?>"
	                                        data-work-item-action
	                                        placeholder="Action taken"
                                        value="<?php echo e($actionValue); ?>"
                                        <?php echo $hasRequestValue ? '' : 'disabled'; ?>
                                    >
                                </div>
                            <?php endfor; ?>
                        </div>
	                    </div>
	                    <div class="work-items-right work-notes-panel">
                        <div class="work-notes-tabs" role="tablist" aria-label="Work order notes">
                            <button type="button" class="work-notes-tab is-active" data-note-tab="context" aria-selected="true">Customer / WO</button>
                            <button type="button" class="work-notes-tab" data-note-tab="internal" aria-selected="false">Shop / Admin</button>
                        </div>

                        <div class="work-notes-group is-active" data-note-panel="context">
                            <div class="note-section note-section-card note-section-customer">
                                <label>Customer Note</label>
                                <textarea name="Customer_Note"><?php echo e($wo['Customer_Note'] ?? ''); ?></textarea>
                            </div>

                            <div class="note-section note-section-card note-section-work-order">
                                <label>Work Order Note</label>
                                <textarea name="WO_Note"><?php echo e($wo['WO_Note'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="work-notes-group" data-note-panel="internal" hidden>
                            <div class="note-section note-section-card note-section-shop">
                                <label>Shop Note
                                    <span class="icon-button" role="button" tabindex="0" onclick="AutoShop.promptShopNote()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); AutoShop.promptShopNote(); }"><img src="../../plus.jpg" alt="Add" class="icon-plus"></span>
                                    <span class="note-action" role="button" tabindex="0" onclick="AutoShop.startShopNoteEdit()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); AutoShop.startShopNoteEdit(); }">Edit</span>
                                    <span class="note-action note-action-save hidden" role="button" tabindex="0" onclick="AutoShop.saveShopNoteEdit()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); AutoShop.saveShopNoteEdit(); }">Save</span>
                                    <span class="note-action note-action-cancel hidden" role="button" tabindex="0" onclick="AutoShop.cancelShopNoteEdit()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); AutoShop.cancelShopNoteEdit(); }">Cancel</span>
                                </label>
                                <textarea name="Mechanic_Note" id="Mechanic_Note" readonly><?php echo e($wo['Mechanic_Note'] ?? ''); ?></textarea>
                                <input type="hidden" name="Mechanic_Note_Add" id="Mechanic_Note_Add" value="">
                                <input type="hidden" name="Mechanic_Note_Mode" id="Mechanic_Note_Mode" value="append">
                            </div>

                            <div class="note-section note-section-card note-section-admin">
                                <label>Admin Note</label>
                                <textarea name="Admin_Note"><?php echo e($wo['Admin_Note'] ?? ''); ?></textarea>
                            </div>
                        </div>
	                    </div>
	                </div>

	                <!-- Bottom Controls -->
                <div class="form-row work-order-actions-row">
                    <div class="form-group form-group-inline work-order-actions-row__mechanic">
                        <label>Mechanic:</label>
                        <select name="Mechanic">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($mechanics as $mech): ?>
                                <?php $mechName = $mech['Display'] ?: getFullName($mech['FirstName'], $mech['LastName']); ?>
                                <option value="<?php echo e($mechName); ?>" 
                                        <?php echo ($wo && $wo['Mechanic'] == $mechName) ? 'selected' : ''; ?>>
                                    <?php echo e($mechName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group form-group-spacer work-order-actions-row__check">
                        <label>
                            <input type="checkbox" name="TestDrive" value="1" 
                                   <?php echo ($wo && $wo['TestDrive']) ? 'checked' : ''; ?>>
                            Test Drive
                        </label>
                    </div>
                    
                    <?php if ($isCompletedStatus): ?>
                        <div class="form-group" style="min-width: 360px;">
                            <label>
                                <input type="checkbox" name="ReopenCompleted" value="1" <?php echo $reopenChecked ? 'checked' : ''; ?>>
                                Reopen Completed Work Order
                            </label>
                            <div style="display: grid; grid-template-columns: 160px 1fr; gap: 8px; margin-top: 8px;">
                                <select name="ReopenStatus">
                                    <option value="<?php echo e(STATUS_PENDING); ?>" <?php echo $reopenStatusValue === STATUS_PENDING ? 'selected' : ''; ?>><?php echo e(STATUS_PENDING); ?></option>
                                    <option value="<?php echo e(STATUS_BILLING); ?>" <?php echo $reopenStatusValue === STATUS_BILLING ? 'selected' : ''; ?>><?php echo e(STATUS_BILLING); ?></option>
                                    <option value="<?php echo e(STATUS_NEW); ?>" <?php echo $reopenStatusValue === STATUS_NEW ? 'selected' : ''; ?>><?php echo e(STATUS_NEW); ?></option>
                                    <option value="<?php echo e(STATUS_ONHOLD); ?>" <?php echo $reopenStatusValue === STATUS_ONHOLD ? 'selected' : ''; ?>><?php echo e(STATUS_ONHOLD); ?></option>
                                </select>
                                <input type="text" name="ReopenReason" placeholder="Reason for reopen (required)" value="<?php echo e($reopenReasonValue); ?>">
                            </div>
                            <label style="display:block; margin-top: 8px;">
                                <input type="checkbox" name="ConfirmReopen" value="1" <?php echo $reopenConfirmed ? 'checked' : ''; ?>>
                                Confirm deliberate reopen action
                            </label>
                        </div>
                    <?php else: ?>
                        <div class="form-group work-order-actions-row__check">
                            <?php if ($isBillingStatus): ?>
                                <label>
                                    <input type="checkbox" name="BillingCompleted" value="1" <?php echo post('BillingCompleted') === '1' ? 'checked' : ''; ?>>
                                    Billing Completed
                                </label>
                            <?php else: ?>
                                <label style="color: #666;">
                                    Billing Completed
                                </label>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!$isCompletedStatus && ($isBillingStatus || $isOnHoldStatus)): ?>
                        <div class="form-group work-order-actions-row__override-inline">
                            <label>
                                <input type="checkbox" name="ReturnToPendingOverride" value="1" <?php echo $pendingReturnChecked ? 'checked' : ''; ?>>
                                Return to PENDING (Admin Override)
                            </label>
                            <input type="text" name="ReturnToPendingReason" placeholder="Reason for return to PENDING (required)" value="<?php echo e($pendingReturnReasonValue); ?>">
                        </div>
                    <?php endif; ?>
                    <?php if (!$isCompletedStatus && $normalizedCurrentStatus === strtoupper(STATUS_PENDING)): ?>
                        <div class="form-group work-order-actions-row__override-inline">
                            <label>
                                <input type="checkbox" name="MoveStatusOverride" value="1" <?php echo $statusOverrideChecked ? 'checked' : ''; ?>>
                                Move to (Admin Override)
                            </label>
                            <select name="StatusOverrideTarget">
                                <option value="<?php echo e(STATUS_BILLING); ?>" <?php echo $statusOverrideTargetValue === STATUS_BILLING ? 'selected' : ''; ?>>Billing</option>
                                <option value="<?php echo e(STATUS_ONHOLD); ?>" <?php echo $statusOverrideTargetValue === STATUS_ONHOLD ? 'selected' : ''; ?>>On-Hold</option>
                            </select>
                            <input type="text" name="StatusOverrideReason" placeholder="Reason for status move (required)" value="<?php echo e($statusOverrideReasonValue); ?>">
                        </div>
                    <?php endif; ?>
                </div>

                <input type="hidden" name="CustomerID" value="<?php echo $wo['CustomerID'] ?? 0; ?>">
                <input type="hidden" name="CVID" value="<?php echo $wo['CVID'] ?? 0; ?>">
            </div>

            <!-- Buttons -->
            <div class="work-order-footer-actions" style="margin-top: 20px; text-align: right;">
		                <button type="submit" class="btn btn-success">Save</button>
		                <button type="button" class="btn" onclick="window.print()">Print</button>
		                <button type="button" class="btn" <?php echo !$wo ? 'disabled' : ''; ?> onclick="window.open('work_order_pdf_options.php?woid=<?php echo $wo ? (int)$wo['WOID'] : 0; ?>', 'WorkOrderPdfOptions', 'width=760,height=760,scrollbars=yes,resizable=yes')">Customer PDF</button>
		                <button type="button" class="btn" <?php echo !$wo ? 'disabled' : ''; ?> onclick="window.location.href='inspection_view.php?woid=<?php echo $wo ? (int)$wo['WOID'] : 0; ?>'">Inspection</button>
		                <button type="button" class="btn" <?php echo !$wo ? 'disabled' : ''; ?> onclick="AutoShop.openWorkItemPhotos('general')"><?php echo $generalPhotoCount > 0 ? 'Photos ' . $generalPhotoCount : 'Photos'; ?></button>
	                <button type="button" class="btn" <?php echo !$wo ? 'disabled' : ''; ?>>Check List</button>
                    <button type="button" class="btn" <?php echo !$wo ? 'disabled' : ''; ?> onclick="window.location.href='work_order_history.php?vin=<?php echo urlencode($wo['VIN']); ?>&return=<?php echo $wo['WOID']; ?>&source=admin'">History</button>
                <button type="button" class="btn">Signature</button>
                <button type="button" class="btn" onclick="window.location.href='work_orders.php'">Close</button>
            </div>
        </form>
    </div>

    <script src="../../public/js/main.js"></script>
    <script>
        AutoShop = window.AutoShop || {};
	        AutoShop.promptShopNote = function () {
	            var note = window.prompt('Add shop note:');
	            if (note && note.trim()) {
	                document.getElementById('Mechanic_Note_Add').value = note.trim();
	                document.getElementById('Mechanic_Note_Mode').value = 'append';
	                document.getElementById('adminForm').submit();
	            }
	        };
	        AutoShop.openWorkItemPhotos = function (index) {
	            if (!index) {
	                return;
	            }
	            window.open(
	                '../shared/work_order_photos.php?woid=<?php echo (int)$woid; ?>&wi=' + encodeURIComponent(index),
	                'WorkOrderPhotos' + index,
	                'width=980,height=760,scrollbars=yes,resizable=yes'
	            );
	        };
        AutoShop.startShopNoteEdit = function () {
            var textarea = document.getElementById('Mechanic_Note');
            var mode = document.getElementById('Mechanic_Note_Mode');
            textarea.dataset.original = textarea.value;
            textarea.removeAttribute('readonly');
            textarea.focus();
            mode.value = 'edit';
            document.querySelector('.note-action-save').classList.remove('hidden');
            document.querySelector('.note-action-cancel').classList.remove('hidden');
        };
        AutoShop.cancelShopNoteEdit = function () {
            var textarea = document.getElementById('Mechanic_Note');
            var mode = document.getElementById('Mechanic_Note_Mode');
            textarea.value = textarea.dataset.original || textarea.value;
            textarea.setAttribute('readonly', 'readonly');
            mode.value = 'append';
            document.querySelector('.note-action-save').classList.add('hidden');
            document.querySelector('.note-action-cancel').classList.add('hidden');
        };
	        AutoShop.saveShopNoteEdit = function () {
	            document.getElementById('Mechanic_Note_Mode').value = 'edit';
	            document.getElementById('adminForm').submit();
	        };
		        AutoShop.getUncheckedFilledWorkItems = function () {
		            var missing = [];
		            document.querySelectorAll('.work-item').forEach(function (row, index) {
		                var textInput = row.querySelector('[data-work-item-text]');
		                var checkbox = row.querySelector('[data-work-item-checkbox]');
	                if (!textInput || !checkbox) {
	                    return;
	                }
	                if (textInput.value.trim() !== '' && !checkbox.checked) {
	                    missing.push('W.I. ' + (index + 1));
	                }
		            });
		            return missing;
		        };
		        AutoShop.getMissingWorkItemActions = function () {
		            var missing = [];
		            document.querySelectorAll('.work-item').forEach(function (row, index) {
		                var textInput = row.querySelector('[data-work-item-text]');
		                var actionInput = row.querySelector('[data-work-item-action]');
		                if (!textInput || !actionInput) {
		                    return;
		                }
		                if (textInput.value.trim() !== '' && actionInput.value.trim() === '') {
		                    missing.push('W.I. ' + (index + 1));
		                }
		            });
		            return missing;
		        };
		        AutoShop.validateAdminWorkOrderDetailForm = function () {
		            if (!AutoShop.validateWorkOrderForm()) {
		                return false;
		            }

	            var moveStatusOverride = document.querySelector('input[name="MoveStatusOverride"]:checked');
	            var movingToBilling = false;
	            if (moveStatusOverride) {
	                var statusTarget = document.querySelector('select[name="StatusOverrideTarget"]');
	                movingToBilling = !!statusTarget && statusTarget.value === <?php echo json_encode(STATUS_BILLING); ?>;
	            }
	            var completingBilling = !!document.querySelector('input[name="BillingCompleted"]:checked');
	            if (!movingToBilling && !completingBilling) {
	                return true;
	            }

		            var missing = AutoShop.getUncheckedFilledWorkItems();
		            var actionLabel = movingToBilling ? 'move this work order to BILLING' : 'complete this work order';
		            if (missing.length > 0) {
		                window.alert('Check off all filled work items before you ' + actionLabel + ': ' + missing.join(', ') + '.');
		                return false;
		            }

		            var missingActions = AutoShop.getMissingWorkItemActions();
		            if (missingActions.length > 0) {
		                window.alert('Add action taken notes for all filled work items before you ' + actionLabel + ': ' + missingActions.join(', ') + '.');
		                return false;
		            }

		            return true;
		        };
	        AutoShop.showPageToast = function (message, type) {
	            if (!message) {
	                return;
	            }

	            var stack = document.getElementById('pageToastStack');
	            if (!stack) {
	                return;
	            }

	            var toast = document.createElement('div');
	            toast.className = 'page-toast page-toast--' + (type || 'info');
	            toast.textContent = message;
	            stack.appendChild(toast);

	            window.requestAnimationFrame(function () {
	                toast.classList.add('is-visible');
	            });

	            window.setTimeout(function () {
	                toast.classList.remove('is-visible');
	                window.setTimeout(function () {
	                    if (toast.parentNode) {
	                        toast.parentNode.removeChild(toast);
	                    }
	                }, 220);
	            }, 3200);
	        };
		        AutoShop.handleAdminWorkOrderMessages = function () {
		            var flash = <?php echo json_encode($flash, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
		            var errorMessage = <?php echo json_encode(isset($error) ? $error : null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

	            if (flash && flash.message) {
	                if (flash.type === 'success') {
	                    AutoShop.showPageToast(flash.message, 'success');
	                } else {
	                    window.alert(flash.message);
	                }
	            }

	            if (errorMessage) {
		                window.alert(errorMessage);
		            }
		        };
	        AutoShop.initWorkNoteTabs = function () {
	            var panel = document.querySelector('.work-notes-panel');
	            if (!panel) {
	                return;
	            }

	            var tabs = panel.querySelectorAll('[data-note-tab]');
	            var groups = panel.querySelectorAll('[data-note-panel]');
	            var storageKey = 'autoshop.workOrderNotesTab';

	            var activate = function (name) {
	                var matched = false;
	                tabs.forEach(function (tab) {
	                    var isActive = tab.dataset.noteTab === name;
	                    tab.classList.toggle('is-active', isActive);
	                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
	                    matched = matched || isActive;
	                });

	                if (!matched) {
	                    name = 'context';
	                    tabs.forEach(function (tab) {
	                        var isActive = tab.dataset.noteTab === name;
	                        tab.classList.toggle('is-active', isActive);
	                        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
	                    });
	                }

	                groups.forEach(function (group) {
	                    group.hidden = group.dataset.notePanel !== name;
	                });
	            };

	            tabs.forEach(function (tab) {
	                tab.addEventListener('click', function () {
	                    var name = tab.dataset.noteTab;
	                    activate(name);
	                    try {
	                        window.localStorage.setItem(storageKey, name);
	                    } catch (e) {
	                        // Ignore storage restrictions.
	                    }
	                });
	            });

	            var saved = 'context';
	            try {
	                saved = window.localStorage.getItem(storageKey) || 'context';
	            } catch (e) {
	                saved = 'context';
	            }
	            activate(saved);
	        };
		        AutoShop.syncWorkItemCheckboxes = function () {
		            var rows = document.querySelectorAll('.work-item');
		            rows.forEach(function (row) {
		                var textInput = row.querySelector('[data-work-item-text]');
		                var checkbox = row.querySelector('[data-work-item-checkbox]');
		                var actionInput = row.querySelector('[data-work-item-action]');
		                var photoButton = row.querySelector('[data-work-item-photo-button]');
		                if (!textInput || !checkbox) {
		                    return;
		                }

		                var hasValue = textInput.value.trim() !== '';
		                checkbox.disabled = !hasValue;
		                if (actionInput) {
		                    actionInput.disabled = !hasValue;
		                }
		                if (photoButton) {
		                    photoButton.disabled = !hasValue;
		                }
		                if (!hasValue) {
		                    checkbox.checked = false;
		                    if (actionInput) {
		                        actionInput.value = '';
		                    }
		                }
		            });
		        };

	        document.querySelectorAll('[data-work-item-text]').forEach(function (input) {
	            input.addEventListener('input', AutoShop.syncWorkItemCheckboxes);
	            input.addEventListener('change', AutoShop.syncWorkItemCheckboxes);
	        });
	        document.querySelectorAll('[data-work-item-photo-button]').forEach(function (button) {
	            button.addEventListener('click', function () {
	                AutoShop.openWorkItemPhotos(button.dataset.workItemIndex || '');
	            });
	        });

			        AutoShop.syncWorkItemCheckboxes();
	        AutoShop.initWorkNoteTabs();
		        AutoShop.handleAdminWorkOrderMessages();
		    </script>
	</body>
	</html>
