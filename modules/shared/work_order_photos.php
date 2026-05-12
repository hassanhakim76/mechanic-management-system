<?php
/**
 * Work Order Photos
 * Shared popup for attaching photo evidence to a specific W.I. line.
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin() && !Session::isMechanic()) {
    redirect(BASE_URL . '/index.php');
}

$woModel = new WorkOrder();
$photoModel = new WorkOrderPhoto();

$woid = (int)get('woid', 0);
$wiParam = strtolower(trim((string)get('wi', '')));
$isGeneralPhotos = $wiParam === 'general';
$workItemIndex = $isGeneralPhotos ? null : (int)$wiParam;
$wo = $woid > 0 ? $woModel->getById($woid) : null;

if (!$wo || (!$isGeneralPhotos && ($workItemIndex < 1 || $workItemIndex > 5))) {
    die('Invalid work order photo target.');
}

$requestValue = $isGeneralPhotos ? 'General Work Order Photos' : trim((string)($wo['WO_Req' . $workItemIndex] ?? ''));
if (!$isGeneralPhotos && $requestValue === '') {
    die('This W.I. does not have a work request yet.');
}

$targetLabel = $isGeneralPhotos ? 'General' : ('W.I. ' . $workItemIndex);
$targetTitle = $isGeneralPhotos ? 'General Work Order Photos' : $requestValue;
$photoMatchesTarget = function ($photo) use ($woid, $workItemIndex, $isGeneralPhotos) {
    if (!$photo || (int)$photo['WOID'] !== $woid) {
        return false;
    }

    if ($isGeneralPhotos) {
        return $photo['work_item_index'] === null || $photo['work_item_index'] === '';
    }

    return (int)$photo['work_item_index'] === (int)$workItemIndex;
};

$message = '';
$error = '';

if (isPost()) {
    if (!verifyCSRFToken(post('csrf_token'))) {
        $error = 'Security token expired. Refresh and try again.';
    } else {
        $action = post('action', '');
        $photoId = (int)post('photo_id', 0);

        if ($action === 'upload') {
            $file = null;
            foreach (['photo_camera', 'photo_upload'] as $fileKey) {
                if (isset($_FILES[$fileKey]) && ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES[$fileKey];
                    break;
                }
            }

            if (!$file) {
                $error = 'Choose or take a photo first.';
            } elseif ($photoModel->saveUpload($woid, $isGeneralPhotos ? null : $workItemIndex, $file, [
                'stage' => post('stage', 'before'),
                'category' => post('category', ''),
                'caption' => post('caption', ''),
                'show_on_customer_pdf' => post('show_on_customer_pdf')
            ])) {
                $message = 'Photo uploaded.';
            } else {
                $error = $photoModel->getLastError() ?: 'Unable to upload photo.';
            }
        } elseif ($action === 'update') {
            $photo = $photoModel->getById($photoId);
            if (!$photoMatchesTarget($photo)) {
                $error = 'Photo not found for this target.';
            } elseif ($photoModel->updateMeta($photoId, [
                'stage' => post('stage', 'before'),
                'category' => $isGeneralPhotos ? post('category', '') : '',
                'caption' => post('caption', ''),
                'show_on_customer_pdf' => post('show_on_customer_pdf')
            ])) {
                $message = 'Photo updated.';
            } else {
                $error = $photoModel->getLastError() ?: 'Unable to update photo.';
            }
        } elseif ($action === 'delete') {
            $photo = $photoModel->getById($photoId);
            $currentUser = trim((string)Session::getUsername());
            $canDelete = Session::isAdmin() || strcasecmp((string)($photo['uploaded_by'] ?? ''), $currentUser) === 0;

            if (!$photoMatchesTarget($photo)) {
                $error = 'Photo not found for this target.';
            } elseif (!$canDelete) {
                $error = 'Only an admin or the uploader can delete this photo.';
            } elseif ($photoModel->delete($photoId)) {
                $message = 'Photo deleted.';
            } else {
                $error = $photoModel->getLastError() ?: 'Unable to delete photo.';
            }
        }
    }
}

$photos = $photoModel->getByTarget($woid, $isGeneralPhotos ? null : $workItemIndex);
$stages = $photoModel->getStages();
$categories = $photoModel->getCategories();
$pageTitle = 'Photos - PREC-' . str_pad((string)$woid, 6, '0', STR_PAD_LEFT) . ' ' . $targetLabel;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body class="work-order-photo-page">
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <button type="button" class="btn" onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo e($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo e($error); ?></div>
        <?php endif; ?>

        <section class="photo-manager-band">
            <div class="photo-manager-summary">
                <div>
                    <div class="photo-manager-eyebrow"><?php echo e($targetLabel); ?></div>
                    <h2><?php echo e($targetTitle); ?></h2>
                </div>
                <div class="photo-manager-count"><?php echo count($photos); ?> Photos</div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="photo-upload-form">
                <?php csrfField(); ?>
                <input type="hidden" name="action" value="upload">

	                <div class="photo-upload-controls">
	                    <?php if ($isGeneralPhotos): ?>
	                        <div class="form-group">
	                            <label>Category</label>
	                            <select name="category">
	                                <?php foreach ($categories as $categoryValue => $categoryLabel): ?>
	                                    <option value="<?php echo e($categoryValue); ?>"><?php echo e($categoryLabel); ?></option>
	                                <?php endforeach; ?>
	                            </select>
	                        </div>
	                    <?php endif; ?>
	                    <div class="form-group">
	                        <label>Stage</label>
	                        <select name="stage">
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo e($stage); ?>"><?php echo e(ucfirst($stage)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group photo-caption-field">
                        <label>Caption</label>
                        <input type="text" name="caption" maxlength="255" placeholder="<?php echo $isGeneralPhotos ? 'Example: Odometer at arrival' : 'Example: Tire tread before replacement'; ?>">
                    </div>
                    <label class="photo-pdf-check">
                        <input type="checkbox" name="show_on_customer_pdf" value="1">
                        Show on customer PDF
                    </label>
                </div>

                <div class="photo-file-actions">
                    <label class="btn btn-primary photo-file-picker">
                        Take Photo
                        <input type="file" name="photo_camera" accept="image/*" capture="environment">
                    </label>
                    <label class="btn photo-file-picker">
                        Upload Photo
                        <input type="file" name="photo_upload" accept="image/*">
                    </label>
                    <button type="submit" class="btn btn-success">Save Photo</button>
                    <span class="photo-file-name" id="photoFileName">No photo selected</span>
                </div>
            </form>
        </section>

        <section class="photo-grid-section">
            <?php if (empty($photos)): ?>
                <div class="photo-empty-state">No photos have been added for <?php echo e(strtolower($targetLabel)); ?> yet.</div>
            <?php else: ?>
                <div class="photo-grid">
                    <?php foreach ($photos as $photo): ?>
                        <?php
                            $photoUrl = $photoModel->publicUrl($photo['file_path']);
                            $currentUser = trim((string)Session::getUsername());
                            $canDelete = Session::isAdmin() || strcasecmp((string)($photo['uploaded_by'] ?? ''), $currentUser) === 0;
                        ?>
                        <article class="photo-card">
                            <a href="<?php echo e($photoUrl); ?>" target="_blank" rel="noopener">
                                <img src="<?php echo e($photoUrl); ?>" alt="<?php echo e($photo['caption'] ?: 'Work order photo'); ?>">
                            </a>
                            <form method="POST" class="photo-meta-form">
                                <?php csrfField(); ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="photo_id" value="<?php echo (int)$photo['photo_id']; ?>">

	                                <div class="photo-card-fields">
	                                    <?php if ($isGeneralPhotos): ?>
	                                        <select name="category" aria-label="Photo category">
	                                            <?php foreach ($categories as $categoryValue => $categoryLabel): ?>
	                                                <option value="<?php echo e($categoryValue); ?>" <?php echo ($photo['category'] ?? '') === $categoryValue ? 'selected' : ''; ?>>
	                                                    <?php echo e($categoryLabel); ?>
	                                                </option>
	                                            <?php endforeach; ?>
	                                        </select>
	                                    <?php endif; ?>
	                                    <select name="stage">
	                                        <?php foreach ($stages as $stage): ?>
	                                            <option value="<?php echo e($stage); ?>" <?php echo $photo['stage'] === $stage ? 'selected' : ''; ?>>
                                                <?php echo e(ucfirst($stage)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>
                                        <input type="checkbox" name="show_on_customer_pdf" value="1" <?php echo !empty($photo['show_on_customer_pdf']) ? 'checked' : ''; ?>>
                                        PDF
                                    </label>
                                </div>
                                <input type="text" name="caption" maxlength="255" value="<?php echo e($photo['caption']); ?>" placeholder="Caption">
                                <div class="photo-card-meta">
                                    <?php echo e($photo['uploaded_by'] ?: 'system'); ?>
                                    <?php if (!empty($photo['created_at'])): ?>
                                        - <?php echo e(date('M j, Y g:i A', strtotime($photo['created_at']))); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="photo-card-actions">
                                    <button type="submit" class="btn btn-small">Update</button>
                                </div>
                            </form>
                            <?php if ($canDelete): ?>
                                <form method="POST" onsubmit="return confirm('Delete this photo?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="photo_id" value="<?php echo (int)$photo['photo_id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <script>
        (function () {
            var label = document.getElementById('photoFileName');
            document.querySelectorAll('input[type="file"]').forEach(function (input) {
                input.addEventListener('change', function () {
                    if (!label) {
                        return;
                    }
                    label.textContent = input.files && input.files[0] ? input.files[0].name : 'No photo selected';
                });
            });
        })();
    </script>
</body>
</html>
