<?php
/**
 * Vehicle Decode API (server-side proxy)
 * Keeps decode provider API key off the browser.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

Session::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$vin = strtoupper(trim((string)($_GET['vin'] ?? '')));
$vin = preg_replace('/[^A-Z0-9]/', '', $vin);

if (strlen($vin) < 11) {
    http_response_code(400);
    echo json_encode(['error' => 'VIN is too short']);
    exit;
}

if (DECODETHIS_API_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Vehicle decode provider is not configured']);
    exit;
}

$key = rawurlencode(DECODETHIS_API_KEY);
$vinEncoded = rawurlencode($vin);
$sources = [
    "https://www.decodethis.com/webservices/decodes/{$vinEncoded}/{$key}/1.json",
    "https://www.decodethis.com/webservices/decodes/{$vinEncoded}/{$key}/1.jsonp?callback=decode_cb"
];

$lastError = 'Vehicle decode failed';
$lastStatus = 0;

foreach ($sources as $url) {
    [$statusCode, $responseBody, $transportError] = fetchRemotePayload($url);

    if ($transportError !== '') {
        $lastError = 'Vehicle decode transport error';
        continue;
    }

    $lastStatus = $statusCode;
    if ($statusCode >= 400) {
        $lastError = 'Vehicle decode provider rejected the request';
        continue;
    }

    $payload = decodePayload($responseBody);
    if (!is_array($payload)) {
        $lastError = 'Vehicle decode response parsing failed';
        continue;
    }

    $decoded = mapDecodedVehicleData($payload);
    $decodeStatus = strtoupper(trim((string)($decoded['_decode_status'] ?? '')));
    if ($decodeStatus !== '' && $decodeStatus !== 'SUCCESS') {
        $lastError = 'Vehicle decode provider did not return success';
        continue;
    }

    unset($decoded['_decode_status']);
    $decoded['vin'] = $vin;

    $decoded['color_source'] = 'decoder';
    $decoded['color_unavailable'] = false;
    if (trim((string)($decoded['color'] ?? '')) === '') {
        $historyColor = lookupColorFromLocalHistory($vin);
        if ($historyColor !== '') {
            $decoded['color'] = $historyColor;
            $decoded['color_source'] = 'history';
        } else {
            $decoded['color'] = 'UNKNOWN';
            $decoded['color_source'] = 'fallback';
            $decoded['color_unavailable'] = true;
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $decoded
    ]);
    exit;
}

error_log('[decode_vehicle] VIN=' . $vin . ' status=' . $lastStatus . ' error=' . $lastError);
http_response_code($lastStatus === 401 ? 401 : 502);
echo json_encode([
    'error' => $lastStatus === 401
        ? 'Vehicle decode authorization failed. Check API key/subscription.'
        : 'Vehicle decode is temporarily unavailable.'
]);

/**
 * @return array{0:int,1:string,2:string}
 */
function fetchRemotePayload($url) {
    $timeout = defined('DECODETHIS_TIMEOUT_SECONDS') ? (int)DECODETHIS_TIMEOUT_SECONDS : 15;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => APP_NAME . ' VIN Decoder',
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, is_string($body) ? $body : '', $error ?: ''];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "User-Agent: " . APP_NAME . " VIN Decoder\r\n"
        ]
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d+)/i', $header, $m)) {
            $status = (int)$m[1];
            break;
        }
    }

    if ($body === false) {
        return [$status, '', 'stream_error'];
    }

    return [$status, $body, ''];
}

function decodePayload($payload) {
    $payload = trim((string)$payload);
    if ($payload === '') {
        return null;
    }

    $decoded = json_decode($payload, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/^[A-Za-z0-9_]+\s*\((.*)\)\s*;?\s*$/s', $payload, $m)) {
        $decodedJsonp = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedJsonp)) {
            return $decodedJsonp;
        }
    }

    return null;
}

function mapDecodedVehicleData(array $payload) {
    $decodeStatus = '';
    $vehicle = [];
    $equipMap = [];

    if (isset($payload['decode']) && is_array($payload['decode'])) {
        $decodeStatus = (string)($payload['decode']['status'] ?? '');
        if (isset($payload['decode']['vehicle'][0]) && is_array($payload['decode']['vehicle'][0])) {
            $vehicle = $payload['decode']['vehicle'][0];
        }
    }

    if (isset($vehicle['Equip']) && is_array($vehicle['Equip'])) {
        $equipMap = extractDecodeEquipMap($vehicle['Equip']);
    }

    $flat = [];
    flattenDecodeArray($payload, $flat);

    return [
        '_decode_status' => $decodeStatus,
        'year' => firstDecodedValue(
            $equipMap['model_year'] ?? '',
            $equipMap['year'] ?? '',
            $vehicle['year'] ?? '',
            pickDecodedValue($flat, ['year', 'modelyear', 'model_year', 'vehicleyear'])
        ),
        'make' => firstDecodedValue(
            $equipMap['make'] ?? '',
            $vehicle['make'] ?? '',
            pickDecodedValue($flat, ['make', 'manufacturer', 'vehiclemake', 'marque'])
        ),
        'model' => firstDecodedValue(
            $equipMap['model'] ?? '',
            $vehicle['model'] ?? '',
            pickDecodedValue($flat, ['model', 'vehiclemodel'])
        ),
        'trim' => firstDecodedValue(
            $equipMap['trim_level'] ?? '',
            $equipMap['trim'] ?? '',
            $equipMap['series'] ?? '',
            pickDecodedValue($flat, ['trim', 'series', 'submodel'])
        ),
        'engine' => firstDecodedValue(
            $vehicle['engine'] ?? '',
            $equipMap['engine'] ?? '',
            pickDecodedValue($flat, ['engine', 'enginesize', 'engine_size', 'engineconfiguration', 'enginecylinders', 'displacement'])
        ),
        'color' => firstDecodedValue(
            $vehicle['color'] ?? '',
            $equipMap['exterior_color'] ?? '',
            $equipMap['color'] ?? '',
            pickDecodedValue($flat, ['color', 'exteriorcolor', 'exterior_color', 'paint'])
        ),
        'body' => firstDecodedValue(
            $vehicle['body'] ?? '',
            $equipMap['body_style'] ?? '',
            pickDecodedValue($flat, ['body', 'bodystyle', 'body_style'])
        ),
        'fuel' => firstDecodedValue(
            $equipMap['fuel_type'] ?? '',
            pickDecodedValue($flat, ['fuel', 'fueltype', 'fuel_type'])
        ),
        'transmission' => firstDecodedValue(
            $equipMap['transmission_type'] ?? '',
            pickDecodedValue($flat, ['transmission', 'transmissiontype'])
        ),
        'drivetrain' => firstDecodedValue(
            $vehicle['driveline'] ?? '',
            $equipMap['driveline'] ?? '',
            pickDecodedValue($flat, ['drivetrain', 'drive', 'drivetype', 'driveline'])
        ),
    ];
}

function extractDecodeEquipMap(array $equip) {
    $out = [];
    foreach ($equip as $row) {
        if (!is_array($row)) {
            continue;
        }
        $name = trim((string)($row['name'] ?? ''));
        $value = trim((string)($row['value'] ?? ''));
        if ($name === '' || $value === '') {
            continue;
        }
        $key = strtolower($name);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string)$key, '_');
        if ($key !== '' && !isset($out[$key])) {
            $out[$key] = $value;
        }
    }
    return $out;
}

function flattenDecodeArray(array $input, array &$flat) {
    foreach ($input as $k => $v) {
        if (is_array($v)) {
            flattenDecodeArray($v, $flat);
            continue;
        }
        if (!is_scalar($v) || $v === null) {
            continue;
        }

        $key = strtolower((string)$k);
        $value = trim((string)$v);
        if ($key !== '' && $value !== '' && !array_key_exists($key, $flat)) {
            $flat[$key] = $value;
        }
    }
}

function firstDecodedValue(...$values) {
    foreach ($values as $value) {
        $v = trim((string)$value);
        if ($v !== '') {
            return $v;
        }
    }
    return '';
}

function lookupColorFromLocalHistory($vin) {
    $vin = strtoupper(trim((string)$vin));
    if ($vin === '') {
        return '';
    }

    try {
        $db = Database::getInstance();
        $row = $db->querySingle(
            "SELECT Color
             FROM customer_vehicle
             WHERE UPPER(VIN) = UPPER(?) AND TRIM(COALESCE(Color, '')) <> ''
             ORDER BY CVID DESC
             LIMIT 1",
            [$vin]
        );
        if (is_array($row)) {
            return trim((string)($row['Color'] ?? ''));
        }
    } catch (Throwable $e) {
        error_log('[decode_vehicle_lookup] ' . $e->getMessage());
    }

    return '';
}

function pickDecodedValue(array $flat, array $candidates) {
    foreach ($candidates as $key) {
        $k = strtolower($key);
        if (isset($flat[$k]) && $flat[$k] !== '') {
            return $flat[$k];
        }
    }

    foreach ($flat as $k => $value) {
        foreach ($candidates as $candidate) {
            if (strpos($k, strtolower($candidate)) !== false && $value !== '') {
                return $value;
            }
        }
    }

    return '';
}
