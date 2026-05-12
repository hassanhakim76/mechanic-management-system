<?php
/**
 * Utility Functions
 * Helper functions used throughout the application
 */

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Format phone number - strip to digits only (as per VB app)
 */
function formatPhone($phone) {
    return preg_replace('/[^0-9]/', '', $phone);
}

/**
 * Format postal code - uppercase (as per VB app)
 */
function formatPostalCode($postal) {
    return strtoupper(trim($postal));
}

/**
 * Title case for names and cities (as per VB app)
 */
function titleCase($str) {
    return ucwords(strtolower(trim($str)));
}

/**
 * Generate work order number
 */
function generateWONumber($woid) {
    return WO_PREFIX . str_pad($woid, WO_NUMBER_LENGTH, '0', STR_PAD_LEFT);
}

/**
 * Format date for display
 */
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime) {
    return formatDate($datetime, DISPLAY_DATETIME_FORMAT);
}

/**
 * Get current datetime for database
 */
function now() {
    return date(DATETIME_FORMAT);
}

/**
 * Redirect to a page
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Check if request is POST
 */
function isPost() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Check if request is GET
 */
function isGet() {
    return $_SERVER['REQUEST_METHOD'] === 'GET';
}

/**
 * Get POST data
 */
function post($key, $default = null) {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Get GET data
 */
function get($key, $default = null) {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!Session::has('csrf_token')) {
        Session::set('csrf_token', bin2hex(random_bytes(32)));
    }
    return Session::get('csrf_token');
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return hash_equals(Session::get('csrf_token', ''), $token);
}

/**
 * Output CSRF token field
 */
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Escape output for HTML
 */
function e($str) {
    if ($str === null) {
        $str = '';
    }
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Debug function
 */
function dd($var) {
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
    die();
}

/**
 * Get status badge HTML
 */
function statusBadge($status) {
    $colors = [
        STATUS_NEW => 'badge-primary',
        STATUS_PENDING => 'badge-warning',
        STATUS_BILLING => 'badge-info',
        STATUS_COMPLETED => 'badge-success',
        STATUS_CANCELLED => 'badge-danger',
        STATUS_ONHOLD => 'badge-secondary'
    ];
    
    $color = isset($colors[$status]) ? $colors[$status] : 'badge-secondary';
    return '<span class="badge ' . $color . '">' . e($status) . '</span>';
}

/**
 * Get priority badge HTML
 */
function priorityBadge($priority) {
    $colors = [
        PRIORITY_NORMAL => 'badge-secondary',
        PRIORITY_HIGH => 'badge-warning',
        PRIORITY_URGENT => 'badge-danger'
    ];
    
    $color = isset($colors[$priority]) ? $colors[$priority] : 'badge-secondary';
    return '<span class="badge ' . $color . '">' . e($priority) . '</span>';
}

/**
 * Format mileage with comma separator
 */
function formatMileage($mileage) {
    if (empty($mileage)) return '';
    return number_format((int)$mileage);
}

/**
 * Combine work items into display string
 */
function combineWorkItems($wo) {
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $key = 'WO_Req' . $i;
        if (!empty($wo[$key])) {
            $items[] = $wo[$key];
        }
    }
    return implode(', ', $items);
}

/**
 * Split a free-form work request into up to five work-order request lines.
 */
function splitWorkItems($input, $limit = 5) {
    $limit = max(1, (int)$limit);
    $input = trim((string)$input);

    if ($input === '') {
        return array_fill(0, $limit, '');
    }

    $normalizedInput = str_replace(["\r\n", "\n", "\r"], ',', $input);
    $items = array_values(array_filter(array_map('trim', explode(',', $normalizedInput)), function ($item) {
        return $item !== '';
    }));

    if (empty($items)) {
        $items = [$input];
    }

    if (count($items) > $limit) {
        $overflow = array_slice($items, $limit);
        $items = array_slice($items, 0, $limit);
        $items[$limit - 1] = trim($items[$limit - 1] . ', ' . implode(', ', $overflow));
    }

    while (count($items) < $limit) {
        $items[] = '';
    }

    return $items;
}

/**
 * Get full customer name
 */
function getFullName($firstName, $lastName) {
    return trim($firstName . ' ' . $lastName);
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check if at least one contact method exists
 */
function hasContactInfo($phone, $cell, $email) {
    return !empty($phone) || !empty($cell) || !empty($email);
}

/**
 * Get array of status options
 */
function getStatusOptions() {
    return [
        STATUS_NEW,
        STATUS_PENDING,
        STATUS_BILLING,
        STATUS_COMPLETED,
        STATUS_CANCELLED,
        STATUS_ONHOLD
    ];
}

/**
 * Get array of priority options
 */
function getPriorityOptions() {
    return [
        PRIORITY_NORMAL,
        PRIORITY_HIGH,
        PRIORITY_URGENT
    ];
}

/**
 * Generate select options HTML
 */
function selectOptions($options, $selected = '', $includeEmpty = true) {
    $html = '';
    if ($includeEmpty) {
        $html .= '<option value="">-- Select --</option>';
    }
    
    foreach ($options as $value => $label) {
        if (is_numeric($value)) {
            $value = $label;
        }
        $sel = ($value == $selected) ? ' selected' : '';
        $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    
    return $html;
}

/**
 * Log activity (for future implementation)
 */
function logActivity($action, $details = '') {
    // Future: Log to database or file
    error_log("[AutoShop] $action | $details");
}

/**
 * Intake approval checklist policy (single source of truth for app layer)
 */
function getDraftApprovalChecklist() {
    return [
        'customer' => [
            'first_name' => 'Customer first name',
            'phone_or_cell' => 'Customer phone or cell',
        ],
        'vehicle' => [
            'exists' => 'Vehicle record',
            'plate_or_vin' => 'Vehicle plate or VIN',
            'make' => 'Vehicle make',
            'model' => 'Vehicle model',
        ],
        'work_order' => [
            'wo_status' => 'Work order status',
            'priority' => 'Work order priority',
            'mileage' => 'Mileage',
            'complaint_or_request' => 'Complaint or at least one request line',
        ],
    ];
}

/**
 * Evaluate draft readiness for approval.
 */
function evaluateDraftApprovalReadiness(array $draft) {
    $missing = [];

    if (trim((string)($draft['first_name'] ?? '')) === '') {
        $missing[] = 'Customer: first name is required';
    }

    $phone = trim((string)($draft['phone'] ?? ''));
    $cell = trim((string)($draft['cell'] ?? ''));
    if ($phone === '' && $cell === '') {
        $missing[] = 'Customer: phone or cell is required';
    }

    if (empty($draft['draft_vehicle_id'])) {
        $missing[] = 'Vehicle: draft vehicle record is required';
    } else {
        $plate = trim((string)($draft['plate'] ?? ''));
        $vin = trim((string)($draft['vin'] ?? ''));
        if ($plate === '' && $vin === '') {
            $missing[] = 'Vehicle: plate or VIN is required';
        }
        if (trim((string)($draft['make'] ?? '')) === '') {
            $missing[] = 'Vehicle: make is required';
        }
        if (trim((string)($draft['model'] ?? '')) === '') {
            $missing[] = 'Vehicle: model is required';
        }
    }

    if (trim((string)($draft['wo_status'] ?? '')) === '') {
        $missing[] = 'Work order: status is required';
    }
    if (trim((string)($draft['priority'] ?? '')) === '') {
        $missing[] = 'Work order: priority is required';
    }
    if (trim((string)($draft['mileage'] ?? '')) === '') {
        $missing[] = 'Work order: mileage is required';
    }

    $hasComplaint = trim((string)($draft['wo_note'] ?? '')) !== '';
    if (!$hasComplaint) {
        for ($i = 1; $i <= 5; $i++) {
            if (trim((string)($draft['wo_req' . $i] ?? '')) !== '') {
                $hasComplaint = true;
                break;
            }
        }
    }
    if (!$hasComplaint) {
        $missing[] = 'Work order: complaint/request is required';
    }

    return [
        'ready' => empty($missing),
        'missing' => $missing,
    ];
}

/**
 * Escalation policy for incomplete open drafts.
 */
function getDraftEscalationLevel($status, $isReady, $createdAt) {
    if ($isReady || (string)$status !== 'draft') {
        return 'none';
    }

    $createdTs = strtotime((string)$createdAt);
    if ($createdTs === false) {
        return 'warning';
    }

    $ageHours = (time() - $createdTs) / 3600;
    if ($ageHours >= 24) {
        return 'critical';
    }
    if ($ageHours >= 4) {
        return 'warning';
    }
    return 'none';
}

/**
 * Persist readiness snapshot for one draft WO and return evaluation details.
 */
function updateDraftValidationSnapshot(PDO $db, $draftWoid, $userId = null) {
    $stmt = $db->prepare("
        SELECT
            dwo.draft_wo_id,
            dwo.status,
            dwo.created_at,
            dwo.wo_status,
            dwo.priority,
            dwo.mileage,
            dwo.wo_note,
            dwo.wo_req1, dwo.wo_req2, dwo.wo_req3, dwo.wo_req4, dwo.wo_req5,
            dwo.draft_vehicle_id,
            dc.first_name, dc.phone, dc.cell,
            dv.plate, dv.vin, dv.make, dv.model
        FROM draft_work_orders dwo
        JOIN draft_customers dc ON dc.draft_customer_id = dwo.draft_customer_id
        LEFT JOIN draft_vehicles dv ON dv.draft_vehicle_id = dwo.draft_vehicle_id
        WHERE dwo.draft_wo_id = ?
        LIMIT 1
    ");
    $stmt->execute([(int)$draftWoid]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        throw new RuntimeException('Draft work order not found for validation');
    }

    $evaluation = evaluateDraftApprovalReadiness($draft);
    $readyState = $evaluation['ready'] ? 'ready' : 'incomplete';
    $escalation = getDraftEscalationLevel($draft['status'], $evaluation['ready'], $draft['created_at']);
    $missingReasons = $evaluation['ready'] ? null : implode(' | ', $evaluation['missing']);
    $validatedBy = $userId !== null ? (int)$userId : null;

    $update = $db->prepare("
        UPDATE draft_work_orders
        SET readiness_state = ?,
            missing_reasons = ?,
            escalation_level = ?,
            last_validated_at = NOW(),
            last_validated_by_user_id = ?
        WHERE draft_wo_id = ?
    ");
    $update->execute([$readyState, $missingReasons, $escalation, $validatedBy, (int)$draftWoid]);

    return [
        'ready' => $evaluation['ready'],
        'missing' => $evaluation['missing'],
        'readiness_state' => $readyState,
        'escalation_level' => $escalation,
        'missing_reasons' => $missingReasons,
    ];
}

/**
 * Update escalation for all open drafts based on current age/readiness.
 */
function refreshDraftEscalation(PDO $db) {
    $db->exec("
        UPDATE draft_work_orders
        SET escalation_level = CASE
            WHEN status <> 'draft' OR readiness_state = 'ready' THEN 'none'
            WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 24 THEN 'critical'
            WHEN TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 4 THEN 'warning'
            ELSE 'none'
        END
        WHERE status = 'draft'
    ");
}

/**
 * Record draft status/audit events.
 */
function recordDraftStatusLog(PDO $db, $draftWoid, $oldStatus, $newStatus, $action, $performedByUserId, $notes = '', $payload = []) {
    $payloadJson = empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare("
        INSERT INTO draft_status_log
            (draft_wo_id, old_status, new_status, action, performed_by_user_id, notes, payload_json)
        VALUES
            (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        (int)$draftWoid,
        $oldStatus !== null ? (string)$oldStatus : null,
        $newStatus !== null ? (string)$newStatus : null,
        (string)$action,
        (int)$performedByUserId,
        $notes !== '' ? (string)$notes : null,
        $payloadJson
    ]);
}
