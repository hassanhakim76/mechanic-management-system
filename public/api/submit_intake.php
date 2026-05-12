<?php
/**
 * Intake Submission API
 * Draft-only intake workflow:
 * 1. Create draft customer
 * 2. Optionally create draft vehicle
 * 3. Create draft work order
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

if (!verifyCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $conn->beginTransaction();

    $mode = $input['mode'] ?? 'new';
    $vehicleMode = $input['vehicle_mode'] ?? 'new';
    $createdByUserId = (int)Session::getUserId();
    $draftStatus = 'draft';

    $firstName = titleCase(trim((string)($input['first_name'] ?? '')));
    $lastName = titleCase(trim((string)($input['last_name'] ?? '')));
    $phone = formatPhone((string)($input['phone'] ?? ''));
    $cell = formatPhone((string)($input['cell'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $address = trim((string)($input['address'] ?? ''));
    $city = titleCase(trim((string)($input['city'] ?? '')));
    $province = trim((string)($input['province'] ?? ''));
    $postalCode = formatPostalCode((string)($input['postal_code'] ?? ''));

    // Customer notes are separate from the intake service request.
    $notes = trim((string)($input['customer_notes'] ?? ''));
    $serviceDescription = trim((string)($input['description'] ?? ''));

    // Backward compatibility for clients that may already submit req1..req5 directly.
    $requestLines = [
        trim((string)($input['req1'] ?? '')),
        trim((string)($input['req2'] ?? '')),
        trim((string)($input['req3'] ?? '')),
        trim((string)($input['req4'] ?? '')),
        trim((string)($input['req5'] ?? ''))
    ];
    $hasExplicitRequestLine = false;
    foreach ($requestLines as $requestLine) {
        if ($requestLine !== '') {
            $hasExplicitRequestLine = true;
            break;
        }
    }

    if (!$hasExplicitRequestLine && $serviceDescription !== '') {
        $normalizedDescription = str_replace(["\r\n", "\n", "\r"], ',', $serviceDescription);
        $splitRequests = array_values(array_filter(array_map('trim', explode(',', $normalizedDescription)), function ($part) {
            return $part !== '';
        }));

        if (empty($splitRequests)) {
            $splitRequests = [$serviceDescription];
        }

        if (count($splitRequests) > 5) {
            $overflow = array_slice($splitRequests, 5);
            $splitRequests = array_slice($splitRequests, 0, 5);
            $splitRequests[4] = trim($splitRequests[4] . ', ' . implode(', ', $overflow));
        }

        for ($i = 0; $i < 5; $i++) {
            $requestLines[$i] = $splitRequests[$i] ?? '';
        }
    }

    $woNote = trim((string)($input['wo_note'] ?? ''));

    if ($mode === 'new' && $firstName === '') {
        throw new Exception('First Name is required for new intake');
    }

    // Returning mode: preload known customer info into draft snapshot when provided.
    if ($mode === 'returning' && !empty($input['customer_id'])) {
        $existingCustomer = $db->querySingle(
            "SELECT FirstName, LastName, Phone, Cell, Email, Address, City, Province, PostalCode
             FROM customers
             WHERE CustomerID = ?",
            [(int)$input['customer_id']]
        );

        if (!$existingCustomer) {
            throw new Exception('Selected customer was not found');
        }

        $firstName = $firstName !== '' ? $firstName : titleCase((string)$existingCustomer['FirstName']);
        $lastName = $lastName !== '' ? $lastName : titleCase((string)$existingCustomer['LastName']);
        $phone = $phone !== '' ? $phone : formatPhone((string)$existingCustomer['Phone']);
        $cell = $cell !== '' ? $cell : formatPhone((string)$existingCustomer['Cell']);
        $email = $email !== '' ? $email : (string)$existingCustomer['Email'];
        $address = $address !== '' ? $address : (string)$existingCustomer['Address'];
        $city = $city !== '' ? $city : titleCase((string)$existingCustomer['City']);
        $province = $province !== '' ? $province : (string)$existingCustomer['Province'];
        $postalCode = $postalCode !== '' ? $postalCode : formatPostalCode((string)$existingCustomer['PostalCode']);
        $existingCustomerId = (int)$input['customer_id'];
        $notes = trim(($notes !== '' ? $notes . ' | ' : '') . 'Returning customer candidate ID: ' . $existingCustomerId);
    }

    // Step 1: draft customer
    $draftCustomerId = $db->insert(
        "INSERT INTO draft_customers
            (first_name, last_name, phone, cell, email, address, city, province, postal_code, notes, status, created_by_user_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $firstName,
            $lastName,
            $phone,
            $cell,
            $email,
            $address,
            $city,
            $province,
            $postalCode,
            $notes,
            $draftStatus,
            $createdByUserId ?: null
        ]
    );

    if (!$draftCustomerId) {
        throw new Exception('Unable to create draft customer');
    }

    // Step 2: optional draft vehicle
    $draftVehicleId = null;
    $towing = !empty($input['towing']) ? 1 : 0;

    $plate = strtoupper(trim((string)($input['plate'] ?? '')));
    $vin = strtoupper(trim((string)($input['vin'] ?? '')));
    $make = trim((string)($input['make'] ?? ''));
    $model = trim((string)($input['model'] ?? ''));
    $year = trim((string)($input['year'] ?? ''));
    $color = trim((string)($input['color'] ?? ''));
    $engine = trim((string)($input['engine'] ?? ''));
    $detail = trim((string)($input['detail'] ?? ''));

    if ($vehicleMode === 'existing' && !empty($input['cvid'])) {
        $existingVehicle = $db->querySingle(
            "SELECT Plate, VIN, Make, Model, Year, Color, Engine, Detail
             FROM customer_vehicle
             WHERE CVID = ?",
            [(int)$input['cvid']]
        );

        if (!$existingVehicle) {
            throw new Exception('Selected vehicle was not found');
        }

        $plate = $plate !== '' ? $plate : strtoupper((string)$existingVehicle['Plate']);
        $vin = $vin !== '' ? $vin : strtoupper((string)$existingVehicle['VIN']);
        $make = $make !== '' ? $make : (string)$existingVehicle['Make'];
        $model = $model !== '' ? $model : (string)$existingVehicle['Model'];
        $year = $year !== '' ? $year : (string)$existingVehicle['Year'];
        $color = $color !== '' ? $color : (string)$existingVehicle['Color'];
        $engine = $engine !== '' ? $engine : (string)$existingVehicle['Engine'];
        $detail = $detail !== '' ? $detail : (string)$existingVehicle['Detail'];
        $notes = trim(($notes !== '' ? $notes . ' | ' : '') . 'Returning vehicle candidate CVID: ' . (int)$input['cvid']);
    }

    $hasVehicleData = ($plate !== '' || $vin !== '' || $make !== '' || $model !== '');

    if ($hasVehicleData || $towing === 1) {
        $draftVehicleId = $db->insert(
            "INSERT INTO draft_vehicles
                (draft_customer_id, plate, vin, make, model, year, color, engine, detail, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $draftCustomerId,
                $plate,
                $vin,
                $make,
                $model,
                $year,
                $color,
                $engine,
                $detail,
                $draftStatus
            ]
        );

        if (!$draftVehicleId) {
            throw new Exception('Unable to create draft vehicle');
        }
    }

    // Step 3: draft work order
    $draftWoid = $db->insert(
        "INSERT INTO draft_work_orders
            (draft_customer_id, draft_vehicle_id, mileage, wo_date, wo_req1, wo_req2, wo_req3, wo_req4, wo_req5,
             wo_status, wo_note, customer_note, admin_note, mechanic_note, req1, req2, req3, req4, req5,
             priority, mechanic, admin, testdrive, checksum, status, created_by_user_id)
         VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $draftCustomerId,
            $draftVehicleId,
            trim((string)($input['mileage'] ?? '')),
            $requestLines[0],
            $requestLines[1],
            $requestLines[2],
            $requestLines[3],
            $requestLines[4],
            'NEW',
            $woNote,
            '',
            '',
            '',
            0,
            0,
            0,
            0,
            0,
            PRIORITY_NORMAL,
            '',
            Session::getUsername(),
            $towing,
            0,
            $draftStatus,
            $createdByUserId ?: null
        ]
    );

    if (!$draftWoid) {
        throw new Exception('Unable to create draft work order');
    }

    $validation = updateDraftValidationSnapshot($conn, (int)$draftWoid, $createdByUserId ?: null);
    recordDraftStatusLog(
        $conn,
        (int)$draftWoid,
        null,
        'draft',
        'created',
        (int)$createdByUserId,
        'Draft intake created',
        [
            'mode' => $mode,
            'vehicle_mode' => $vehicleMode,
            'ready' => $validation['ready'],
            'missing_reasons' => $validation['missing']
        ]
    );

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Draft intake created successfully',
        'draft_customer_id' => (int)$draftCustomerId,
        'draft_vehicle_id' => $draftVehicleId ? (int)$draftVehicleId : null,
        'draft_wo_id' => (int)$draftWoid,
        'readiness_state' => $validation['readiness_state'],
        'missing_reasons' => $validation['missing'],
        'escalation_level' => $validation['escalation_level']
    ]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    error_log('[submit_intake] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create draft intake right now. Please verify required draft data and try again.']);
}
