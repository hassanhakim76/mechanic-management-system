<?php
/**
 * Admin - Customer Work Order PDF
 */

require_once '../../includes/bootstrap.php';
require_once '../../includes/pdf/CustomerWorkOrderPdf.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$getWoid = (int)get('woid', 0);
if (!isPost()) {
    if ($getWoid > 0) {
        redirect('work_order_pdf_options.php?woid=' . $getWoid);
    }
    Session::setFlashMessage('error', 'Choose a work order before generating a PDF.');
    redirect('work_orders.php');
}

if (!verifyCSRFToken(post('csrf_token'))) {
    $redirectWoid = (int)post('woid', 0);
    Session::setFlashMessage('error', 'Security token expired. Refresh and try again.');
    redirect($redirectWoid > 0 ? ('work_order_pdf_options.php?woid=' . $redirectWoid) : 'work_orders.php');
}

$woid = (int)post('woid', 0);
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

$options = [
    'include_customer_note' => post('include_customer_note') === '1',
    'include_work_order_note' => post('include_work_order_note') === '1',
    'include_action_taken' => post('include_action_taken') === '1',
    'include_photos' => post('include_photos') === '1',
    'include_general_photos' => post('include_general_photos') === '1',
    'include_work_item_photos' => post('include_work_item_photos') === '1',
    'include_signature' => post('include_signature') === '1',
];

$photos = [];
if ($options['include_photos']) {
    $photos = $photoModel->getCustomerPdfPhotosByWorkOrder($woid, [
        'include_general_photos' => $options['include_general_photos'],
        'include_work_item_photos' => $options['include_work_item_photos'],
    ]);
}

$pdf = new CustomerWorkOrderPdf($wo, $photos, $options);
$pdf->render();

$filename = 'customer_work_order_' . generateWONumber($woid) . '.pdf';
$pdf->Output('I', $filename);
exit;
