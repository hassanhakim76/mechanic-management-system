<?php
/**
 * Draft Intake View
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

$canManage = Session::isAdmin() || Session::isFrontDesk();
$canEdit = $canManage || Session::isMechanic();

$draftWoid = (int)get('draft_wo_id', 0);

if ($draftWoid <= 0) {
    Session::setFlashMessage('error', 'Draft work order ID is required');
    redirect('review_queue.php');
}

$db = Database::getInstance()->getConnection();

$sql = "
SELECT
    dwo.*,
    dc.first_name, dc.last_name, dc.phone, dc.cell, dc.email, dc.address, dc.city, dc.province, dc.postal_code,
    dc.status AS customer_draft_status,
    dv.plate, dv.vin, dv.make, dv.model, dv.year, dv.color, dv.engine, dv.detail,
    dv.status AS vehicle_draft_status
FROM draft_work_orders dwo
JOIN draft_customers dc ON dc.draft_customer_id = dwo.draft_customer_id
LEFT JOIN draft_vehicles dv ON dv.draft_vehicle_id = dwo.draft_vehicle_id
WHERE dwo.draft_wo_id = ?
LIMIT 1";

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        Session::setFlashMessage('error', 'Invalid CSRF token');
        redirect('draft_view.php?draft_wo_id=' . $draftWoid);
    }

    $action = post('action', '');
    $stmt = $db->prepare($sql);
    $stmt->execute([$draftWoid]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        Session::setFlashMessage('error', 'Draft intake not found');
        redirect('review_queue.php');
    }

    try {
        if ($action === 'save' && $canEdit) {
            if ($current['status'] !== 'draft') {
                throw new RuntimeException('Only draft records can be edited');
            }

            $db->beginTransaction();

            $updateCustomer = $db->prepare("
                UPDATE draft_customers
                SET first_name = ?, last_name = ?, phone = ?, cell = ?, email = ?, address = ?, city = ?, province = ?, postal_code = ?, notes = ?
                WHERE draft_customer_id = ?
            ");
            $updateCustomer->execute([
                titleCase(trim((string)post('first_name', ''))),
                titleCase(trim((string)post('last_name', ''))),
                formatPhone((string)post('phone', '')),
                formatPhone((string)post('cell', '')),
                trim((string)post('email', '')),
                trim((string)post('address', '')),
                titleCase(trim((string)post('city', ''))),
                trim((string)post('province', '')),
                formatPostalCode((string)post('postal_code', '')),
                trim((string)post('customer_notes', '')),
                (int)$current['draft_customer_id']
            ]);

            if (!empty($current['draft_vehicle_id'])) {
                $updateVehicle = $db->prepare("
                    UPDATE draft_vehicles
                    SET plate = ?, vin = ?, make = ?, model = ?, year = ?, color = ?, engine = ?, detail = ?
                    WHERE draft_vehicle_id = ?
                ");
                $updateVehicle->execute([
                    strtoupper(trim((string)post('plate', ''))),
                    strtoupper(trim((string)post('vin', ''))),
                    trim((string)post('make', '')),
                    trim((string)post('model', '')),
                    trim((string)post('year', '')),
                    trim((string)post('color', '')),
                    trim((string)post('engine', '')),
                    trim((string)post('detail', '')),
                    (int)$current['draft_vehicle_id']
                ]);
            }

            $updateWo = $db->prepare("
                UPDATE draft_work_orders
                SET mileage = ?, wo_status = ?, priority = ?, wo_note = ?,
                    wo_req1 = ?, wo_req2 = ?, wo_req3 = ?, wo_req4 = ?, wo_req5 = ?
                WHERE draft_wo_id = ?
            ");
            $updateWo->execute([
                trim((string)post('mileage', '')),
                trim((string)post('wo_status', 'NEW')),
                trim((string)post('priority', PRIORITY_NORMAL)),
                trim((string)post('wo_note', '')),
                trim((string)post('wo_req1', '')),
                trim((string)post('wo_req2', '')),
                trim((string)post('wo_req3', '')),
                trim((string)post('wo_req4', '')),
                trim((string)post('wo_req5', '')),
                $draftWoid
            ]);

            $db->commit();
            $validation = updateDraftValidationSnapshot($db, $draftWoid, (int)Session::getUserId());
            recordDraftStatusLog(
                $db,
                $draftWoid,
                (string)$current['status'],
                (string)$current['status'],
                'save',
                (int)Session::getUserId(),
                'Draft updated from draft view',
                ['ready' => $validation['ready'], 'missing_reasons' => $validation['missing']]
            );
            Session::setFlashMessage('success', 'Draft saved');
        } elseif ($action === 'cancel' && $canManage) {
            $cancel = $db->prepare("CALL cancel_draft_intake(?, ?, ?, ?, ?)");
            $cancel->execute([
                (int)$current['draft_customer_id'],
                !empty($current['draft_vehicle_id']) ? (int)$current['draft_vehicle_id'] : null,
                $draftWoid,
                (int)Session::getUserId(),
                'Cancelled from draft view'
            ]);
            do {
                $cancel->fetchAll();
            } while ($cancel->nextRowset());
            Session::setFlashMessage('success', 'Draft cancelled');
        } else {
            Session::setFlashMessage('error', 'Action not allowed');
        }
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[draft_view] ' . $e->getMessage());
        Session::setFlashMessage('error', 'Action failed. Please verify draft data and try again.');
    }

    redirect('draft_view.php?draft_wo_id=' . $draftWoid);
}

$stmt = $db->prepare($sql);
$stmt->execute([$draftWoid]);
$draft = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$draft) {
    Session::setFlashMessage('error', 'Draft intake not found');
    redirect('review_queue.php');
}

$validation = updateDraftValidationSnapshot($db, $draftWoid, (int)Session::getUserId());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Intake #<?php echo (int)$draft['draft_wo_id']; ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Draft Intake #<?php echo (int)$draft['draft_wo_id']; ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="review_queue.php">Back to Queue</a> |
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
        <div class="alert alert-<?php echo $validation['ready'] ? 'success' : 'warning'; ?>">
            Readiness: <?php echo e($validation['readiness_state']); ?> |
            Escalation: <?php echo e($validation['escalation_level']); ?> |
            Missing: <?php echo e($validation['missing_reasons'] ?: 'None'); ?>
        </div>
        <form method="POST">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">

            <div class="form-container">
                <h3>Draft Customer</h3>
                <div class="form-row">
                    <div class="form-group"><label>Customer Draft Status</label><input type="text" readonly value="<?php echo e($draft['customer_draft_status']); ?>"></div>
                    <div class="form-group"><label>WO Draft Status</label><input type="text" readonly value="<?php echo e($draft['status']); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>First Name</label><input type="text" name="first_name" value="<?php echo e($draft['first_name']); ?>"></div>
                    <div class="form-group"><label>Last Name</label><input type="text" name="last_name" value="<?php echo e($draft['last_name']); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" value="<?php echo e($draft['phone']); ?>"></div>
                    <div class="form-group"><label>Cell</label><input type="text" name="cell" value="<?php echo e($draft['cell']); ?>"></div>
                    <div class="form-group"><label>Email</label><input type="text" name="email" value="<?php echo e($draft['email']); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Address</label><input type="text" name="address" value="<?php echo e($draft['address']); ?>"></div>
                    <div class="form-group"><label>City</label><input type="text" name="city" value="<?php echo e($draft['city']); ?>"></div>
                    <div class="form-group"><label>Province</label><input type="text" name="province" value="<?php echo e($draft['province']); ?>"></div>
                    <div class="form-group"><label>Postal Code</label><input type="text" name="postal_code" value="<?php echo e($draft['postal_code']); ?>"></div>
                </div>
                <div class="form-group">
                    <label>Customer Notes</label>
                    <textarea name="customer_notes"><?php echo e($draft['notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="form-container" style="margin-top:12px;">
                <h3>Draft Vehicle</h3>
                <div class="form-row">
                    <div class="form-group"><label>Status</label><input type="text" readonly value="<?php echo e($draft['vehicle_draft_status'] ?? 'N/A'); ?>"></div>
                    <div class="form-group"><label>Plate</label><input type="text" name="plate" value="<?php echo e($draft['plate']); ?>"></div>
                    <div class="form-group"><label>VIN</label><input type="text" name="vin" id="vinInput" value="<?php echo e($draft['vin']); ?>" maxlength="17"></div>
                    <?php if ($canEdit && $draft['status'] === 'draft'): ?>
                        <div class="form-group" style="align-self:flex-end;">
                            <button type="button" id="btnDecodeVin" class="btn">Decode VIN</button>
                        </div>
                    <?php endif; ?>
                </div>
                <div id="decodeStatus" class="text-muted mb-10"></div>
                <div class="form-row">
                    <div class="form-group"><label>Make</label><input type="text" name="make" id="makeInput" value="<?php echo e($draft['make']); ?>"></div>
                    <div class="form-group"><label>Model</label><input type="text" name="model" id="modelInput" value="<?php echo e($draft['model']); ?>"></div>
                    <div class="form-group"><label>Year</label><input type="text" name="year" id="yearInput" value="<?php echo e($draft['year']); ?>"></div>
                    <div class="form-group"><label>Color</label><input type="text" name="color" id="colorInput" value="<?php echo e($draft['color']); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Engine</label><input type="text" name="engine" id="engineInput" value="<?php echo e($draft['engine']); ?>"></div>
                    <div class="form-group"><label>Detail</label><input type="text" name="detail" id="detailInput" value="<?php echo e($draft['detail']); ?>"></div>
                </div>
            </div>

            <div class="form-container" style="margin-top:12px;">
                <h3>Draft Work Order</h3>
                <div class="form-row">
                    <div class="form-group"><label>Draft WO ID</label><input type="text" readonly value="<?php echo (int)$draft['draft_wo_id']; ?>"></div>
                    <div class="form-group"><label>WO Status</label><input type="text" name="wo_status" value="<?php echo e($draft['wo_status']); ?>"></div>
                    <div class="form-group"><label>Priority</label><input type="text" name="priority" value="<?php echo e($draft['priority']); ?>"></div>
                    <div class="form-group"><label>Mileage</label><input type="text" name="mileage" value="<?php echo e($draft['mileage']); ?>"></div>
                </div>
                <div class="form-group">
                    <label>Work Note</label>
                    <textarea name="wo_note"><?php echo e($draft['wo_note']); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Req1</label><input type="text" name="wo_req1" value="<?php echo e($draft['wo_req1']); ?>"></div>
                    <div class="form-group"><label>Req2</label><input type="text" name="wo_req2" value="<?php echo e($draft['wo_req2']); ?>"></div>
                    <div class="form-group"><label>Req3</label><input type="text" name="wo_req3" value="<?php echo e($draft['wo_req3']); ?>"></div>
                    <div class="form-group"><label>Req4</label><input type="text" name="wo_req4" value="<?php echo e($draft['wo_req4']); ?>"></div>
                    <div class="form-group"><label>Req5</label><input type="text" name="wo_req5" value="<?php echo e($draft['wo_req5']); ?>"></div>
                </div>
            </div>

            <div style="margin-top:12px; text-align:right;">
                <?php if ($canEdit && $draft['status'] === 'draft'): ?>
                    <button type="submit" class="btn btn-primary">Save Draft</button>
                <?php endif; ?>
            </div>
        </form>

        <div style="margin-top: 10px; text-align:right;">
            <?php if ($canManage && $draft['status'] === 'draft'): ?>
                <a href="approve.php?draft_wo_id=<?php echo (int)$draft['draft_wo_id']; ?>" class="btn btn-success">Approve</a>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Cancel this draft intake?');">
                    <?php csrfField(); ?>
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-danger">Cancel Draft</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script>
        (function () {
            const vinInput = document.getElementById('vinInput');
            const btnDecodeVin = document.getElementById('btnDecodeVin');
            const decodeStatus = document.getElementById('decodeStatus');
            const yearInput = document.getElementById('yearInput');
            const makeInput = document.getElementById('makeInput');
            const modelInput = document.getElementById('modelInput');
            const colorInput = document.getElementById('colorInput');
            const engineInput = document.getElementById('engineInput');
            const detailInput = document.getElementById('detailInput');

            if (!vinInput) {
                return;
            }

            function normalizeVin(vin) {
                return String(vin || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            }

            function setDecodeStatus(message, kind) {
                if (!decodeStatus) {
                    return;
                }

                decodeStatus.textContent = message || '';
                if (!message) {
                    decodeStatus.style.color = '';
                    return;
                }

                if (kind === 'error') {
                    decodeStatus.style.color = '#b00020';
                } else if (kind === 'success') {
                    decodeStatus.style.color = '#1b5e20';
                } else {
                    decodeStatus.style.color = '#333';
                }
            }

            function applyDecodedValue(input, value, force) {
                if (!input) {
                    return;
                }

                const normalized = String(value || '').trim();
                if (!normalized) {
                    return;
                }

                if (!force && String(input.value || '').trim() !== '') {
                    return;
                }

                input.value = normalized;
            }

            function handleVinDecode() {
                const vin = normalizeVin(vinInput.value);
                vinInput.value = vin;

                if (vin.length < 11) {
                    setDecodeStatus('Enter at least 11 VIN characters to decode.', 'error');
                    return;
                }

                if (!btnDecodeVin) {
                    return;
                }

                btnDecodeVin.disabled = true;
                setDecodeStatus('Decoding VIN...', 'info');

                fetch('../../public/api/decode_vehicle.php?vin=' + encodeURIComponent(vin))
                    .then(async (res) => {
                        const payload = await res.json().catch(() => ({}));
                        if (!res.ok || !payload.success) {
                            throw new Error(payload.error || 'VIN decode failed');
                        }
                        return payload.data || {};
                    })
                    .then((data) => {
                        applyDecodedValue(yearInput, data.year, true);
                        applyDecodedValue(makeInput, data.make, true);
                        applyDecodedValue(modelInput, data.model, true);
                        applyDecodedValue(colorInput, data.color, true);
                        applyDecodedValue(engineInput, data.engine, true);

                        const detailParts = [
                            data.trim,
                            data.body,
                            data.fuel,
                            data.transmission,
                            data.drivetrain
                        ].filter(Boolean);

                        if (detailParts.length > 0) {
                            applyDecodedValue(detailInput, detailParts.join(' | '), true);
                        }

                        let status = 'VIN decoded. Verify values before saving draft.';
                        if (String(data.color_source || '') === 'history') {
                            status += ' Color pulled from vehicle history.';
                        } else if (String(data.color_source || '') === 'fallback') {
                            status += ' Color not provided by decoder; set to UNKNOWN.';
                        }
                        setDecodeStatus(status, 'success');
                    })
                    .catch((err) => {
                        setDecodeStatus(err.message || 'VIN decode failed.', 'error');
                    })
                    .finally(() => {
                        btnDecodeVin.disabled = false;
                    });
            }

            vinInput.addEventListener('blur', () => {
                vinInput.value = normalizeVin(vinInput.value);
            });

            vinInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    handleVinDecode();
                }
            });

            if (btnDecodeVin) {
                btnDecodeVin.addEventListener('click', handleVinDecode);
            }
        })();
    </script>
</body>
</html>
