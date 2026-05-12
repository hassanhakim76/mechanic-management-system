<?php
/**
 * Admin - Multi-Point Inspection PDF
 */

require_once '../../includes/bootstrap.php';
require_once '../../includes/pdf/MultiPointInspectionPdf.php';

Session::requireLogin();

if (!Session::isAdmin()) {
    redirect(BASE_URL . '/modules/mechanic/work_orders.php');
}

$inspectionId = (int)get('inspection_id', 0);
$woid = (int)get('woid', 0);

$inspectionModel = new VehicleInspection();
$inspection = $inspectionId > 0
    ? $inspectionModel->getById($inspectionId)
    : ($woid > 0 ? $inspectionModel->getByWorkOrder($woid) : null);

if (!$inspection) {
    Session::setFlashMessage('error', 'Inspection not found.');
    redirect($woid > 0 ? ('inspection_view.php?woid=' . $woid) : 'work_orders.php');
}

$itemsByCategory = $inspectionModel->getItemsByCategory((int)$inspection['inspection_id']);
$summary = $inspectionModel->getSummaryCounts((int)$inspection['inspection_id']);
$recommendations = $inspectionModel->getRecommendations((int)$inspection['inspection_id']);

$pdf = new MultiPointInspectionPdf($inspection, $itemsByCategory, $summary, $recommendations);
$pdf->render();

$filename = 'multi_point_inspection_' . generateWONumber((int)$inspection['WOID']) . '.pdf';
$pdf->Output('I', $filename);
exit;
