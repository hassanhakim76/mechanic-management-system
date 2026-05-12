<?php
/**
 * Mechanic - Work Order Detail
 * Simplified view for mechanics
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

// Redirect if not mechanic or admin
if (!Session::isMechanic() && !Session::isAdmin()) {
    redirect(BASE_URL . '/index.php');
}

$woModel = new WorkOrder();
$photoModel = new WorkOrderPhoto();
$employeeModel = new Employee();
$currentUser = Session::getUsername();

$woid = (int)get('woid', 0);
$wo = null;
$normalizedCurrentStatus = '';

if ($woid > 0) {
    $wo = $woModel->getById($woid);
}

if (!$wo) {
    Session::setFlashMessage('error', 'Work order not found');
    redirect('work_orders.php');
}

$normalizedCurrentStatus = strtoupper(trim((string)($wo['WO_Status'] ?? STATUS_NEW)));
$isPendingStatus = $normalizedCurrentStatus === strtoupper(STATUS_PENDING);
$isBillingStatus = $normalizedCurrentStatus === strtoupper(STATUS_BILLING);
$canStartOrEditInspection = $isPendingStatus;

// Get mechanics list (to self-assign)
$mechanics = $employeeModel->getMechanics();
$photoCounts = $photoModel->countByWorkOrder($woid);
$generalPhotoCount = (int)($photoCounts['general'] ?? 0);
$canEditActionTaken = $isPendingStatus;

// Handle Form Submission
if (isPost()) {
    $data = $wo; // Start with existing data

    // Update fields allowed for mechanic
    $data['Mileage'] = post('Mileage', $wo['Mileage']);
    if ($isPendingStatus) {
        $data['WO_Action1'] = post('WO_Action1', $wo['WO_Action1'] ?? '');
        $data['WO_Action2'] = post('WO_Action2', $wo['WO_Action2'] ?? '');
        $data['WO_Action3'] = post('WO_Action3', $wo['WO_Action3'] ?? '');
        $data['WO_Action4'] = post('WO_Action4', $wo['WO_Action4'] ?? '');
        $data['WO_Action5'] = post('WO_Action5', $wo['WO_Action5'] ?? '');
    }

    // Checkboxes
    $data['Req1'] = post('Req1');
    $data['Req2'] = post('Req2');
    $data['Req3'] = post('Req3');
    $data['Req4'] = post('Req4');
    $data['Req5'] = post('Req5');

    // Notes
    $submittedNote = trim((string)post('Mechanic_Note_Add', ''));
    $existingNote = trim((string)($wo['Mechanic_Note'] ?? ''));
    $normalize = function ($text) {
        return str_replace(["\r\n", "\r"], "\n", $text);
    };
    $submittedNorm = $normalize($submittedNote);
    $existingNorm = $normalize($existingNote);
    $newPart = '';
    if ($submittedNorm === '') {
        $data['Mechanic_Note'] = $wo['Mechanic_Note'];
    } else {
        $stamp = $currentUser . ' - ' . date('Y-m-d H:i') . ': ' . trim($submittedNorm);
        $data['Mechanic_Note'] = $existingNote === '' ? $stamp : ($existingNote . PHP_EOL . $stamp);
    }
    // Customer note ownership stays with front desk/admin.
    $data['Customer_Note'] = $wo['Customer_Note'];

    // Assignment
    $mechValue = post('Mechanic', '');
    $data['Mechanic'] = $mechValue === '' ? null : $mechValue;

    $isJobCompletedRequested = post('JobCompleted') == '1';

    // Status Logic
    // If mechanic assigns themselves and status is NEW -> Update to PENDING
    if ($wo['WO_Status'] == STATUS_NEW && !empty($data['Mechanic'])) {
        $data['WO_Status'] = STATUS_PENDING;
    }

    // Job Completed Checkbox
    if ($isJobCompletedRequested) {
        if ($isPendingStatus || $isBillingStatus) {
            $data['WO_Status'] = STATUS_BILLING; // Ready for billing
        } else {
            $error = 'Job can only be submitted for billing when the current status is PENDING.';
        }
    }

    if (!isset($error) && $woModel->update($woid, $data)) {
        Session::setFlashMessage('success', 'Work order updated successfully');
        redirect('work_order_detail.php?woid=' . $woid);
    } else {
        if (!isset($error)) {
            $error = $woModel->getLastError() ?: 'Failed to save work order.';
        }
    }
}

$flash = Session::getFlashMessage();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Work Order #<?php echo $wo['WOID']; ?> - Mechanic View</title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body class="work-order-detail">
    <div class="page-toast-stack" id="pageToastStack" aria-live="polite" aria-atomic="true"></div>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Mechanic Detail</h1>
        <div class="user-info">
            <span>User: <?php echo e($currentUser); ?></span>
            <a href="work_orders.php" style="margin-left: 15px; color: white;">Back to List</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($flash): ?>
            <noscript>
                <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
            </noscript>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <noscript>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            </noscript>
        <?php endif; ?>

	        <form method="POST" id="mechanicForm" onsubmit="return AutoShop.validateMechanicWorkOrderDetailForm()">
            <div class="wo-header wo-header--admin-summary">
                <div class="wo-summary-grid">
                    <div class="wo-summary-block">
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Work Order</span>
                            <span class="wo-summary-value"><?php echo generateWONumber($wo['WOID']); ?></span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Date/Time</span>
                            <span class="wo-summary-value"><?php echo formatDateTime($wo['WO_Date']); ?></span>
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
                                <?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?>
                                <span class="wo-summary-meta">(ID: <?php echo e($wo['CustomerID']); ?>)</span>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Contact</span>
                            <span class="wo-summary-value">
                                Phone: <?php echo e($wo['Phone']); ?>
                                <span class="wo-summary-separator">|</span>
                                Cell: <?php echo e($wo['Cell']); ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Email</span>
                            <span class="wo-summary-value"><?php echo e($wo['Email']); ?></span>
                        </div>
                    </div>

                    <div class="wo-summary-block">
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Vehicle</span>
                            <span class="wo-summary-value"><?php echo e(trim($wo['Year'] . ' ' . $wo['Make'] . ' ' . $wo['Model'])); ?></span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Details</span>
                            <span class="wo-summary-value">
                                Plate: <?php echo e($wo['Plate']); ?>
                                <span class="wo-summary-separator">|</span>
                                VIN: <?php echo e($wo['VIN']); ?>
                            </span>
                        </div>
                        <div class="wo-summary-line">
                            <span class="wo-summary-label">Specs</span>
                            <span class="wo-summary-value">
                                Color: <?php echo e($wo['Color']); ?>
                                <?php if (trim((string)($wo['Engine'] ?? '')) !== ''): ?>
                                    <span class="wo-summary-separator">|</span>
                                    Engine: <?php echo e($wo['Engine']); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-container">
                <div class="work-items-split">
                    <div class="work-items-left">
                        <div class="form-row half-width">
                            <div class="form-group form-group-inline">
                                <label>Priority:</label>
                                <select name="Priority" disabled>
                                    <?php echo selectOptions(getPriorityOptions(), $wo['Priority'] ?? PRIORITY_NORMAL, false); ?>
                                </select>
                            </div>
                            
                            <div class="form-group form-group-inline">
                                <label>Mileage:</label>
                                <input type="text" name="Mileage" value="<?php echo e($wo['Mileage']); ?>">
                            </div>
                        </div>

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
	                                        <?php echo ($wo['Req' . $i] && $hasRequestValue) ? 'checked' : ''; ?>
	                                        <?php echo $hasRequestValue ? '' : 'disabled'; ?>
	                                    >
		                                    <input
		                                        type="text"
		                                        name="WO_Req<?php echo $i; ?>"
		                                        data-work-item-text
		                                        value="<?php echo e($requestValue); ?>"
	                                            readonly
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
                                        <?php echo $canEditActionTaken ? '' : 'readonly'; ?>
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
                                <label>Customer Note (Front Desk/Admin Only)</label>
                                <textarea readonly><?php echo e($wo['Customer_Note']); ?></textarea>
                            </div>

                            <div class="note-section note-section-card note-section-work-order">
                                <label>Work Order Note</label>
                                <textarea readonly><?php echo e($wo['WO_Note'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="work-notes-group" data-note-panel="internal" hidden>
                            <div class="note-section note-section-card note-section-shop">
                                <label>Shop Note <span class="icon-button" role="button" tabindex="0" 
	onclick="AutoShop.promptShopNote()" onkeydown="if(event.key==='Enter'||event.key===' ') { event.preventDefault(); 
	AutoShop.promptShopNote(); }"><img src="../../plus.jpg" alt="Add" class="icon-plus"></span></label>
                                <textarea name="Mechanic_Note" readonly><?php echo e($wo['Mechanic_Note']); ?></textarea>
                                <input type="hidden" name="Mechanic_Note_Add" id="Mechanic_Note_Add" value="">
                            </div>

                            <div class="note-section note-section-card note-section-admin">
                                <label>Admin Note</label>
                                <textarea name="Admin_Note" readonly><?php echo e($wo['Admin_Note']); ?></textarea>
                            </div>
                        </div>
	                    </div>
	                </div>

	                <div class="form-row work-order-actions-row">
                    <div class="form-group form-group-inline work-order-actions-row__mechanic">
                        <label>Mechanic:</label>
                        <select name="Mechanic">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($mechanics as $mech): ?>
                                <?php $mechName = $mech['Display'] ?: getFullName($mech['FirstName'], $mech['LastName']); ?>
                                <option value="<?php echo e($mechName); ?>" 
                                        <?php echo ($wo['Mechanic'] == $mechName) ? 'selected' : ''; ?>>
                                    <?php echo e($mechName); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group form-group-spacer work-order-actions-row__check">
                        <?php if ($isPendingStatus || $isBillingStatus): ?>
                            <label>
                                <input type="hidden" name="JobCompleted" value="0">
                                <input type="checkbox" name="JobCompleted" value="1" style="transform: scale(1.2); margin-right: 8px;" <?php echo ($wo['WO_Status'] === STATUS_BILLING || post('JobCompleted') == '1') ? 'checked' : ''; ?>>
                                Job Completed (Submit for Billing)
                            </label>
                        <?php else: ?>
                            <label style="color: #666;">
                                Job Completed (Submit for Billing)
                            </label>
                            <div class="work-order-actions-row__helper">
                                Available only when the current status is <?php echo e(STATUS_PENDING); ?>.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

	                <div class="work-order-footer-actions" style="text-align: right; margin-top: 20px;">
	                    <button type="submit" class="btn btn-success">Save Changes</button>
			                    <button type="button" class="btn" <?php echo $canStartOrEditInspection ? '' : 'disabled title="Inspection is available only when this work order is PENDING."'; ?> onclick="window.location.href='inspection.php?woid=<?php echo (int)$woid; ?>'">Inspection</button>
	                    <button type="button" class="btn" onclick="AutoShop.openWorkItemPhotos('general')"><?php echo $generalPhotoCount > 0 ? 'Photos ' . $generalPhotoCount : 'Photos'; ?></button>
	                    <button type="button" class="btn" onclick="window.location.href='../admin/work_order_history.php?vin=<?php echo urlencode($wo['VIN']); ?>&return=<?php echo $woid; ?>&source=mechanic'">History</button>
                    <button type="button" class="btn" onclick="window.location.href='work_orders.php'">Close</button>
                </div>
            </div>
        </form>
    </div>
    <script>
        AutoShop = window.AutoShop || {};
	        AutoShop.promptShopNote = function () {
	            var note = window.prompt('Add shop note:');
	            if (note && note.trim()) {
	                document.getElementById('Mechanic_Note_Add').value = note.trim();
	                document.getElementById('mechanicForm').submit();
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
		        AutoShop.validateMechanicWorkOrderDetailForm = function () {
		            var completingJob = !!document.querySelector('input[name="JobCompleted"]:checked');
		            if (!completingJob) {
		                return true;
		            }

		            var missing = AutoShop.getUncheckedFilledWorkItems();
		            if (missing.length > 0) {
		                window.alert('Check off all filled work items before you submit this work order to billing: ' + missing.join(', ') + '.');
		                return false;
		            }

		            var missingActions = AutoShop.getMissingWorkItemActions();
		            if (missingActions.length > 0) {
		                window.alert('Add action taken notes for all filled work items before you submit this work order to billing: ' + missingActions.join(', ') + '.');
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
		        AutoShop.handleMechanicWorkOrderMessages = function () {
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
		        AutoShop.handleMechanicWorkOrderMessages();
		    </script>
	</body>
	</html>
