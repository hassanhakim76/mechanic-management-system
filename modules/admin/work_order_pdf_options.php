<?php
/**
 * Admin - Customer Work Order PDF Options
 */

require_once '../../includes/bootstrap.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$woid = (int)get('woid', 0);
if ($woid <= 0) {
    Session::setFlashMessage('error', 'Invalid work order.');
    redirect('work_orders.php');
}

$woModel = new WorkOrder();
$photoModel = new WorkOrderPhoto();

$wo = $woModel->getById($woid);
if (!$wo) {
    Session::setFlashMessage('error', 'Work order not found.');
    redirect('work_orders.php');
}

$pdfPhotos = $photoModel->getCustomerPdfPhotosByWorkOrder($woid);
$generalPhotoCount = 0;
$workItemPhotoCount = 0;
foreach ($pdfPhotos as $photo) {
    if (($photo['work_item_index'] ?? null) === null || $photo['work_item_index'] === '') {
        $generalPhotoCount++;
    } else {
        $workItemPhotoCount++;
    }
}

$pageTitle = 'Customer PDF Options - ' . generateWONumber($woid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="../../public/css/style.css?v=<?php echo urlencode((string)@filemtime(__DIR__ . '/../../public/css/style.css')); ?>">
</head>
<body>
    <div class="app-header">
        <div class="app-header-brand"><?php echo APP_NAME; ?></div>
        <h1 class="page-title-centered"><?php echo e($pageTitle); ?></h1>
        <div class="user-info">
            <span>User: <?php echo e(Session::getUsername()); ?></span>
            <button type="button" class="btn" onclick="window.close()">Close</button>
        </div>
    </div>

    <div class="container">
        <div class="form-container">
            <form method="POST" action="work_order_pdf.php" target="_blank">
                <?php csrfField(); ?>
                <input type="hidden" name="woid" value="<?php echo (int)$woid; ?>">

                <div class="wo-header wo-header--admin-summary" style="margin-bottom: 16px;">
                    <div class="wo-summary-grid">
                        <div class="wo-summary-block">
                            <div class="wo-summary-line">
                                <span class="wo-summary-label">Work Order</span>
                                <span class="wo-summary-value"><?php echo e(generateWONumber($woid)); ?></span>
                            </div>
                            <div class="wo-summary-line">
                                <span class="wo-summary-label">Status</span>
                                <span class="wo-summary-value"><?php echo statusBadge($wo['WO_Status'] ?? STATUS_NEW); ?></span>
                            </div>
                        </div>
                        <div class="wo-summary-block">
                            <div class="wo-summary-line">
                                <span class="wo-summary-label">Customer</span>
                                <span class="wo-summary-value"><?php echo e(getFullName($wo['FirstName'], $wo['LastName'])); ?></span>
                            </div>
                            <div class="wo-summary-line">
                                <span class="wo-summary-label">Vehicle</span>
                                <span class="wo-summary-value"><?php echo e(trim($wo['Year'] . ' ' . $wo['Make'] . ' ' . $wo['Model'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <h2 style="margin-top: 0;">Report Content</h2>

                <div class="form-row">
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_customer_note" value="1" checked>
                            Include Customer Note
                        </label>
                    </div>
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_work_order_note" value="1" checked>
                            Include Work Order Note
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_action_taken" value="1" checked>
                            Include Action Taken
                        </label>
                    </div>
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_signature" value="1" checked>
                            Include Signature Area
                        </label>
                    </div>
                </div>

                <h2>Photos</h2>
                <p class="text-muted">
                    Only photos marked "Show on customer PDF" are eligible.
                    General: <?php echo (int)$generalPhotoCount; ?>,
                    W.I.: <?php echo (int)$workItemPhotoCount; ?>.
                </p>

                <div class="form-row">
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_photos" value="1" checked data-pdf-photos-master>
                            Include Customer Photos
                        </label>
                    </div>
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_general_photos" value="1" checked data-pdf-photo-option>
                            Include General Photos
                        </label>
                    </div>
                    <div class="form-group" style="min-width: 280px;">
                        <label>
                            <input type="checkbox" name="include_work_item_photos" value="1" checked data-pdf-photo-option>
                            Include W.I. Photos
                        </label>
                    </div>
                </div>

                <div class="work-order-footer-actions" style="margin-top: 20px; text-align: right;">
                    <button type="submit" class="btn btn-success">Generate PDF</button>
                    <button type="button" class="btn" onclick="window.close()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var master = document.querySelector('[data-pdf-photos-master]');
            var photoOptions = document.querySelectorAll('[data-pdf-photo-option]');
            if (!master) {
                return;
            }

            var sync = function () {
                photoOptions.forEach(function (option) {
                    option.disabled = !master.checked;
                });
            };

            master.addEventListener('change', sync);
            sync();
        })();
    </script>
</body>
</html>
