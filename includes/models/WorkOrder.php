<?php
/**
 * WorkOrder Model
 * Handles work order data operations (Core module)
 */

class WorkOrder {
    private $db;
    private $lastError = '';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Get the last model-level error (if any).
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Get work order by ID with all related data
     */
    public function getById($woid) {
        $sql = "SELECT wo.*, 
                c.FirstName, c.LastName, c.Phone, c.Cell, c.Email, c.Address, c.City, c.Province, c.PostalCode,
                cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color, cv.Engine
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE wo.WOID = ?";
        
        return $this->db->querySingle($sql, [$woid]);
    }
    
    /**
     * Get work orders list with filters (Admin view)
     */
    public function getList($filters = []) {
        $sql = "SELECT wo.WOID, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage, wo.Mechanic, wo.Admin,
                wo.WO_Req1, wo.WO_Req2, wo.WO_Req3, wo.WO_Req4, wo.WO_Req5,
                wo.Customer_Note, wo.Admin_Note, wo.Mechanic_Note, wo.TestDrive,
                c.FirstName, c.LastName, c.Phone, c.Cell, c.Email,
                cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE 1=1";
        
        $params = [];
        $this->applyAdminFilters($sql, $params, $filters, true);
        
        $sql .= " ORDER BY wo.WO_Date DESC, wo.WOID DESC";
        
        // Limit
        if (isset($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = (int)$filters['limit'];
        }
        
        return $this->db->query($sql, $params);
    }

    /**
     * Get admin work order counts by status for the current filter context.
     */
    public function getStatusCounts($filters = []) {
        $sql = "SELECT
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN wo.WO_Status = ? THEN 1 ELSE 0 END) AS new_count,
                    SUM(CASE WHEN wo.WO_Status = ? THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN wo.WO_Status = ? THEN 1 ELSE 0 END) AS billing_count,
                    SUM(CASE WHEN wo.WO_Status = ? THEN 1 ELSE 0 END) AS onhold_count
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE 1=1";

        $params = [
            STATUS_NEW,
            STATUS_PENDING,
            STATUS_BILLING,
            STATUS_ONHOLD
        ];

        $this->applyAdminFilters($sql, $params, $filters, false);

        $result = $this->db->querySingle($sql, $params);

        return [
            'All' => (int)($result['total_count'] ?? 0),
            STATUS_NEW => (int)($result['new_count'] ?? 0),
            STATUS_PENDING => (int)($result['pending_count'] ?? 0),
            STATUS_BILLING => (int)($result['billing_count'] ?? 0),
            STATUS_ONHOLD => (int)($result['onhold_count'] ?? 0)
        ];
    }

    /**
     * Get work orders for a vehicle VIN (Admin view)
     */
    public function getByVin($vin, $limit = 200) {
        $sql = "SELECT wo.WOID, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage, wo.Mechanic, wo.Admin,
                wo.WO_Req1, wo.WO_Req2, wo.WO_Req3, wo.WO_Req4, wo.WO_Req5,
                wo.Customer_Note, wo.Admin_Note, wo.Mechanic_Note,
                c.FirstName, c.LastName, c.Phone, c.Cell, c.Email,
                cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE cv.VIN = ?
                ORDER BY wo.WO_Date DESC, wo.WOID DESC
                LIMIT ?";
        
        return $this->db->query($sql, [$vin, (int)$limit]);
    }
    
    /**
     * Get work orders for mechanic view (split into NEW and PENDING)
     */
    public function getMechanicWorkOrders($mechanic = null) {
        // NEW work orders (unassigned)
        $sqlNew = "SELECT wo.WOID, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage,
                   wo.WO_Req1, wo.WO_Req2, wo.WO_Req3, wo.WO_Req4, wo.WO_Req5,
                   wo.Customer_Note, wo.Admin_Note, wo.Mechanic_Note,
                   c.FirstName, c.LastName, c.Phone, c.Cell,
                   cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color
                   FROM work_order wo
                   JOIN customers c ON wo.CustomerID = c.CustomerID
                   LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                   WHERE wo.WO_Status = ? AND (wo.Mechanic IS NULL OR wo.Mechanic = '')
                   ORDER BY wo.WO_Date DESC, wo.WOID DESC";
        
        $new = $this->db->query($sqlNew, [STATUS_NEW]);
        
        // PENDING work orders (assigned to mechanic)
        $sqlPending = "SELECT wo.WOID, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage,
                       wo.WO_Req1, wo.WO_Req2, wo.WO_Req3, wo.WO_Req4, wo.WO_Req5,
                       wo.Customer_Note, wo.Admin_Note, wo.Mechanic_Note,
                       c.FirstName, c.LastName, c.Phone, c.Cell,
                       cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color
                       FROM work_order wo
                       JOIN customers c ON wo.CustomerID = c.CustomerID
                       LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                       WHERE wo.WO_Status = ?";
        
        $params = [STATUS_PENDING];
        
        if ($mechanic) {
            $sqlPending .= " AND wo.Mechanic = ?";
            $params[] = $mechanic;
        }
        
        $sqlPending .= " ORDER BY wo.WO_Date DESC, wo.WOID DESC";
        
        $pending = $this->db->query($sqlPending, $params);
        
        return [
            'new' => $new,
            'pending' => $pending
        ];
    }
    
    /**
     * Build search condition based on field and operator
     */
    private function buildSearchCondition($field, $operator, &$params, $value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $targets = $this->getAdminSearchTargets($field);
        if (empty($targets)) {
            return '';
        }

        $conditionParts = [];
        foreach ($targets as $target) {
            $this->appendSearchCondition(
                $conditionParts,
                $params,
                $target['expr'],
                $operator,
                $value,
                $target['params'] ?? []
            );
        }

        if ($field === 'All') {
            $digitsOnly = preg_replace('/\D+/', '', $value);
            $looksNumericSearch = (bool)preg_match('/^[\d\s().+\-]+$/', $value);
            if ($looksNumericSearch && $digitsOnly !== '') {
                foreach (['c.Phone', 'c.Cell', 'CAST(wo.WOID AS CHAR)', 'CAST(c.CustomerID AS CHAR)', 'CAST(cv.CVID AS CHAR)'] as $expr) {
                    $this->appendSearchCondition($conditionParts, $params, $expr, $operator, $digitsOnly);
                }
            }

            $compactValue = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $value));
            if ($compactValue !== '') {
                $compactPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', WO_PREFIX));
                $compactTargets = [
                    [
                        'expr' => "UPPER(CONCAT(?, LPAD(wo.WOID, ?, '0')))",
                        'params' => [$compactPrefix, (int)WO_NUMBER_LENGTH]
                    ],
                    [
                        'expr' => "LPAD(wo.WOID, ?, '0')",
                        'params' => [(int)WO_NUMBER_LENGTH]
                    ]
                ];

                foreach ($compactTargets as $target) {
                    $this->appendSearchCondition(
                        $conditionParts,
                        $params,
                        $target['expr'],
                        $operator,
                        $compactValue,
                        $target['params'] ?? []
                    );
                }
            }
        }

        return empty($conditionParts) ? '' : " AND (" . implode(" OR ", $conditionParts) . ")";
    }

    /**
     * Searchable fields for the admin Find modal.
     */
    private function getAdminSearchTargets($field) {
        $fieldMap = [
            'WorkOrder' => [
                [
                    'expr' => "CONCAT(?, LPAD(wo.WOID, ?, '0'))",
                    'params' => [WO_PREFIX, (int)WO_NUMBER_LENGTH]
                ],
                ['expr' => 'CAST(wo.WOID AS CHAR)']
            ],
            'Customer' => [
                ['expr' => "COALESCE(c.FirstName, '')"],
                ['expr' => "COALESCE(c.LastName, '')"],
                ['expr' => "CONCAT_WS(' ', c.FirstName, c.LastName)"],
                ['expr' => "CONCAT_WS(' ', c.LastName, c.FirstName)"],
                ['expr' => "CAST(c.CustomerID AS CHAR)"]
            ],
            'Vehicle' => [
                ['expr' => "COALESCE(cv.Plate, '')"],
                ['expr' => "COALESCE(cv.VIN, '')"],
                ['expr' => "COALESCE(cv.Make, '')"],
                ['expr' => "COALESCE(cv.Model, '')"],
                ['expr' => "COALESCE(cv.Year, '')"],
                ['expr' => "COALESCE(cv.Color, '')"],
                ['expr' => "CONCAT_WS(' ', cv.Year, cv.Make, cv.Model)"],
                ['expr' => "CONCAT_WS(' ', cv.Make, cv.Model)"],
                ['expr' => "CAST(cv.CVID AS CHAR)"]
            ],
            'Contact' => [
                ['expr' => "COALESCE(c.Phone, '')"],
                ['expr' => "COALESCE(c.Cell, '')"],
                ['expr' => "COALESCE(c.Email, '')"]
            ],
            'Work' => [
                ['expr' => "CONCAT_WS(' ', wo.WO_Req1, wo.WO_Req2, wo.WO_Req3, wo.WO_Req4, wo.WO_Req5)"],
                ['expr' => "COALESCE(wo.Customer_Note, '')"],
                ['expr' => "COALESCE(wo.Admin_Note, '')"],
                ['expr' => "COALESCE(wo.Mechanic_Note, '')"],
                ['expr' => "COALESCE(wo.WO_Note, '')"],
                ['expr' => "COALESCE(wo.Mechanic, '')"],
                ['expr' => "COALESCE(wo.Admin, '')"]
            ],
            'Plate' => [['expr' => "COALESCE(cv.Plate, '')"]],
            'Phone' => [['expr' => "COALESCE(c.Phone, '')"]],
            'Cell' => [['expr' => "COALESCE(c.Cell, '')"]],
            'VIN' => [['expr' => "COALESCE(cv.VIN, '')"]],
            'FirstName' => [['expr' => "COALESCE(c.FirstName, '')"]],
            'LastName' => [['expr' => "COALESCE(c.LastName, '')"]],
            'Email' => [['expr' => "COALESCE(c.Email, '')"]],
            'Make' => [['expr' => "COALESCE(cv.Make, '')"]],
            'Model' => [['expr' => "COALESCE(cv.Model, '')"]]
        ];

        if ($field !== 'All') {
            return $fieldMap[$field] ?? [];
        }

        $targets = [];
        foreach (['WorkOrder', 'Customer', 'Vehicle', 'Contact', 'Work'] as $group) {
            $targets = array_merge($targets, $fieldMap[$group]);
        }

        return $targets;
    }

    /**
     * Add one operator-aware search condition and its parameters.
     */
    private function appendSearchCondition(&$conditionParts, &$params, $expr, $operator, $value, $exprParams = []) {
        $collatedExpr = '(' . $expr . ') COLLATE utf8mb4_general_ci';
        $collatedParam = '(? COLLATE utf8mb4_general_ci)';

        switch ($operator) {
            case 'Equal':
                $conditionParts[] = "$collatedExpr = $collatedParam";
                $searchParam = $value;
                break;
            case 'Start With':
                $conditionParts[] = "$collatedExpr LIKE $collatedParam";
                $searchParam = $value . '%';
                break;
            case 'End With':
                $conditionParts[] = "$collatedExpr LIKE $collatedParam";
                $searchParam = '%' . $value;
                break;
            case 'Contain':
            default:
                $conditionParts[] = "$collatedExpr LIKE $collatedParam";
                $searchParam = '%' . $value . '%';
                break;
        }

        foreach ($exprParams as $exprParam) {
            $params[] = $exprParam;
        }
        $params[] = $searchParam;
    }

    /**
     * Apply shared admin list filters to a query.
     */
    private function applyAdminFilters(&$sql, &$params, $filters = [], $includeStatus = true) {
        if (isset($filters['hide_completed']) && $filters['hide_completed']) {
            // Support both legacy "COMPLETE" and "COMPLETED" values in data.
            $sql .= " AND wo.WO_Status NOT IN (?, ?)";
            $params[] = STATUS_COMPLETED;
            $params[] = 'COMPLETE';
        }

        if ($includeStatus && !empty($filters['status']) && $filters['status'] != 'All') {
            $sql .= " AND wo.WO_Status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search_value'])) {
            $sql .= $this->buildSearchCondition('All', $filters['search_operator'], $params, $filters['search_value']);
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(wo.WO_Date) >= ?";
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(wo.WO_Date) <= ?";
            $params[] = $filters['date_to'];
        }
    }
    
    /**
     * Create new work order
     */
    public function create($data) {
        $sql = "INSERT INTO work_order (
                    CustomerID, CVID, Mileage, WO_Date, WO_Status, Priority,
                    WO_Req1, WO_Req2, WO_Req3, WO_Req4, WO_Req5, WO_Note, Customer_Note,
                    Admin_Note, Mechanic_Note, Mechanic, Admin, TestDrive, checksum,
                    Req1, Req2, Req3, Req4, Req5,
                    WO_Action1, WO_Action2, WO_Action3, WO_Action4, WO_Action5
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        return $this->db->insert($sql, [
            $data['CustomerID'],
            $data['CVID'] ?? null,
            $data['Mileage'] ?? '',
            $data['WO_Date'] ?? now(),
            $data['WO_Status'] ?? STATUS_NEW,
            $data['Priority'] ?? PRIORITY_NORMAL,
            $data['WO_Req1'] ?? '',
            $data['WO_Req2'] ?? '',
            $data['WO_Req3'] ?? '',
            $data['WO_Req4'] ?? '',
            $data['WO_Req5'] ?? '',
            $data['WO_Note'] ?? '',
            $data['Customer_Note'] ?? '',
            $data['Admin_Note'] ?? '',
            $data['Mechanic_Note'] ?? '',
            $data['Mechanic'] ?? '',
            $data['Admin'] ?? '',
            isset($data['TestDrive']) ? 1 : 0,
            0, // checksum
            0, 0, 0, 0, 0, // Req1-5 checkboxes default to unchecked
            $data['WO_Action1'] ?? '',
            $data['WO_Action2'] ?? '',
            $data['WO_Action3'] ?? '',
            $data['WO_Action4'] ?? '',
            $data['WO_Action5'] ?? ''
        ]);
    }
    
    /**
     * Update work order
     */
    public function update($woid, $data, $options = []) {
        $this->lastError = '';

        $existing = $this->db->querySingle(
            "SELECT WO_Status, Customer_Note, Admin_Note,
                    WO_Action1, WO_Action2, WO_Action3, WO_Action4, WO_Action5
             FROM work_order WHERE WOID = ?",
            [$woid]
        );
        if (!$existing) {
            $this->lastError = 'Work order not found.';
            return false;
        }

        $currentStatus = $this->normalizeStatus($existing['WO_Status'] ?? '');
        $requestedStatus = $this->normalizeStatus($data['WO_Status'] ?? ($existing['WO_Status'] ?? STATUS_NEW));

        if ($this->isCompletedStatus($currentStatus) && !$this->isCompletedStatus($requestedStatus)) {
            $allowReopen = !empty($options['allow_reopen']);
            $reopenReason = trim((string)($options['reopen_reason'] ?? ''));

            if (!$allowReopen) {
                $this->lastError = 'Completed work orders require explicit reopen action.';
                return false;
            }

            if ($reopenReason === '') {
                $this->lastError = 'Reopen reason is required when reopening a completed work order.';
                return false;
            }

            $actor = trim((string)Session::getUsername());
            if ($actor === '') {
                $actor = 'system';
            }
            $reopenStamp = '[REOPENED ' . date('Y-m-d H:i') . ' by ' . $actor . '] ' . $reopenReason;
            $adminNoteBase = trim((string)($data['Admin_Note'] ?? ($existing['Admin_Note'] ?? '')));
            $data['Admin_Note'] = $adminNoteBase === '' ? $reopenStamp : ($adminNoteBase . PHP_EOL . $reopenStamp);
        }

        if (in_array($currentStatus, [
                $this->normalizeStatus(STATUS_BILLING),
                $this->normalizeStatus(STATUS_ONHOLD)
            ], true)
            && $requestedStatus === $this->normalizeStatus(STATUS_PENDING)) {
            $allowPendingReturn = !empty($options['allow_pending_return']);
            $pendingReturnReason = trim((string)($options['pending_return_reason'] ?? ''));
            $returnSourceStatus = $currentStatus === $this->normalizeStatus(STATUS_BILLING)
                ? STATUS_BILLING
                : STATUS_ONHOLD;

            if (!$allowPendingReturn) {
                $this->lastError = 'Returning an ' . $returnSourceStatus . ' work order to PENDING requires explicit override action.';
                return false;
            }

            if ($pendingReturnReason === '') {
                $this->lastError = 'Reason is required when returning an ' . $returnSourceStatus . ' work order to PENDING.';
                return false;
            }

            $actor = trim((string)Session::getUsername());
            if ($actor === '') {
                $actor = 'system';
            }
            $billingReturnStamp = '[RETURNED TO PENDING ' . date('Y-m-d H:i') . ' by ' . $actor . '] ' . $pendingReturnReason;
            $adminNoteBase = trim((string)($data['Admin_Note'] ?? ($existing['Admin_Note'] ?? '')));
            $data['Admin_Note'] = $adminNoteBase === '' ? $billingReturnStamp : ($adminNoteBase . PHP_EOL . $billingReturnStamp);
        }

        if (!$this->isCompletedStatus($currentStatus) && $this->isCompletedStatus($requestedStatus)) {
            if ($currentStatus !== $this->normalizeStatus(STATUS_BILLING)) {
                $this->lastError = 'Work orders can only be completed from BILLING.';
                return false;
            }
        }

        if ($currentStatus === $this->normalizeStatus(STATUS_PENDING)
            && $requestedStatus === $this->normalizeStatus(STATUS_BILLING)) {
            $mileageValue = trim((string)($data['Mileage'] ?? ''));
            if ($mileageValue === '') {
                $this->lastError = 'Mileage is required before submitting to billing.';
                return false;
            }
        }

        // Customer note ownership: front desk/admin only.
        if (!Session::isAdmin() && !Session::isFrontDesk()) {
            $data['Customer_Note'] = (string)($existing['Customer_Note'] ?? '');
        } elseif (!array_key_exists('Customer_Note', $data)) {
            $data['Customer_Note'] = (string)($existing['Customer_Note'] ?? '');
        }

        // Empty work-item text cannot be marked complete.
        for ($i = 1; $i <= 5; $i++) {
            $requestKey = 'WO_Req' . $i;
            $flagKey = 'Req' . $i;
            $actionKey = 'WO_Action' . $i;
            if (trim((string)($data[$requestKey] ?? '')) === '') {
                unset($data[$flagKey]);
                $data[$actionKey] = '';
            } else {
                if (!array_key_exists($actionKey, $data)) {
                    $data[$actionKey] = (string)($existing[$actionKey] ?? '');
                }
                $data[$actionKey] = trim((string)$data[$actionKey]);
            }
        }

        $requiresCheckedWorkItems =
            ($currentStatus === $this->normalizeStatus(STATUS_PENDING)
                && $requestedStatus === $this->normalizeStatus(STATUS_BILLING))
            || ($currentStatus === $this->normalizeStatus(STATUS_BILLING)
                && $this->isCompletedStatus($requestedStatus));

        if ($requiresCheckedWorkItems) {
            $missingChecks = $this->getUncheckedFilledWorkItems($data);
            if (!empty($missingChecks)) {
                $actionLabel = $requestedStatus === $this->normalizeStatus(STATUS_BILLING)
                    ? 'submit this work order to billing'
                    : 'complete this work order';
                $this->lastError = 'Check off all filled work items before you ' . $actionLabel . ': ' . implode(', ', $missingChecks) . '.';
                return false;
            }

            $missingActions = $this->getFilledWorkItemsWithoutAction($data);
            if (!empty($missingActions)) {
                $actionLabel = $requestedStatus === $this->normalizeStatus(STATUS_BILLING)
                    ? 'submit this work order to billing'
                    : 'complete this work order';
                $this->lastError = 'Add action taken notes for all filled work items before you ' . $actionLabel . ': ' . implode(', ', $missingActions) . '.';
                return false;
            }
        }

        $data['WO_Status'] = $requestedStatus === '' ? STATUS_NEW : $requestedStatus;

        $sql = "UPDATE work_order SET
                CustomerID = ?, CVID = ?, Mileage = ?, WO_Status = ?, Priority = ?,
                WO_Req1 = ?, WO_Req2 = ?, WO_Req3 = ?, WO_Req4 = ?, WO_Req5 = ?,
                WO_Note = ?, Customer_Note = ?, Admin_Note = ?, Mechanic_Note = ?,
                Mechanic = ?, Admin = ?, TestDrive = ?,
                Req1 = ?, Req2 = ?, Req3 = ?, Req4 = ?, Req5 = ?,
                WO_Action1 = ?, WO_Action2 = ?, WO_Action3 = ?, WO_Action4 = ?, WO_Action5 = ?
                WHERE WOID = ?";
        
        return $this->db->execute($sql, [
            $data['CustomerID'],
            $data['CVID'] ?? null,
            $data['Mileage'] ?? '',
            $data['WO_Status'],
            $data['Priority'] ?? PRIORITY_NORMAL,
            $data['WO_Req1'] ?? '',
            $data['WO_Req2'] ?? '',
            $data['WO_Req3'] ?? '',
            $data['WO_Req4'] ?? '',
            $data['WO_Req5'] ?? '',
            $data['WO_Note'] ?? '',
            $data['Customer_Note'] ?? '',
            $data['Admin_Note'] ?? '',
            $data['Mechanic_Note'] ?? '',
            $data['Mechanic'] ?? '',
            $data['Admin'] ?? Session::getUsername(),
            isset($data['TestDrive']) ? 1 : 0,
            isset($data['Req1']) ? 1 : 0,
            isset($data['Req2']) ? 1 : 0,
            isset($data['Req3']) ? 1 : 0,
            isset($data['Req4']) ? 1 : 0,
            isset($data['Req5']) ? 1 : 0,
            $data['WO_Action1'] ?? '',
            $data['WO_Action2'] ?? '',
            $data['WO_Action3'] ?? '',
            $data['WO_Action4'] ?? '',
            $data['WO_Action5'] ?? '',
            $woid
        ]);
    }

    private function normalizeStatus($status) {
        return strtoupper(trim((string)$status));
    }

    private function isCompletedStatus($status) {
        $normalized = $this->normalizeStatus($status);
        return in_array($normalized, [strtoupper(STATUS_COMPLETED), 'COMPLETE'], true);
    }

    private function getUncheckedFilledWorkItems(array $data) {
        $missing = [];
        for ($i = 1; $i <= 5; $i++) {
            $requestKey = 'WO_Req' . $i;
            $flagKey = 'Req' . $i;
            if (trim((string)($data[$requestKey] ?? '')) !== '' && empty($data[$flagKey])) {
                $missing[] = 'W.I. ' . $i;
            }
        }
        return $missing;
    }

    private function getFilledWorkItemsWithoutAction(array $data) {
        $missing = [];
        for ($i = 1; $i <= 5; $i++) {
            $requestKey = 'WO_Req' . $i;
            $actionKey = 'WO_Action' . $i;
            if (trim((string)($data[$requestKey] ?? '')) !== '' && trim((string)($data[$actionKey] ?? '')) === '') {
                $missing[] = 'W.I. ' . $i;
            }
        }
        return $missing;
    }
    
    /**
     * Delete work order
     */
    public function delete($woid) {
        // Related records with database-level foreign keys are removed by cascade.
        $sql = "DELETE FROM work_order WHERE WOID = ?";
        return $this->db->execute($sql, [$woid]);
    }
    
    /**
     * Get work order count
     */
    public function getCount($filters = []) {
        $sql = "SELECT COUNT(*) as cnt FROM work_order wo WHERE 1=1";
        $params = [];
        
        if (isset($filters['hide_completed']) && $filters['hide_completed']) {
            // Support both legacy "COMPLETE" and "COMPLETED" values in data.
            $sql .= " AND wo.WO_Status NOT IN (?, ?)";
            $params[] = STATUS_COMPLETED;
            $params[] = 'COMPLETE';
        }
        
        if (!empty($filters['status']) && $filters['status'] != 'All') {
            $sql .= " AND wo.WO_Status = ?";
            $params[] = $filters['status'];
        }
        
        $result = $this->db->querySingle($sql, $params);
        return $result['cnt'];
    }
    
    /**
     * Get work orders for specific period
     */
    public function getByPeriod($period = 'month') {
        $sql = "SELECT wo.*, c.FirstName, c.LastName, cv.Plate, cv.Make, cv.Model
                FROM work_order wo
                JOIN customers c ON wo.CustomerID = c.CustomerID
                LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
                WHERE ";
        
        if ($period == 'month') {
            $sql .= "MONTH(wo.WO_Date) = MONTH(CURRENT_DATE()) 
                     AND YEAR(wo.WO_Date) = YEAR(CURRENT_DATE())";
        } else if ($period == 'year') {
            $sql .= "YEAR(wo.WO_Date) = YEAR(CURRENT_DATE())";
        }
        
        $sql .= " ORDER BY wo.WO_Date DESC";
        
        return $this->db->query($sql);
    }
}
