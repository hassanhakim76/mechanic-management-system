<?php
/**
 * Multi-point vehicle inspection model.
 */

class VehicleInspection {
    private $db;
    private $pdo;
    private $lastError = '';
    private array $lastValidationErrors = [];

    private const RATINGS = ['good', 'watch', 'repair', 'na'];

    public static function formatItemCode(array $item): string {
        $categoryCode = (int)($item['category_code'] ?? 0);
        $itemCode = (int)($item['item_code'] ?? 0);

        if ($categoryCode > 0 && $itemCode > 0) {
            return $categoryCode . '.' . str_pad((string)$itemCode, 2, '0', STR_PAD_LEFT);
        }

        $legacyNumber = (int)($item['item_number'] ?? 0);
        if ($legacyNumber >= 100) {
            $derivedCategoryCode = intdiv($legacyNumber, 100);
            $derivedItemCode = $legacyNumber % 100;
            if ($derivedCategoryCode > 0 && $derivedItemCode > 0) {
                return $derivedCategoryCode . '.' . str_pad((string)$derivedItemCode, 2, '0', STR_PAD_LEFT);
            }
        }

        return $legacyNumber > 0 ? (string)$legacyNumber : '';
    }

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getLastValidationErrors() {
        return $this->lastValidationErrors;
    }

    public function getById($inspectionId) {
        return $this->db->querySingle(
            "SELECT vi.*, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage,
                    c.FirstName, c.LastName, c.Phone, c.Cell, c.Email,
                    cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color, cv.Engine
             FROM vehicle_inspections vi
             JOIN work_order wo ON vi.WOID = wo.WOID
             JOIN customers c ON wo.CustomerID = c.CustomerID
             LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
             WHERE vi.inspection_id = ?",
            [(int)$inspectionId]
        );
    }

    public function getByWorkOrder($woid) {
        return $this->db->querySingle(
            "SELECT vi.*, wo.WO_Date, wo.WO_Status, wo.Priority, wo.Mileage,
                    c.FirstName, c.LastName, c.Phone, c.Cell, c.Email,
                    cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color, cv.Engine
             FROM vehicle_inspections vi
             JOIN work_order wo ON vi.WOID = wo.WOID
             JOIN customers c ON wo.CustomerID = c.CustomerID
             LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
             WHERE vi.WOID = ?",
            [(int)$woid]
        );
    }

    public function getOrCreateForWorkOrder($woid) {
        $this->lastError = '';
        $existing = $this->getByWorkOrder($woid);
        if ($existing) {
            if (($existing['status'] ?? '') !== 'completed') {
                $this->syncActiveTemplateItems((int)$existing['inspection_id']);
                return $this->getById((int)$existing['inspection_id']);
            }
            return $existing;
        }

        $woModel = new WorkOrder();
        $wo = $woModel->getById((int)$woid);
        if (!$wo) {
            $this->lastError = 'Work order not found.';
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO vehicle_inspections
                    (WOID, CVID, CustomerID, mechanic, mileage_at_inspect, status, created_by, created_at)
                 VALUES
                    (?, ?, ?, ?, ?, 'in_progress', ?, NOW())"
            );
            $stmt->execute([
                (int)$wo['WOID'],
                isset($wo['CVID']) ? (int)$wo['CVID'] : null,
                isset($wo['CustomerID']) ? (int)$wo['CustomerID'] : null,
                trim((string)($wo['Mechanic'] ?? '')),
                trim((string)($wo['Mileage'] ?? '')),
                trim((string)Session::getUsername())
            ]);

            $inspectionId = (int)$this->pdo->lastInsertId();
            $this->createSnapshotItems($inspectionId);

            $this->pdo->commit();
            return $this->getById($inspectionId);
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function syncActiveTemplateItems($inspectionId) {
        $this->lastError = '';
        $inspection = $this->getById((int)$inspectionId);
        if (!$inspection) {
            $this->lastError = 'Inspection not found.';
            return false;
        }

        if (($inspection['status'] ?? '') === 'completed') {
            return true;
        }

        try {
            $this->pdo->beginTransaction();

            $updateStmt = $this->pdo->prepare(
                "UPDATE vehicle_inspection_items vii
                 JOIN inspection_item_master im ON vii.master_item_id = im.master_item_id
                 JOIN inspection_categories ic ON im.category_id = ic.category_id
                 SET vii.category_id = im.category_id,
                     vii.category_code = ic.category_code,
                     vii.item_code = im.item_code,
                     vii.category_name = ic.category_name,
                     vii.item_number = im.item_number,
                     vii.item_label = im.item_label,
                     vii.check_description = im.check_description,
                     vii.display_order = im.display_order,
                     vii.updated_at = NOW()
                 WHERE vii.inspection_id = ?
                   AND im.active = 1
                   AND ic.active = 1"
            );
            $updateStmt->execute([(int)$inspectionId]);

            $insertStmt = $this->pdo->prepare(
                "INSERT INTO vehicle_inspection_items
                    (inspection_id, master_item_id, category_id, category_code, item_code, category_name, item_number, item_label,
                     check_description, display_order)
                 SELECT
                    ?, im.master_item_id, im.category_id, ic.category_code, im.item_code, ic.category_name, im.item_number, im.item_label,
                    im.check_description, im.display_order
                 FROM inspection_item_master im
                 JOIN inspection_categories ic ON im.category_id = ic.category_id
                 WHERE im.active = 1
                   AND ic.active = 1
                   AND NOT EXISTS (
                        SELECT 1
                        FROM vehicle_inspection_items vii
                        WHERE vii.inspection_id = ?
                          AND vii.master_item_id = im.master_item_id
                   )
                 ORDER BY ic.display_order ASC, ic.category_code ASC, im.display_order ASC, im.item_code ASC, im.item_number ASC"
            );
            $insertStmt->execute([(int)$inspectionId, (int)$inspectionId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getItems($inspectionId) {
        return $this->db->query(
            "SELECT vii.*
             FROM vehicle_inspection_items vii
             JOIN vehicle_inspections vi ON vii.inspection_id = vi.inspection_id
             LEFT JOIN inspection_item_master im ON vii.master_item_id = im.master_item_id
             LEFT JOIN inspection_categories ic ON im.category_id = ic.category_id
             WHERE vii.inspection_id = ?
               AND (
                    vi.status = 'completed'
                    OR (
                        COALESCE(im.active, 1) = 1
                        AND COALESCE(ic.active, 1) = 1
                    )
               )
             ORDER BY category_code ASC, display_order ASC, item_code ASC, item_number ASC",
            [(int)$inspectionId]
        ) ?: [];
    }

    public function getItemsByCategory($inspectionId) {
        $items = $this->getItems($inspectionId);
        $grouped = [];
        foreach ($items as $item) {
            $category = (string)$item['category_name'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $item;
        }
        return $grouped;
    }

    public function save($inspectionId, array $items, $overallNotes = null) {
        $this->lastError = '';
        $this->lastValidationErrors = [];

        $inspection = $this->getById($inspectionId);
        if (!$inspection) {
            $this->lastError = 'Inspection not found.';
            return false;
        }

        if (($inspection['status'] ?? '') === 'completed') {
            $this->lastError = 'Completed inspections must be reopened before editing.';
            return false;
        }

        try {
            $this->pdo->beginTransaction();
            $this->saveItemRows($inspectionId, $items);
            $this->updateHeader($inspectionId, $overallNotes, false);
            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function complete($inspectionId, array $items, $overallNotes = null) {
        $this->lastError = '';
        $this->lastValidationErrors = [];

        $inspection = $this->getById($inspectionId);
        if (!$inspection) {
            $this->lastError = 'Inspection not found.';
            return false;
        }

        if (($inspection['status'] ?? '') === 'completed') {
            return true;
        }

        try {
            $this->pdo->beginTransaction();
            $this->saveItemRows($inspectionId, $items);
            $this->updateHeader($inspectionId, $overallNotes, false);

            $errors = $this->validateForCompletion($inspectionId);
            if (!empty($errors)) {
                $this->lastValidationErrors = $errors;
                $this->pdo->commit();
                return false;
            }

            $stmt = $this->pdo->prepare(
                "UPDATE vehicle_inspections
                 SET status = 'completed',
                     completed_at = NOW(),
                     updated_at = NOW()
                 WHERE inspection_id = ?"
            );
            $stmt->execute([(int)$inspectionId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function reopen($inspectionId) {
        $stmt = $this->pdo->prepare(
            "UPDATE vehicle_inspections
             SET status = 'in_progress',
                 completed_at = NULL,
                 updated_at = NOW()
             WHERE inspection_id = ?"
        );
        return $stmt->execute([(int)$inspectionId]);
    }

    public function getSummaryCounts($inspectionId) {
        $counts = [
            'good' => 0,
            'watch' => 0,
            'repair' => 0,
            'na' => 0,
            'unrated' => 0
        ];

        $rows = $this->db->query(
            "SELECT COALESCE(vii.rating, 'unrated') AS rating_key, COUNT(*) AS rating_count
             FROM vehicle_inspection_items vii
             JOIN vehicle_inspections vi ON vii.inspection_id = vi.inspection_id
             LEFT JOIN inspection_item_master im ON vii.master_item_id = im.master_item_id
             LEFT JOIN inspection_categories ic ON im.category_id = ic.category_id
             WHERE vii.inspection_id = ?
               AND (
                    vi.status = 'completed'
                    OR (
                        COALESCE(im.active, 1) = 1
                        AND COALESCE(ic.active, 1) = 1
                    )
               )
             GROUP BY COALESCE(rating, 'unrated')",
            [(int)$inspectionId]
        ) ?: [];

        foreach ($rows as $row) {
            $key = (string)$row['rating_key'];
            if (isset($counts[$key])) {
                $counts[$key] = (int)$row['rating_count'];
            }
        }

        return $counts;
    }

    public function getRecommendations($inspectionId) {
        return $this->db->query(
            "SELECT vii.*
             FROM vehicle_inspection_items vii
             JOIN vehicle_inspections vi ON vii.inspection_id = vi.inspection_id
             LEFT JOIN inspection_item_master im ON vii.master_item_id = im.master_item_id
             LEFT JOIN inspection_categories ic ON im.category_id = ic.category_id
             WHERE vii.inspection_id = ?
               AND vii.rating IN ('watch', 'repair')
               AND (
                    vi.status = 'completed'
                    OR (
                        COALESCE(im.active, 1) = 1
                        AND COALESCE(ic.active, 1) = 1
                    )
               )
             ORDER BY FIELD(rating, 'repair', 'watch'), category_code ASC, display_order ASC, item_code ASC, item_number ASC",
            [(int)$inspectionId]
        ) ?: [];
    }

    public function getPreviousByVehicle($cvid, $excludeInspectionId = null, $limit = 10) {
        if ((int)$cvid <= 0) {
            return [];
        }

        $params = [(int)$cvid];
        $sql = "SELECT vi.*, wo.WO_Date, wo.WO_Status
                FROM vehicle_inspections vi
                JOIN work_order wo ON vi.WOID = wo.WOID
                WHERE vi.CVID = ?";

        if ($excludeInspectionId) {
            $sql .= " AND vi.inspection_id <> ?";
            $params[] = (int)$excludeInspectionId;
        }

        $sql .= " ORDER BY vi.created_at DESC, vi.inspection_id DESC LIMIT ?";
        $params[] = (int)$limit;

        return $this->db->query($sql, $params) ?: [];
    }

    public function getTemplateCategoriesWithItems($includeInactive = false) {
        $categoryWhere = $includeInactive ? '1=1' : 'active = 1';
        $itemWhere = $includeInactive ? '1=1' : 'im.active = 1';

        $categories = $this->db->query(
            "SELECT *
             FROM inspection_categories
             WHERE {$categoryWhere}
             ORDER BY display_order ASC, category_code ASC, category_name ASC"
        ) ?: [];

        $items = $this->db->query(
            "SELECT im.*, ic.category_name
             FROM inspection_item_master im
             JOIN inspection_categories ic ON im.category_id = ic.category_id
             WHERE {$itemWhere}
             ORDER BY ic.display_order ASC, ic.category_code ASC, im.display_order ASC, im.item_code ASC, im.item_number ASC"
        ) ?: [];

        foreach ($categories as &$category) {
            $category['items'] = [];
            foreach ($items as $item) {
                if ((int)$item['category_id'] === (int)$category['category_id']) {
                    $category['items'][] = $item;
                }
            }
        }
        unset($category);

        return $categories;
    }

    public function saveCategory(array $data) {
        $id = (int)($data['category_id'] ?? 0);
        $categoryCode = (int)($data['category_code'] ?? 0);
        $name = trim((string)($data['category_name'] ?? ''));
        $displayOrder = (int)($data['display_order'] ?? 0);
        $active = !empty($data['active']) ? 1 : 0;

        if ($name === '') {
            $this->lastError = 'Category name is required.';
            return false;
        }

        if ($categoryCode <= 0) {
            $categoryCode = $id > 0 ? $this->getExistingCategoryCode($id) : $this->getNextCategoryCode();
        }

        if ($displayOrder <= 0) {
            $displayOrder = $categoryCode * 10;
        }

        if ($this->categoryCodeExists($categoryCode, $id)) {
            $this->lastError = 'Category code is already used.';
            return false;
        }

        if ($id > 0) {
            $ok = $this->db->execute(
                "UPDATE inspection_categories
                 SET category_code = ?, category_name = ?, display_order = ?, active = ?, updated_at = NOW()
                 WHERE category_id = ?",
                [$categoryCode, $name, $displayOrder, $active, $id]
            );
            if ($ok) {
                $this->refreshTemplateItemNumbersForCategory($id);
            }
            return $ok;
        }

        return $this->db->insert(
            "INSERT INTO inspection_categories (category_code, category_name, display_order, active)
             VALUES (?, ?, ?, ?)",
            [$categoryCode, $name, $displayOrder, $active]
        );
    }

    public function saveTemplateItem(array $data) {
        $id = (int)($data['master_item_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        $itemCode = (int)($data['item_code'] ?? ($data['item_number'] ?? 0));
        $label = trim((string)($data['item_label'] ?? ''));
        $description = trim((string)($data['check_description'] ?? ''));
        $displayOrder = (int)($data['display_order'] ?? 0);
        $active = !empty($data['active']) ? 1 : 0;

        if ($categoryId <= 0 || $itemCode <= 0 || $label === '') {
            $this->lastError = 'Category, item code, and item label are required.';
            return false;
        }

        $category = $this->getCategoryById($categoryId);
        if (!$category) {
            $this->lastError = 'Category not found.';
            return false;
        }

        if ($displayOrder <= 0) {
            $displayOrder = $itemCode * 10;
        }

        if ($this->itemCodeExists($categoryId, $itemCode, $id)) {
            $this->lastError = 'Item code is already used in this category.';
            return false;
        }

        $itemNumber = $this->legacyItemNumber((int)$category['category_code'], $itemCode);

        if ($id > 0) {
            return $this->db->execute(
                "UPDATE inspection_item_master
                 SET category_id = ?, item_code = ?, item_number = ?, item_label = ?, check_description = ?,
                     display_order = ?, active = ?, updated_at = NOW()
                 WHERE master_item_id = ?",
                [$categoryId, $itemCode, $itemNumber, $label, $description, $displayOrder, $active, $id]
            );
        }

        return $this->db->insert(
            "INSERT INTO inspection_item_master
                (category_id, item_code, item_number, item_label, check_description, display_order, active)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$categoryId, $itemCode, $itemNumber, $label, $description, $displayOrder, $active]
        );
    }

    public function getNextCategoryCode(): int {
        $row = $this->db->querySingle("SELECT COALESCE(MAX(category_code), 0) + 1 AS next_code FROM inspection_categories");
        return max(1, (int)($row['next_code'] ?? 1));
    }

    public function getNextItemCodeForCategory($categoryId): int {
        $row = $this->db->querySingle(
            "SELECT COALESCE(MAX(item_code), 0) + 1 AS next_code
             FROM inspection_item_master
             WHERE category_id = ?",
            [(int)$categoryId]
        );
        return max(1, (int)($row['next_code'] ?? 1));
    }

    public function getNextDisplayOrderForCategory($categoryId): int {
        $nextItemCode = $this->getNextItemCodeForCategory($categoryId);
        return $nextItemCode * 10;
    }

    public function setCategoryActive($categoryId, $active) {
        $id = (int)$categoryId;
        if ($id <= 0) {
            $this->lastError = 'Category ID is required.';
            return false;
        }

        return $this->db->execute(
            "UPDATE inspection_categories
             SET active = ?, updated_at = NOW()
             WHERE category_id = ?",
            [!empty($active) ? 1 : 0, $id]
        );
    }

    public function setTemplateItemActive($masterItemId, $active) {
        $id = (int)$masterItemId;
        if ($id <= 0) {
            $this->lastError = 'Inspection item ID is required.';
            return false;
        }

        return $this->db->execute(
            "UPDATE inspection_item_master
             SET active = ?, updated_at = NOW()
             WHERE master_item_id = ?",
            [!empty($active) ? 1 : 0, $id]
        );
    }

    private function createSnapshotItems($inspectionId) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO vehicle_inspection_items
                (inspection_id, master_item_id, category_id, category_code, item_code, category_name, item_number, item_label,
                 check_description, display_order)
             SELECT
                ?, im.master_item_id, im.category_id, ic.category_code, im.item_code, ic.category_name, im.item_number, im.item_label,
                im.check_description, im.display_order
             FROM inspection_item_master im
             JOIN inspection_categories ic ON im.category_id = ic.category_id
             WHERE im.active = 1 AND ic.active = 1
             ORDER BY ic.display_order ASC, ic.category_code ASC, im.display_order ASC, im.item_code ASC, im.item_number ASC"
        );
        $stmt->execute([(int)$inspectionId]);
    }

    private function saveItemRows($inspectionId, array $items) {
        $stmt = $this->pdo->prepare(
            "UPDATE vehicle_inspection_items
             SET rating = ?, note = ?, updated_at = NOW()
             WHERE inspection_item_id = ? AND inspection_id = ?"
        );

        foreach ($items as $itemId => $itemData) {
            $rating = strtolower(trim((string)($itemData['rating'] ?? '')));
            if ($rating === '') {
                $rating = null;
            }
            if ($rating !== null && !in_array($rating, self::RATINGS, true)) {
                $rating = null;
            }

            $note = trim((string)($itemData['note'] ?? ''));
            $stmt->execute([$rating, $note, (int)$itemId, (int)$inspectionId]);
        }
    }

    private function updateHeader($inspectionId, $overallNotes, $complete) {
        $stmt = $this->pdo->prepare(
            "UPDATE vehicle_inspections
             SET overall_notes = ?, updated_at = NOW()
             WHERE inspection_id = ?"
        );
        $stmt->execute([trim((string)$overallNotes), (int)$inspectionId]);
    }

    private function validateForCompletion($inspectionId) {
        $errors = [];
        $items = $this->getItems($inspectionId);
        foreach ($items as $item) {
            $label = 'Item ' . self::formatItemCode($item) . ' - ' . $item['item_label'];
            $rating = (string)($item['rating'] ?? '');
            $note = trim((string)($item['note'] ?? ''));

            if ($rating === '') {
                $errors[] = $label . ' needs a rating.';
            } elseif (in_array($rating, ['watch', 'repair'], true) && $note === '') {
                $errors[] = $label . ' needs a note for ' . strtoupper($rating) . '.';
            }
        }
        return $errors;
    }

    private function getCategoryById($categoryId) {
        return $this->db->querySingle(
            "SELECT *
             FROM inspection_categories
             WHERE category_id = ?",
            [(int)$categoryId]
        );
    }

    private function getExistingCategoryCode($categoryId): int {
        $category = $this->getCategoryById($categoryId);
        return (int)($category['category_code'] ?? 0);
    }

    private function categoryCodeExists($categoryCode, $excludeCategoryId = 0): bool {
        $params = [(int)$categoryCode];
        $sql = "SELECT COUNT(*) AS cnt
                FROM inspection_categories
                WHERE category_code = ?";

        if ((int)$excludeCategoryId > 0) {
            $sql .= " AND category_id <> ?";
            $params[] = (int)$excludeCategoryId;
        }

        $row = $this->db->querySingle($sql, $params);
        return (int)($row['cnt'] ?? 0) > 0;
    }

    private function itemCodeExists($categoryId, $itemCode, $excludeMasterItemId = 0): bool {
        $params = [(int)$categoryId, (int)$itemCode];
        $sql = "SELECT COUNT(*) AS cnt
                FROM inspection_item_master
                WHERE category_id = ? AND item_code = ?";

        if ((int)$excludeMasterItemId > 0) {
            $sql .= " AND master_item_id <> ?";
            $params[] = (int)$excludeMasterItemId;
        }

        $row = $this->db->querySingle($sql, $params);
        return (int)($row['cnt'] ?? 0) > 0;
    }

    private function legacyItemNumber($categoryCode, $itemCode): int {
        return ((int)$categoryCode * 100) + (int)$itemCode;
    }

    private function refreshTemplateItemNumbersForCategory($categoryId): void {
        $category = $this->getCategoryById($categoryId);
        if (!$category) {
            return;
        }

        $items = $this->db->query(
            "SELECT master_item_id, item_code
             FROM inspection_item_master
             WHERE category_id = ?
             ORDER BY display_order ASC, item_code ASC, master_item_id ASC",
            [(int)$categoryId]
        ) ?: [];

        $stmt = $this->pdo->prepare(
            "UPDATE inspection_item_master
             SET item_number = ?, updated_at = NOW()
             WHERE master_item_id = ?"
        );

        foreach ($items as $item) {
            $stmt->execute([
                $this->legacyItemNumber((int)$category['category_code'], (int)$item['item_code']),
                (int)$item['master_item_id']
            ]);
        }
    }
}
