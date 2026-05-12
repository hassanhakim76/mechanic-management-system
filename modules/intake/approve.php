<?php
/**
 * Draft Intake Approval Wizard
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin() && !Session::isFrontDesk()) {
    http_response_code(403);
    die('Access denied');
}

$draftWoid = (int)get('draft_wo_id', (int)post('draft_wo_id', 0));

if ($draftWoid <= 0) {
    Session::setFlashMessage('error', 'Draft work order ID is required');
    redirect('review_queue.php');
}

$db = Database::getInstance()->getConnection();

$loadDraftSql = "
    SELECT
        dwo.*,
        dc.draft_customer_id,
        dc.first_name, dc.last_name, dc.phone, dc.cell, dc.email, dc.address, dc.city, dc.province, dc.postal_code,
        dc.status AS customer_draft_status,
        dv.draft_vehicle_id,
        dv.plate, dv.vin, dv.make, dv.model, dv.year, dv.color, dv.engine, dv.detail,
        dv.status AS vehicle_draft_status
    FROM draft_work_orders dwo
    JOIN draft_customers dc ON dc.draft_customer_id = dwo.draft_customer_id
    LEFT JOIN draft_vehicles dv ON dv.draft_vehicle_id = dwo.draft_vehicle_id
    WHERE dwo.draft_wo_id = ?
    LIMIT 1
";

$stmt = $db->prepare($loadDraftSql);
$stmt->execute([$draftWoid]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    Session::setFlashMessage('error', 'Draft not found');
    redirect('review_queue.php');
}

if ($draft['status'] !== 'draft') {
    Session::setFlashMessage('error', 'Only draft records can be approved');
    redirect('draft_view.php?draft_wo_id=' . $draftWoid);
}

$customerCandidates = [];
$vehicleCandidates = [];
$error = '';
$transferWarning = '';
$selectedCustomerMode = post('customer_mode', 'new');
$selectedVehicleMode = post('vehicle_mode', 'new');
$selectedExistingCustomerId = trim((string)post('existing_customer_id', ''));
$selectedExistingCvid = trim((string)post('existing_cvid', ''));

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $error = 'Invalid CSRF token';
    } else {
        $customerMode = post('customer_mode', 'new');
        $vehicleMode = post('vehicle_mode', 'new');
        $selectedCustomerMode = $customerMode;
        $selectedVehicleMode = $vehicleMode;
        $draftWorkOrderInput = [
            'wo_status' => trim((string)post('wo_status', 'NEW')),
            'priority' => trim((string)post('priority', PRIORITY_NORMAL)),
            'mileage' => trim((string)post('mileage', '')),
            'wo_req1' => trim((string)post('wo_req1', '')),
            'wo_req2' => trim((string)post('wo_req2', '')),
            'wo_req3' => trim((string)post('wo_req3', '')),
            'wo_req4' => trim((string)post('wo_req4', '')),
            'wo_req5' => trim((string)post('wo_req5', '')),
            'wo_note' => trim((string)post('wo_note', ''))
        ];
        $confirmCustomerDuplicate = (int)post('confirm_customer_duplicate', 0) === 1;
        $confirmVehicleDuplicate = (int)post('confirm_vehicle_duplicate', 0) === 1;
        $confirmVehicleTransfer = (int)post('confirm_vehicle_transfer', 0) === 1;
        $duplicateOverride = ($confirmCustomerDuplicate || $confirmVehicleDuplicate) ? 1 : 0;
        $existingCustomerId = null;
        $existingCvid = null;

        $updateDraft = $db->prepare("
            UPDATE draft_work_orders
            SET wo_status = ?, priority = ?, mileage = ?, wo_req1 = ?, wo_req2 = ?, wo_req3 = ?, wo_req4 = ?, wo_req5 = ?, wo_note = ?
            WHERE draft_wo_id = ? AND status = 'draft'
        ");
        $updateDraft->execute([
            $draftWorkOrderInput['wo_status'],
            $draftWorkOrderInput['priority'],
            $draftWorkOrderInput['mileage'],
            $draftWorkOrderInput['wo_req1'],
            $draftWorkOrderInput['wo_req2'],
            $draftWorkOrderInput['wo_req3'],
            $draftWorkOrderInput['wo_req4'],
            $draftWorkOrderInput['wo_req5'],
            $draftWorkOrderInput['wo_note'],
            $draftWoid
        ]);
        foreach ($draftWorkOrderInput as $key => $value) {
            $draft[$key] = $value;
        }

        try {
            $validationNow = updateDraftValidationSnapshot($db, $draftWoid, (int)Session::getUserId());
        } catch (Throwable $validationError) {
            $validationNow = ['ready' => false, 'missing_reasons' => 'Readiness check failed'];
        }

        $blockingMissing = [];
        if (!empty($validationNow['missing']) && is_array($validationNow['missing'])) {
            $blockingMissing = $validationNow['missing'];
        } elseif (!empty($validationNow['missing_reasons'])) {
            $blockingMissing = explode(' | ', (string)$validationNow['missing_reasons']);
        }

        if ($customerMode === 'existing') {
            $blockingMissing = array_values(array_filter($blockingMissing, function ($item) {
                return !in_array(trim((string)$item), [
                    'Customer: first name is required',
                    'Customer: phone or cell is required'
                ], true);
            }));
        }

        if ($vehicleMode === 'existing') {
            $blockingMissing = array_values(array_filter($blockingMissing, function ($item) {
                return !in_array(trim((string)$item), [
                    'Vehicle: plate or VIN is required',
                    'Vehicle: make is required',
                    'Vehicle: model is required'
                ], true);
            }));
        }

        if (!empty($blockingMissing)) {
            $error = 'Draft is incomplete and cannot be approved yet: ' . implode(' | ', $blockingMissing);
        }

        if (!$error && $customerMode === 'existing') {
            $existingCustomerId = (int)post('existing_customer_id', 0);
            if ($existingCustomerId <= 0) {
                $error = 'Select a valid existing customer ID';
            }
        }

        if (!$error && $customerMode === 'new') {
            $phone = formatPhone((string)($draft['phone'] ?? ''));
            $cell = formatPhone((string)($draft['cell'] ?? ''));
            $dupCustomerConditions = [];
            $dupCustomerParams = [];

            if ($phone !== '') {
                $dupCustomerConditions[] = "Phone COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci";
                $dupCustomerParams[] = $phone;
            }

            if ($cell !== '') {
                $dupCustomerConditions[] = "Cell COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci";
                $dupCustomerParams[] = $cell;
            }

            $dupCustomerCount = 0;
            if (!empty($dupCustomerConditions)) {
                $dupCustomerStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM customers
                    WHERE " . implode(' OR ', $dupCustomerConditions)
                );
                $dupCustomerStmt->execute($dupCustomerParams);
                $dupCustomerCount = (int)$dupCustomerStmt->fetchColumn();
            }

            if ($dupCustomerCount > 0 && !$confirmCustomerDuplicate) {
                $error = 'Potential duplicate customer found. Match existing customer or confirm duplicate override.';
            }
        }

        if (!$error && !empty($draft['draft_vehicle_id']) && $vehicleMode === 'existing') {
            $existingCvid = (int)post('existing_cvid', 0);
            if ($existingCvid <= 0) {
                $error = 'Select a valid existing vehicle CVID';
            } else {
                $selectedVehicleStmt = $db->prepare("
                    SELECT CVID, CustomerID, Status
                    FROM customer_vehicle
                    WHERE CVID = ?
                    LIMIT 1
                ");
                $selectedVehicleStmt->execute([$existingCvid]);
                $selectedVehicle = $selectedVehicleStmt->fetch(PDO::FETCH_ASSOC);

                if (!$selectedVehicle) {
                    $error = 'Selected vehicle CVID was not found';
                } elseif (trim((string)($selectedVehicle['Status'] ?? '')) !== 'A') {
                    $error = 'Selected vehicle CVID is inactive. Choose an active vehicle record.';
                } else {
                    $vehicleOwnerCustomerId = (int)$selectedVehicle['CustomerID'];
                    if ($customerMode === 'new') {
                        $transferWarning = 'Selected vehicle CVID ' . $existingCvid . ' currently belongs to CustomerID ' . $vehicleOwnerCustomerId . '. Approval will transfer ownership to the new customer and set the old vehicle record to inactive.';
                    } elseif ($existingCustomerId !== null && $vehicleOwnerCustomerId !== $existingCustomerId) {
                        $transferWarning = 'Selected vehicle CVID ' . $existingCvid . ' currently belongs to CustomerID ' . $vehicleOwnerCustomerId . '. Approval will transfer ownership to CustomerID ' . $existingCustomerId . ' and set the old vehicle record to inactive.';
                    }

                    if ($transferWarning !== '' && !$confirmVehicleTransfer) {
                        $error = $transferWarning . ' Confirm vehicle transfer to continue.';
                    }
                }
            }
        }

        if (!$error && !empty($draft['draft_vehicle_id']) && $vehicleMode === 'new') {
            $vin = trim((string)($draft['vin'] ?? ''));
            $plate = trim((string)($draft['plate'] ?? ''));
            $dupVehicleConditions = [];
            $dupVehicleParams = [];

            if ($vin !== '') {
                $dupVehicleConditions[] = "UPPER(VIN COLLATE utf8mb4_unicode_ci) = UPPER(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";
                $dupVehicleParams[] = $vin;
            }

            if ($plate !== '') {
                $dupVehicleConditions[] = "UPPER(Plate COLLATE utf8mb4_unicode_ci) = UPPER(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";
                $dupVehicleParams[] = $plate;
            }

            $dupVehicleCount = 0;
            if (!empty($dupVehicleConditions)) {
                $dupVehicleStmt = $db->prepare("
                    SELECT COUNT(*)
                    FROM customer_vehicle
                    WHERE Status = 'A'
                      AND (" . implode(' OR ', $dupVehicleConditions) . ")
                ");
                $dupVehicleStmt->execute($dupVehicleParams);
                $dupVehicleCount = (int)$dupVehicleStmt->fetchColumn();
            }

            if ($dupVehicleCount > 0 && !$confirmVehicleDuplicate) {
                $error = 'Potential duplicate vehicle found. Match existing vehicle or confirm duplicate override.';
            }
        }

        if (!$error) {
            try {
                $approve = $db->prepare("CALL approve_draft_intake(?, ?, ?, ?, ?, ?, ?, ?)");
                $approve->execute([
                    (int)$draft['draft_customer_id'],
                    !empty($draft['draft_vehicle_id']) ? (int)$draft['draft_vehicle_id'] : null,
                    $draftWoid,
                    $existingCustomerId,
                    $existingCvid,
                    (int)Session::getUserId(),
                    $duplicateOverride,
                    $confirmVehicleTransfer ? 1 : 0
                ]);

                $result = $approve->fetch(PDO::FETCH_ASSOC);
                do {
                    $approve->fetchAll();
                } while ($approve->nextRowset());

                $woid = isset($result['WOID']) ? (int)$result['WOID'] : 0;
                if ($woid <= 0) {
                    throw new RuntimeException('Approval did not return a valid WOID');
                }

                Session::setFlashMessage('success', 'Draft approved successfully');
                redirect('../admin/work_order_detail.php?woid=' . $woid);
            } catch (Throwable $e) {
                error_log('[approve_draft] ' . $e->getMessage());
                $error = 'Approval failed. Resolve missing fields/duplicates and try again.';
            }
        }
    }
}

try {
    $phone = formatPhone((string)($draft['phone'] ?? ''));
    $cell = formatPhone((string)($draft['cell'] ?? ''));
    $email = trim((string)($draft['email'] ?? ''));
    $firstName = trim((string)($draft['first_name'] ?? ''));
    $lastName = trim((string)($draft['last_name'] ?? ''));

    if ($phone !== '' || $cell !== '' || $email !== '' || ($firstName !== '' && $lastName !== '')) {
        $customerSelectParts = [
            'c.CustomerID',
            'c.FirstName',
            'c.LastName',
            'c.Phone',
            'c.Cell',
            'c.Email'
        ];
        $customerSelectParams = [];
        $customerScoreParts = [];
        $customerScoreParams = [];
        $customerWhereParts = [];
        $customerWhereParams = [];

        if ($phone !== '') {
            $phoneExpr = "c.Phone COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci";
            $customerSelectParts[] = "($phoneExpr) AS phone_match";
            $customerSelectParams[] = $phone;
            $customerScoreParts[] = "(CASE WHEN $phoneExpr THEN 2 ELSE 0 END)";
            $customerScoreParams[] = $phone;
            $customerWhereParts[] = $phoneExpr;
            $customerWhereParams[] = $phone;
        } else {
            $customerSelectParts[] = '0 AS phone_match';
        }

        if ($cell !== '') {
            $cellExpr = "c.Cell COLLATE utf8mb4_unicode_ci = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci";
            $customerSelectParts[] = "($cellExpr) AS cell_match";
            $customerSelectParams[] = $cell;
            $customerScoreParts[] = "(CASE WHEN $cellExpr THEN 2 ELSE 0 END)";
            $customerScoreParams[] = $cell;
            $customerWhereParts[] = $cellExpr;
            $customerWhereParams[] = $cell;
        } else {
            $customerSelectParts[] = '0 AS cell_match';
        }

        if ($email !== '') {
            $emailExpr = "LOWER(c.Email COLLATE utf8mb4_unicode_ci) = LOWER(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";
            $customerSelectParts[] = "($emailExpr) AS email_match";
            $customerSelectParams[] = $email;
            $customerScoreParts[] = "(CASE WHEN $emailExpr THEN 3 ELSE 0 END)";
            $customerScoreParams[] = $email;
            $customerWhereParts[] = $emailExpr;
            $customerWhereParams[] = $email;
        } else {
            $customerSelectParts[] = '0 AS email_match';
        }

        if ($firstName !== '' && $lastName !== '') {
            $nameExpr = "UPPER(TRIM(c.FirstName) COLLATE utf8mb4_unicode_ci) = UPPER(TRIM(CAST(? AS CHAR CHARACTER SET utf8mb4)) COLLATE utf8mb4_unicode_ci)
                         AND UPPER(TRIM(c.LastName) COLLATE utf8mb4_unicode_ci) = UPPER(TRIM(CAST(? AS CHAR CHARACTER SET utf8mb4)) COLLATE utf8mb4_unicode_ci)";
            $customerSelectParts[] = "($nameExpr) AS name_match";
            $customerSelectParams[] = $firstName;
            $customerSelectParams[] = $lastName;
            $customerScoreParts[] = "(CASE WHEN $nameExpr THEN 1 ELSE 0 END)";
            $customerScoreParams[] = $firstName;
            $customerScoreParams[] = $lastName;
            $customerWhereParts[] = $nameExpr;
            $customerWhereParams[] = $firstName;
            $customerWhereParams[] = $lastName;
        } else {
            $customerSelectParts[] = '0 AS name_match';
        }

        if (!empty($customerWhereParts)) {
            $matchScoreSql = empty($customerScoreParts) ? '0' : implode(' + ', $customerScoreParts);
            $cSql = "
                SELECT
                    " . implode(",\n                    ", $customerSelectParts) . ",
                    ($matchScoreSql) AS match_score
                FROM customers c
                WHERE " . implode("\n                   OR ", $customerWhereParts) . "
                ORDER BY match_score DESC, c.CustomerID DESC
                LIMIT 30
            ";
            $cStmt = $db->prepare($cSql);
            $cStmt->execute(array_merge($customerSelectParams, $customerScoreParams, $customerWhereParams));
            $customerCandidates = $cStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!empty($draft['vin']) || !empty($draft['plate'])) {
        $vin = trim((string)$draft['vin']);
        $plate = trim((string)$draft['plate']);
        $vehicleWhereParts = [];
        $vehicleParams = [];

        if ($vin !== '') {
            $vehicleWhereParts[] = "UPPER(cv.VIN COLLATE utf8mb4_unicode_ci) = UPPER(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";
            $vehicleParams[] = $vin;
        }

        if ($plate !== '') {
            $vehicleWhereParts[] = "UPPER(cv.Plate COLLATE utf8mb4_unicode_ci) = UPPER(CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)";
            $vehicleParams[] = $plate;
        }

        if (!empty($vehicleWhereParts)) {
            $vSql = "
                SELECT cv.CVID, cv.CustomerID, cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color
                FROM customer_vehicle cv
                WHERE cv.Status = 'A'
                  AND (" . implode(' OR ', $vehicleWhereParts) . ")
                ORDER BY cv.CVID DESC
                LIMIT 30
            ";
            $vStmt = $db->prepare($vSql);
            $vStmt->execute($vehicleParams);
            $vehicleCandidates = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    if ($error === '') {
        error_log('[approve_candidates] ' . $e->getMessage());
        $error = 'Unable to load candidate matches right now.';
    }
}

$validation = updateDraftValidationSnapshot($db, $draftWoid, (int)Session::getUserId());
$checklist = getDraftApprovalChecklist();

$missingItems = [];
$missingReasonsText = trim((string)($validation['missing_reasons'] ?? ''));
if ($missingReasonsText !== '' && strtoupper($missingReasonsText) !== 'NONE') {
    $missingItems = array_values(array_filter(array_map('trim', explode('|', $missingReasonsText)), function ($item) {
        return $item !== '';
    }));
}

$hasCustomerMissing = false;
$hasVehicleMissing = false;
$hasWorkOrderMissing = false;
foreach ($missingItems as $missingItem) {
    $normalizedMissing = strtolower($missingItem);
    if (strpos($normalizedMissing, 'customer:') === 0) {
        $hasCustomerMissing = true;
        continue;
    }
    if (strpos($normalizedMissing, 'vehicle:') === 0) {
        $hasVehicleMissing = true;
        continue;
    }
    if (strpos($normalizedMissing, 'work order:') === 0 || strpos($normalizedMissing, 'workorder:') === 0) {
        $hasWorkOrderMissing = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Draft #<?php echo (int)$draft['draft_wo_id']; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Approve Draft #<?php echo (int)$draft['draft_wo_id']; ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="draft_view.php?draft_wo_id=<?php echo (int)$draft['draft_wo_id']; ?>">Back to Draft</a> |
            <a href="review_queue.php">Queue</a>
        </div>
    </div>

    <div class="container approve-wizard">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <div class="alert alert-<?php echo $validation['ready'] ? 'success' : 'warning'; ?>">
            Readiness: <?php echo e($validation['readiness_state']); ?> |
            Escalation: <?php echo e($validation['escalation_level']); ?> |
            Missing: <?php echo e($validation['missing_reasons'] ?: 'None'); ?>
            <?php if (!empty($missingItems)): ?>
                <div class="approve-readiness-links">
                    <strong>Fix first:</strong>
                    <ul>
                        <?php foreach ($missingItems as $missingItem): ?>
                            <?php
                                $anchor = '#step-work-order';
                                $normalizedMissing = strtolower($missingItem);
                                if (strpos($normalizedMissing, 'customer:') === 0) {
                                    $anchor = '#step-customer';
                                } elseif (strpos($normalizedMissing, 'vehicle:') === 0) {
                                    $anchor = '#step-vehicle';
                                } elseif (strpos($normalizedMissing, 'work order:') === 0 || strpos($normalizedMissing, 'workorder:') === 0) {
                                    $anchor = '#step-work-order';
                                }
                            ?>
                            <li><a href="<?php echo $anchor; ?>"><?php echo e($missingItem); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-container" style="margin-bottom: 12px;">
            <h3>Approval Checklist</h3>
            <div>Customer: <?php echo e(implode(', ', array_values($checklist['customer']))); ?></div>
            <div>Vehicle: <?php echo e(implode(', ', array_values($checklist['vehicle']))); ?></div>
            <div>Work Order: <?php echo e(implode(', ', array_values($checklist['work_order']))); ?></div>
        </div>

        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="draft_wo_id" value="<?php echo (int)$draft['draft_wo_id']; ?>">

            <div class="form-container" id="step-customer">
                <div class="approve-step-header">
                    <h3>Step 1: Customer Resolution</h3>
                    <span id="step-customer-status" class="approve-step-status">Checking...</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label><input type="radio" name="customer_mode" value="new" <?php echo $selectedCustomerMode === 'new' ? 'checked' : ''; ?>> Create New Customer</label>
                    </div>
                    <div class="form-group">
                        <label><input type="radio" name="customer_mode" value="existing" <?php echo $selectedCustomerMode === 'existing' ? 'checked' : ''; ?>> Match Existing Customer ID</label>
                        <input type="number" name="existing_customer_id" placeholder="CustomerID" value="<?php echo e($selectedExistingCustomerId); ?>">
                    </div>
                </div>
                <?php if (!empty($customerCandidates)): ?>
                    <div class="candidate-hint">Suggested customers (phone/cell/email/name match): click a row to select Existing Customer ID.</div>
                    <table class="data-grid">
                        <thead><tr><th>CustomerID</th><th>Name</th><th>Phone</th><th>Cell</th><th>Email</th><th>Match</th></tr></thead>
                        <tbody>
                            <?php foreach ($customerCandidates as $c): ?>
                                <?php
                                    $reasons = [];
                                    if ((int)$c['phone_match'] > 0) { $reasons[] = 'Phone'; }
                                    if ((int)$c['cell_match'] > 0) { $reasons[] = 'Cell'; }
                                    if ((int)$c['email_match'] > 0) { $reasons[] = 'Email'; }
                                    if ((int)$c['name_match'] > 0) { $reasons[] = 'Name'; }
                                    $reasonText = empty($reasons) ? 'Other' : implode(', ', $reasons);
                                ?>
                                <tr class="candidate-row customer-candidate-row" data-customer-id="<?php echo (int)$c['CustomerID']; ?>" tabindex="0" role="button" aria-label="Use customer ID <?php echo (int)$c['CustomerID']; ?>">
                                    <td><?php echo (int)$c['CustomerID']; ?></td>
                                    <td><?php echo e(trim($c['FirstName'] . ' ' . $c['LastName'])); ?></td>
                                    <td><?php echo e($c['Phone']); ?></td>
                                    <td><?php echo e($c['Cell']); ?></td>
                                    <td><?php echo e($c['Email']); ?></td>
                                    <td><?php echo e($reasonText); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="approve-override-panel">
                        <label>
                            <input type="checkbox" name="confirm_customer_duplicate" value="1" <?php echo post('confirm_customer_duplicate', '') === '1' ? 'checked' : ''; ?>>
                            I understand possible duplicate customer records and want to create a new customer anyway.
                        </label>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($draft['draft_vehicle_id'])): ?>
                <div class="form-container" id="step-vehicle" style="margin-top: 12px;">
                    <div class="approve-step-header">
                        <h3>Step 2: Vehicle Resolution</h3>
                        <span id="step-vehicle-status" class="approve-step-status">Checking...</span>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label><input type="radio" name="vehicle_mode" value="new" <?php echo $selectedVehicleMode === 'new' ? 'checked' : ''; ?>> Create New Vehicle</label>
                        </div>
                        <div class="form-group">
                            <label><input type="radio" name="vehicle_mode" value="existing" <?php echo $selectedVehicleMode === 'existing' ? 'checked' : ''; ?>> Match Existing Vehicle CVID</label>
                            <input type="number" name="existing_cvid" placeholder="CVID" value="<?php echo e($selectedExistingCvid); ?>">
                        </div>
                    </div>
                    <?php if ($transferWarning !== ''): ?>
                        <div class="alert alert-warning" style="margin-top: 8px;"><?php echo e($transferWarning); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($vehicleCandidates)): ?>
                        <div class="candidate-hint">Suggested vehicles (VIN/Plate match): click a row to select Existing CVID.</div>
                        <table class="data-grid">
                            <thead><tr><th>CVID</th><th>CustomerID</th><th>Plate</th><th>VIN</th><th>Vehicle</th></tr></thead>
                            <tbody>
                                <?php foreach ($vehicleCandidates as $v): ?>
                                    <tr class="candidate-row vehicle-candidate-row" data-cvid="<?php echo (int)$v['CVID']; ?>" data-customer-id="<?php echo (int)$v['CustomerID']; ?>" tabindex="0" role="button" aria-label="Use vehicle CVID <?php echo (int)$v['CVID']; ?>">
                                        <td><?php echo (int)$v['CVID']; ?></td>
                                        <td><?php echo (int)$v['CustomerID']; ?></td>
                                        <td><?php echo e($v['Plate']); ?></td>
                                        <td><?php echo e($v['VIN']); ?></td>
                                        <td><?php echo e(trim($v['Year'] . ' ' . $v['Make'] . ' ' . $v['Model'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="approve-override-panel">
                            <label>
                                <input type="checkbox" name="confirm_vehicle_duplicate" value="1" <?php echo post('confirm_vehicle_duplicate', '') === '1' ? 'checked' : ''; ?>>
                                I understand possible duplicate vehicles and want to create a new vehicle anyway.
                            </label>
                        </div>
                    <?php endif; ?>
                    <div class="approve-override-panel approve-override-panel-transfer">
                        <label>
                            <input type="checkbox" name="confirm_vehicle_transfer" value="1" <?php echo post('confirm_vehicle_transfer', '') === '1' ? 'checked' : ''; ?>>
                            If selected CVID belongs to another customer, transfer it to the selected customer and mark the previous vehicle record as inactive.
                        </label>
                    </div>
                </div>
            <?php else: ?>
                <input type="hidden" name="vehicle_mode" value="new">
            <?php endif; ?>

            <div class="form-container" id="step-work-order" style="margin-top: 12px;">
                <div class="approve-step-header">
                    <h3>Step 3: Confirm Work Order</h3>
                    <span id="step-work-order-status" class="approve-step-status">Checking...</span>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>WO Status</label>
                        <select name="wo_status">
                            <option value="NEW" <?php echo (($draft['wo_status'] ?? 'NEW') === 'NEW') ? 'selected' : ''; ?>>NEW</option>
                            <option value="PENDING" <?php echo (($draft['wo_status'] ?? '') === 'PENDING') ? 'selected' : ''; ?>>PENDING</option>
                            <option value="BILLING" <?php echo (($draft['wo_status'] ?? '') === 'BILLING') ? 'selected' : ''; ?>>BILLING</option>
                            <option value="COMPLETED" <?php echo (($draft['wo_status'] ?? '') === 'COMPLETED') ? 'selected' : ''; ?>>COMPLETED</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <?php echo selectOptions(getPriorityOptions(), $draft['priority'] ?? PRIORITY_NORMAL, false); ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Mileage</label>
                        <input type="text" name="mileage" value="<?php echo e($draft['mileage']); ?>" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Req1</label><input type="text" name="wo_req1" value="<?php echo e($draft['wo_req1']); ?>"></div>
                    <div class="form-group"><label>Req2</label><input type="text" name="wo_req2" value="<?php echo e($draft['wo_req2']); ?>"></div>
                    <div class="form-group"><label>Req3</label><input type="text" name="wo_req3" value="<?php echo e($draft['wo_req3']); ?>"></div>
                    <div class="form-group"><label>Req4</label><input type="text" name="wo_req4" value="<?php echo e($draft['wo_req4']); ?>"></div>
                    <div class="form-group"><label>Req5</label><input type="text" name="wo_req5" value="<?php echo e($draft['wo_req5']); ?>"></div>
                </div>
                <div class="form-group">
                    <label>Work Note</label>
                    <textarea name="wo_note"><?php echo e($draft['wo_note']); ?></textarea>
                </div>
            </div>

            <div class="approve-action-row">
                <span id="approveStatusNote" class="approve-action-note" aria-live="polite"></span>
                <button type="submit" id="approveDraftBtn" class="btn btn-success">Approve Draft Intake</button>
            </div>
        </form>
    </div>
    <script>
        (function () {
            const form = document.querySelector('.approve-wizard form');
            if (!form) {
                return;
            }

            const customerNewRadio = form.querySelector('input[name="customer_mode"][value="new"]');
            const customerExistingRadio = form.querySelector('input[name="customer_mode"][value="existing"]');
            const customerIdInput = form.querySelector('input[name="existing_customer_id"]');
            const vehicleNewRadio = form.querySelector('input[name="vehicle_mode"][value="new"]');
            const vehicleExistingRadio = form.querySelector('input[name="vehicle_mode"][value="existing"]');
            const vehicleCvidInput = form.querySelector('input[name="existing_cvid"]');
            const woStatusInput = form.querySelector('select[name="wo_status"]');
            const priorityInput = form.querySelector('select[name="priority"]');
            const mileageInput = form.querySelector('input[name="mileage"]');
            const woReqInputs = Array.from(form.querySelectorAll('input[name="wo_req1"], input[name="wo_req2"], input[name="wo_req3"], input[name="wo_req4"], input[name="wo_req5"]'));
            const woNoteInput = form.querySelector('textarea[name="wo_note"]');
            const stepCustomerStatus = document.getElementById('step-customer-status');
            const stepVehicleStatus = document.getElementById('step-vehicle-status');
            const stepWorkOrderStatus = document.getElementById('step-work-order-status');
            const approveButton = document.getElementById('approveDraftBtn');
            const approveStatusNote = document.getElementById('approveStatusNote');
            const customerRows = Array.from(document.querySelectorAll('.customer-candidate-row'));
            const vehicleRows = Array.from(document.querySelectorAll('.vehicle-candidate-row'));

            const hasVehicleStep = document.getElementById('step-vehicle') !== null;
            const hasCustomerMissing = <?php echo $hasCustomerMissing ? 'true' : 'false'; ?>;
            const hasVehicleMissing = <?php echo $hasVehicleMissing ? 'true' : 'false'; ?>;

            function normalizeInt(value) {
                const parsed = parseInt(String(value || '').trim(), 10);
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function setStatusBadge(target, isDone, doneText, pendingText) {
                if (!target) {
                    return;
                }

                target.className = 'approve-step-status ' + (isDone ? 'is-done' : 'is-pending');
                target.textContent = isDone ? doneText : pendingText;
            }

            function getCustomerMode() {
                return customerExistingRadio && customerExistingRadio.checked ? 'existing' : 'new';
            }

            function getVehicleMode() {
                return vehicleExistingRadio && vehicleExistingRadio.checked ? 'existing' : 'new';
            }

            function updateSelectedRows() {
                const selectedCustomerId = normalizeInt(customerIdInput ? customerIdInput.value : '');
                customerRows.forEach((row) => {
                    const rowCustomerId = normalizeInt(row.dataset.customerId || '');
                    row.classList.toggle('is-selected', selectedCustomerId > 0 && rowCustomerId === selectedCustomerId);
                });

                const selectedCvid = normalizeInt(vehicleCvidInput ? vehicleCvidInput.value : '');
                vehicleRows.forEach((row) => {
                    const rowCvid = normalizeInt(row.dataset.cvid || '');
                    row.classList.toggle('is-selected', selectedCvid > 0 && rowCvid === selectedCvid);
                });
            }

            function evaluateSteps() {
                const customerMode = getCustomerMode();
                const vehicleMode = getVehicleMode();
                const selectedCustomerId = normalizeInt(customerIdInput ? customerIdInput.value : '');
                const selectedCvid = normalizeInt(vehicleCvidInput ? vehicleCvidInput.value : '');

                const step1Done = customerMode === 'existing' ? selectedCustomerId > 0 : !hasCustomerMissing;
                const step2Done = !hasVehicleStep || (vehicleMode === 'existing' ? selectedCvid > 0 : !hasVehicleMissing);

                const hasWorkRequest = woReqInputs.some((input) => String(input.value || '').trim() !== '');
                const hasWorkNote = woNoteInput && String(woNoteInput.value || '').trim() !== '';
                const hasWorkStatus = woStatusInput && String(woStatusInput.value || '').trim() !== '';
                const hasPriority = priorityInput && String(priorityInput.value || '').trim() !== '';
                const hasMileage = mileageInput && String(mileageInput.value || '').trim() !== '';
                const step3Done = hasWorkStatus && hasPriority && hasMileage && (hasWorkRequest || hasWorkNote);

                setStatusBadge(stepCustomerStatus, step1Done, 'Ready', customerMode === 'existing' ? 'Select CustomerID' : 'Fix Customer Fields');
                setStatusBadge(stepVehicleStatus, step2Done, 'Ready', vehicleMode === 'existing' ? 'Select CVID' : 'Fix Vehicle Fields');
                setStatusBadge(stepWorkOrderStatus, step3Done, 'Ready', 'Add Mileage and Work Request/Note');

                const canApprove = step1Done && step2Done && step3Done;
                if (approveButton) {
                    approveButton.disabled = !canApprove;
                }
                if (approveStatusNote) {
                    approveStatusNote.textContent = canApprove ? 'Ready to approve.' : 'Complete highlighted steps before approval.';
                }

                updateSelectedRows();
            }

            function bindRowSelect(row, callback) {
                row.addEventListener('click', callback);
                row.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        callback();
                    }
                });
            }

            customerRows.forEach((row) => {
                bindRowSelect(row, () => {
                    if (customerExistingRadio) {
                        customerExistingRadio.checked = true;
                    }
                    if (customerIdInput) {
                        customerIdInput.value = String(row.dataset.customerId || '');
                        customerIdInput.focus();
                    }
                    evaluateSteps();
                });
            });

            vehicleRows.forEach((row) => {
                bindRowSelect(row, () => {
                    if (vehicleExistingRadio) {
                        vehicleExistingRadio.checked = true;
                    }
                    if (vehicleCvidInput) {
                        vehicleCvidInput.value = String(row.dataset.cvid || '');
                        vehicleCvidInput.focus();
                    }
                    evaluateSteps();
                });
            });

            [
                customerNewRadio,
                customerExistingRadio,
                customerIdInput,
                vehicleNewRadio,
                vehicleExistingRadio,
                vehicleCvidInput,
                woStatusInput,
                priorityInput,
                mileageInput,
                woNoteInput,
                ...woReqInputs
            ].forEach((el) => {
                if (!el) {
                    return;
                }
                el.addEventListener('change', evaluateSteps);
                el.addEventListener('input', evaluateSteps);
            });

            evaluateSteps();
        })();
    </script>
</body>
</html>
