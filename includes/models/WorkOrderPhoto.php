<?php
/**
 * WorkOrderPhoto Model
 * Handles photo evidence attached to work orders and W.I. lines.
 */

class WorkOrderPhoto {
    private $pdo;
    private $lastError = '';

    private const STAGES = ['before', 'during', 'after', 'inspection', 'internal'];
    private const CATEGORIES = [
        'arrival' => 'Arrival',
        'odometer' => 'Odometer',
        'vin_plate' => 'VIN / Plate',
        'exterior' => 'Exterior',
        'interior' => 'Interior',
        'damage' => 'Damage',
        'leak' => 'Leak',
        'dashboard' => 'Dashboard',
        'undercarriage' => 'Undercarriage',
        'inspection' => 'Inspection',
        'customer_concern' => 'Customer Concern',
        'other' => 'Other',
    ];

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getStages() {
        return self::STAGES;
    }

    public function getCategories() {
        return self::CATEGORIES;
    }

    public function getByWorkItem($woid, $workItemIndex) {
        $workItemIndex = $this->normalizeWorkItemIndex($workItemIndex);
        if ($workItemIndex === false) {
            return [];
        }

        return $this->getByTarget($woid, $workItemIndex);
    }

    public function getByTarget($woid, $workItemIndex = null) {
        $this->lastError = '';
        $workItemIndex = $this->normalizeOptionalWorkItemIndex($workItemIndex);
        if ($workItemIndex === false) {
            return [];
        }

        try {
            if ($workItemIndex === null) {
                $stmt = $this->pdo->prepare(
                    "SELECT *
                     FROM work_order_photos
                     WHERE WOID = ? AND work_item_index IS NULL
                     ORDER BY FIELD(stage, 'before', 'during', 'after', 'inspection', 'internal'), created_at DESC, photo_id DESC"
                );
                $stmt->execute([(int)$woid]);
            } else {
                $stmt = $this->pdo->prepare(
                    "SELECT *
                     FROM work_order_photos
                     WHERE WOID = ? AND work_item_index = ?
                     ORDER BY FIELD(stage, 'before', 'during', 'after', 'inspection', 'internal'), created_at DESC, photo_id DESC"
                );
                $stmt->execute([(int)$woid, $workItemIndex]);
            }
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function countByWorkOrder($woid) {
        $this->lastError = '';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT work_item_index, COUNT(*) AS photo_count
                 FROM work_order_photos
                 WHERE WOID = ?
                 GROUP BY work_item_index"
            );
            $stmt->execute([(int)$woid]);
            $counts = [];
            foreach ($stmt->fetchAll() as $row) {
                if ($row['work_item_index'] === null) {
                    $counts['general'] = (int)$row['photo_count'];
                } else {
                    $counts[(int)$row['work_item_index']] = (int)$row['photo_count'];
                }
            }
            return $counts;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getCustomerPdfPhotosByWorkOrder($woid, array $filters = []) {
        $this->lastError = '';

        $includeGeneral = array_key_exists('include_general_photos', $filters)
            ? (bool)$filters['include_general_photos']
            : true;
        $includeWorkItems = array_key_exists('include_work_item_photos', $filters)
            ? (bool)$filters['include_work_item_photos']
            : true;

        if (!$includeGeneral && !$includeWorkItems) {
            return [];
        }

        try {
            $where = [
                'WOID = ?',
                'show_on_customer_pdf = 1'
            ];
            $params = [(int)$woid];

            if ($includeGeneral && !$includeWorkItems) {
                $where[] = 'work_item_index IS NULL';
            } elseif (!$includeGeneral && $includeWorkItems) {
                $where[] = 'work_item_index IS NOT NULL';
            }

            $stmt = $this->pdo->prepare(
                "SELECT *
                 FROM work_order_photos
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY
                    CASE WHEN work_item_index IS NULL THEN 0 ELSE 1 END,
                    work_item_index,
                    FIELD(stage, 'before', 'during', 'after', 'inspection', 'internal'),
                    created_at ASC,
                    photo_id ASC"
            );
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    public function getById($photoId) {
        $this->lastError = '';

        try {
            $stmt = $this->pdo->prepare("SELECT * FROM work_order_photos WHERE photo_id = ?");
            $stmt->execute([(int)$photoId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function saveUpload($woid, $workItemIndex, array $file, array $data) {
        $this->lastError = '';
        $woid = (int)$woid;
        $workItemIndex = $this->normalizeOptionalWorkItemIndex($workItemIndex);

        if ($woid <= 0 || $workItemIndex === false) {
            $this->lastError = 'Invalid work order photo target.';
            return false;
        }

        $validation = $this->validateUpload($file);
        if (!$validation) {
            return false;
        }

        $stage = $this->normalizeStage($data['stage'] ?? 'before');
        $category = $workItemIndex === null ? $this->normalizeCategory($data['category'] ?? '') : null;
        $caption = trim((string)($data['caption'] ?? ''));
        $showOnPdf = !empty($data['show_on_customer_pdf']) ? 1 : 0;
        $actor = trim((string)Session::getUsername());
        if ($actor === '') {
            $actor = 'system';
        }

        $relativeDir = 'uploads/work_order_photos/' . $this->workOrderFolder($woid);
        $absoluteDir = $this->projectRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true)) {
            $this->lastError = 'Unable to create upload directory.';
            return false;
        }

        $extension = $validation['extension'];
        $targetPrefix = $workItemIndex === null ? 'general' : 'wi' . $workItemIndex;
        $filename = sprintf(
            '%s_%s_%s.%s',
            $targetPrefix,
            $stage,
            date('Ymd_His') . '_' . bin2hex(random_bytes(4)),
            $extension
        );
        $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = $relativeDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            $this->lastError = 'Unable to save uploaded photo.';
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO work_order_photos
                    (WOID, work_item_index, stage, category, caption, file_path, thumbnail_path, original_name, mime_type, file_size, show_on_customer_pdf, uploaded_by)
                 VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $woid,
                $workItemIndex,
                $stage,
                $category,
                $caption,
                $relativePath,
                $relativePath,
                $this->cleanFileName($file['name'] ?? ''),
                $validation['mime_type'],
                (int)$file['size'],
                $showOnPdf,
                $actor
            ]);
            return true;
        } catch (PDOException $e) {
            @unlink($absolutePath);
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function updateMeta($photoId, array $data) {
        $this->lastError = '';

        $stage = $this->normalizeStage($data['stage'] ?? 'before');
        $category = $this->normalizeCategory($data['category'] ?? '');
        $caption = trim((string)($data['caption'] ?? ''));
        $showOnPdf = !empty($data['show_on_customer_pdf']) ? 1 : 0;

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE work_order_photos
                 SET stage = ?, category = ?, caption = ?, show_on_customer_pdf = ?
                 WHERE photo_id = ?"
            );
            return $stmt->execute([$stage, $category, $caption, $showOnPdf, (int)$photoId]);
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function delete($photoId) {
        $this->lastError = '';
        $photo = $this->getById($photoId);
        if (!$photo) {
            $this->lastError = 'Photo not found.';
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM work_order_photos WHERE photo_id = ?");
            $deleted = $stmt->execute([(int)$photoId]);
            if ($deleted) {
                $this->removeRelativeFile($photo['file_path'] ?? '');
                $thumb = (string)($photo['thumbnail_path'] ?? '');
                if ($thumb !== '' && $thumb !== (string)($photo['file_path'] ?? '')) {
                    $this->removeRelativeFile($thumb);
                }
            }
            return $deleted;
        } catch (PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function publicUrl($relativePath) {
        $path = trim(str_replace('\\', '/', (string)$relativePath), '/');
        return rtrim(BASE_URL, '/') . '/' . $path;
    }

    private function validateUpload(array $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->lastError = $this->uploadErrorMessage((int)($file['error'] ?? UPLOAD_ERR_NO_FILE));
            return false;
        }

        $maxBytes = max((int)MAX_FILE_SIZE, 8 * 1024 * 1024);
        if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxBytes) {
            $this->lastError = 'Photo must be smaller than ' . number_format($maxBytes / 1024 / 1024, 0) . ' MB.';
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        if (!isset($extensions[$mimeType])) {
            $this->lastError = 'Upload a JPG, PNG, GIF, or WEBP photo.';
            return false;
        }

        return [
            'mime_type' => $mimeType,
            'extension' => $extensions[$mimeType]
        ];
    }

    private function normalizeWorkItemIndex($index) {
        $index = (int)$index;
        return $index >= 1 && $index <= 5 ? $index : false;
    }

    private function normalizeOptionalWorkItemIndex($index) {
        if ($index === null || $index === '' || $index === 'general') {
            return null;
        }

        return $this->normalizeWorkItemIndex($index);
    }

    private function normalizeStage($stage) {
        $stage = strtolower(trim((string)$stage));
        return in_array($stage, self::STAGES, true) ? $stage : 'before';
    }

    private function normalizeCategory($category) {
        $category = strtolower(trim((string)$category));
        return isset(self::CATEGORIES[$category]) ? $category : null;
    }

    private function projectRoot() {
        return dirname(__DIR__, 2);
    }

    private function workOrderFolder($woid) {
        return 'PREC-' . str_pad((string)(int)$woid, 6, '0', STR_PAD_LEFT);
    }

    private function cleanFileName($name) {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '', (string)$name);
        return trim($name) ?: 'photo';
    }

    private function removeRelativeFile($relativePath) {
        $path = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relativePath), DIRECTORY_SEPARATOR);
        if ($path === '') {
            return;
        }

        $absolute = $this->projectRoot() . DIRECTORY_SEPARATOR . $path;
        $root = realpath($this->projectRoot());
        $targetDir = realpath(dirname($absolute));
        if ($root && $targetDir && str_starts_with($targetDir, $root) && is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function uploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded photo is too large.';
            case UPLOAD_ERR_PARTIAL:
                return 'The photo upload did not finish.';
            case UPLOAD_ERR_NO_FILE:
                return 'Choose or take a photo first.';
            default:
                return 'Photo upload failed.';
        }
    }
}
