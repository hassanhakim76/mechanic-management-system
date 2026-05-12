<?php
/**
 * Intake Draft Review Queue
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

$canManage = Session::isAdmin() || Session::isFrontDesk();

if (isPost()) {
    if (!$canManage) {
        http_response_code(403);
        die('Access denied');
    }

    if (!verifyCSRFToken(post('csrf_token'))) {
        Session::setFlashMessage('error', 'Invalid CSRF token');
        redirect('review_queue.php');
    }

    $action = post('action', '');
    $draftCustomerId = (int)post('draft_customer_id', 0);
    $draftVehicleIdRaw = post('draft_vehicle_id', '');
    $draftVehicleId = $draftVehicleIdRaw === '' ? null : (int)$draftVehicleIdRaw;
    $draftWoid = (int)post('draft_wo_id', 0);

    if ($draftCustomerId <= 0 || $draftWoid <= 0) {
        Session::setFlashMessage('error', 'Invalid draft identifiers');
        redirect('review_queue.php');
    }

    try {
        $db = Database::getInstance()->getConnection();

        if ($action === 'cancel') {
            $stmt = $db->prepare("CALL cancel_draft_intake(?, ?, ?, ?, ?)");
            $stmt->execute([
                $draftCustomerId,
                $draftVehicleId,
                $draftWoid,
                (int)Session::getUserId(),
                'Cancelled from review queue'
            ]);
            do {
                $stmt->fetchAll();
            } while ($stmt->nextRowset());
            Session::setFlashMessage('success', 'Draft cancelled');
        }
    } catch (Throwable $e) {
        error_log('[review_queue] ' . $e->getMessage());
        Session::setFlashMessage('error', 'Action failed. Please verify draft data and try again.');
    }

    redirect('review_queue.php');
}

$status = trim((string)get('status', 'open'));
$dateFrom = trim((string)get('date_from', ''));
$dateTo = trim((string)get('date_to', ''));
$phone = formatPhone((string)get('phone', ''));
$plate = strtoupper(trim((string)get('plate', '')));
$vin = strtoupper(trim((string)get('vin', '')));
$createdBy = trim((string)get('created_by', ''));
$createdById = ctype_digit($createdBy) ? (int)$createdBy : 0;
$ready = trim((string)get('ready', ''));
$escalation = trim((string)get('escalation', ''));

$db = Database::getInstance()->getConnection();
refreshDraftEscalation($db);
$createdByUsersStmt = $db->query("SELECT user_id, username, is_active FROM users ORDER BY username ASC");
$createdByUsers = $createdByUsersStmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "
    SELECT
        dwo.draft_wo_id,
        dwo.status AS draft_wo_status,
        dwo.created_at AS draft_created_at,
        dwo.created_by_user_id,
        u.username AS created_by_username,
        dwo.wo_status,
        dwo.priority,
        dwo.mileage,
        dwo.wo_note,
        dwo.readiness_state,
        dwo.missing_reasons,
        dwo.escalation_level,
        dwo.last_validated_at,
        dc.draft_customer_id,
        dc.first_name,
        dc.last_name,
        dc.phone,
        dc.cell,
        dc.email,
        dc.status AS draft_customer_status,
        dv.draft_vehicle_id,
        dv.plate,
        dv.vin,
        dv.make,
        dv.model,
        dv.year,
        dv.status AS draft_vehicle_status,
        (
          SELECT COUNT(*)
          FROM customers c
          WHERE
              (dc.phone IS NOT NULL AND dc.phone <> '' AND c.Phone COLLATE utf8mb4_unicode_ci = dc.phone COLLATE utf8mb4_unicode_ci)
              OR (dc.cell IS NOT NULL AND dc.cell <> '' AND c.Cell COLLATE utf8mb4_unicode_ci = dc.cell COLLATE utf8mb4_unicode_ci)
        ) AS customer_phone_matches,
        (
          SELECT COUNT(*)
          FROM customers c
          WHERE dc.email IS NOT NULL
            AND dc.email <> ''
            AND LOWER(c.Email COLLATE utf8mb4_unicode_ci) = LOWER(dc.email COLLATE utf8mb4_unicode_ci)
        ) AS customer_email_matches,
        (
          SELECT COUNT(*)
          FROM customers c
          WHERE dc.first_name IS NOT NULL
            AND dc.first_name <> ''
            AND dc.last_name IS NOT NULL
            AND dc.last_name <> ''
            AND UPPER(TRIM(c.FirstName) COLLATE utf8mb4_unicode_ci) = UPPER(TRIM(dc.first_name) COLLATE utf8mb4_unicode_ci)
            AND UPPER(TRIM(c.LastName) COLLATE utf8mb4_unicode_ci) = UPPER(TRIM(dc.last_name) COLLATE utf8mb4_unicode_ci)
        ) AS customer_name_matches,
        (
          SELECT COUNT(*)
          FROM customer_vehicle rv
          WHERE dv.vin IS NOT NULL AND dv.vin <> '' AND UPPER(rv.VIN COLLATE utf8mb4_unicode_ci) = UPPER(dv.vin COLLATE utf8mb4_unicode_ci)
        ) AS vin_matches,
        (
          SELECT COUNT(*)
          FROM customer_vehicle rp
          WHERE dv.plate IS NOT NULL AND dv.plate <> '' AND UPPER(rp.Plate COLLATE utf8mb4_unicode_ci) = UPPER(dv.plate COLLATE utf8mb4_unicode_ci)
        ) AS plate_matches
    FROM draft_work_orders dwo
    JOIN draft_customers dc ON dc.draft_customer_id = dwo.draft_customer_id
    LEFT JOIN draft_vehicles dv ON dv.draft_vehicle_id = dwo.draft_vehicle_id
    LEFT JOIN users u ON u.user_id = dwo.created_by_user_id
    WHERE 1=1
";

$params = [];

if ($status === 'open') {
    $sql .= " AND dwo.status = 'draft'";
} elseif ($status !== '' && $status !== 'all') {
    $sql .= " AND dwo.status = ?";
    $params[] = $status;
}

if ($dateFrom !== '') {
    $sql .= " AND dwo.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if ($dateTo !== '') {
    $sql .= " AND dwo.created_at < DATE_ADD(?, INTERVAL 1 DAY)";
    $params[] = $dateTo;
}

if ($phone !== '') {
    $sql .= " AND (dc.phone LIKE ? OR dc.cell LIKE ?)";
    $params[] = '%' . $phone . '%';
    $params[] = '%' . $phone . '%';
}

if ($plate !== '') {
    $sql .= " AND UPPER(COALESCE(dv.plate, '')) LIKE ?";
    $params[] = '%' . $plate . '%';
}

if ($vin !== '') {
    $sql .= " AND UPPER(COALESCE(dv.vin, '')) LIKE ?";
    $params[] = '%' . $vin . '%';
}

if ($createdById > 0) {
    $sql .= " AND dwo.created_by_user_id = ?";
    $params[] = $createdById;
}

if ($ready !== '' && in_array($ready, ['ready', 'incomplete'], true)) {
    $sql .= " AND dwo.readiness_state = ?";
    $params[] = $ready;
}

if ($escalation !== '' && in_array($escalation, ['none', 'warning', 'critical'], true)) {
    $sql .= " AND dwo.escalation_level = ?";
    $params[] = $escalation;
}

$sql .= " ORDER BY dwo.created_at DESC, dwo.draft_wo_id DESC LIMIT 300";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Draft Intake Queue - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered">Draft Intake Queue</h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <a href="../admin/work_orders.php">Admin</a> |
            <a href="../mechanic/work_orders.php">Mechanic</a> |
            <a href="../../public/logout.php">Logout</a>
        </div>
    </div>

    <div class="container-fluid">
        <?php if ($flash = Session::getFlashMessage()): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <form method="GET" class="form-row" style="margin-bottom: 12px;">
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Open (draft)</option>
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?php echo e($dateFrom); ?>">
            </div>
            <div class="form-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?php echo e($dateTo); ?>">
            </div>
            <div class="form-group">
                <label>Phone/Cell</label>
                <input type="text" name="phone" value="<?php echo e(get('phone', '')); ?>">
            </div>
            <div class="form-group">
                <label>Plate</label>
                <input type="text" name="plate" value="<?php echo e($plate); ?>">
            </div>
            <div class="form-group">
                <label>VIN</label>
                <input type="text" name="vin" value="<?php echo e($vin); ?>">
            </div>
            <div class="form-group">
                <label>Created By</label>
                <select name="created_by">
                    <option value="">Any user</option>
                    <?php foreach ($createdByUsers as $user): ?>
                        <?php
                            $optionId = (int)$user['user_id'];
                            $optionLabel = (string)$user['username'];
                            if ((int)$user['is_active'] !== 1) {
                                $optionLabel .= ' (inactive)';
                            }
                        ?>
                        <option value="<?php echo $optionId; ?>" <?php echo $createdById === $optionId ? 'selected' : ''; ?>>
                            <?php echo e($optionLabel); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Readiness</label>
                <select name="ready">
                    <option value="" <?php echo $ready === '' ? 'selected' : ''; ?>>Any</option>
                    <option value="ready" <?php echo $ready === 'ready' ? 'selected' : ''; ?>>Ready</option>
                    <option value="incomplete" <?php echo $ready === 'incomplete' ? 'selected' : ''; ?>>Incomplete</option>
                </select>
            </div>
            <div class="form-group">
                <label>Escalation</label>
                <select name="escalation">
                    <option value="" <?php echo $escalation === '' ? 'selected' : ''; ?>>Any</option>
                    <option value="none" <?php echo $escalation === 'none' ? 'selected' : ''; ?>>None</option>
                    <option value="warning" <?php echo $escalation === 'warning' ? 'selected' : ''; ?>>Warning</option>
                    <option value="critical" <?php echo $escalation === 'critical' ? 'selected' : ''; ?>>Critical</option>
                </select>
            </div>
            <div class="form-group" style="align-self: flex-end;">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>

        <div class="table-scroll">
            <table class="data-grid">
                <thead>
                    <tr>
                        <th>Draft WO</th>
                        <th>Created</th>
                        <th>Created By</th>
                        <th>Draft Status</th>
                        <th>Customer</th>
                        <th>Phone</th>
                        <th>Vehicle</th>
                        <th>Plate</th>
                        <th>VIN</th>
                        <th>Hint: Customer</th>
                        <th>Hint: Vehicle</th>
                        <th>Ready</th>
                        <th>Escalation</th>
                        <th>Missing</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="15" style="text-align:center; padding:20px;">No draft intakes found</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $customerName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
                                $vehicleText = trim((string)$row['year'] . ' ' . (string)$row['make'] . ' ' . (string)$row['model']);
                                $vehicleHint = 'No match';
                                $rowEscalationLevel = strtolower(trim((string)($row['escalation_level'] ?? '')));
                                if ((int)$row['vin_matches'] > 0) {
                                    $vehicleHint = 'VIN match: ' . (int)$row['vin_matches'];
                                } elseif ((int)$row['plate_matches'] > 0) {
                                    $vehicleHint = 'Plate match: ' . (int)$row['plate_matches'];
                                }
                                $customerHints = [];
                                if ((int)$row['customer_phone_matches'] > 0) {
                                    $customerHints[] = 'Phone/Cell match: ' . (int)$row['customer_phone_matches'];
                                }
                                if ((int)$row['customer_email_matches'] > 0) {
                                    $customerHints[] = 'Email match: ' . (int)$row['customer_email_matches'];
                                }
                                if ((int)$row['customer_name_matches'] > 0) {
                                    $customerHints[] = 'Name match: ' . (int)$row['customer_name_matches'];
                                }
                                $customerHintText = empty($customerHints) ? 'No match' : implode(' | ', $customerHints);
                            ?>
                            <tr data-escalation="<?php echo e($rowEscalationLevel); ?>">
                                <td><?php echo e($row['draft_wo_id']); ?></td>
                                <td><?php echo formatDateTime($row['draft_created_at']); ?></td>
                                <td><?php echo e($row['created_by_username'] ?: ('User #' . (int)$row['created_by_user_id'])); ?></td>
                                <td><?php echo e($row['draft_wo_status']); ?></td>
                                <td><?php echo e($customerName); ?></td>
                                <td><?php echo e($row['phone'] ?: $row['cell']); ?></td>
                                <td><?php echo e($vehicleText); ?></td>
                                <td><?php echo e($row['plate']); ?></td>
                                <td><?php echo e($row['vin']); ?></td>
                                <td><?php echo e($customerHintText); ?></td>
                                <td><?php echo e($vehicleHint); ?></td>
                                <td><?php echo e($row['readiness_state']); ?></td>
                                <td><?php echo e($row['escalation_level']); ?></td>
                                <td><?php echo e($row['missing_reasons'] ?: 'None'); ?></td>
                                <td>
                                    <div class="queue-action-row" style="display:inline-flex; align-items:center; flex-wrap:nowrap; gap:6px; white-space:nowrap;">
                                        <a href="draft_view.php?draft_wo_id=<?php echo (int)$row['draft_wo_id']; ?>" class="btn">View</a>
                                        <?php if ($canManage): ?>
                                            <a href="approve.php?draft_wo_id=<?php echo (int)$row['draft_wo_id']; ?>" class="btn btn-success">Approve</a>
                                            <?php if ($row['draft_wo_status'] === 'draft'): ?>
                                                <form method="POST" class="queue-action-form" style="display:inline-flex; align-items:center; margin:0;" onsubmit="return confirm('Cancel this draft intake?');">
                                                    <?php csrfField(); ?>
                                                    <input type="hidden" name="action" value="cancel">
                                                    <input type="hidden" name="draft_customer_id" value="<?php echo (int)$row['draft_customer_id']; ?>">
                                                    <input type="hidden" name="draft_vehicle_id" value="<?php echo $row['draft_vehicle_id'] !== null ? (int)$row['draft_vehicle_id'] : ''; ?>">
                                                    <input type="hidden" name="draft_wo_id" value="<?php echo (int)$row['draft_wo_id']; ?>">
                                                    <button type="submit" class="btn btn-danger">Cancel</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
