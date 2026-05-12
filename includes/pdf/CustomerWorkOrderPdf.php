<?php

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class CustomerWorkOrderPdf extends FPDF
{
    private array $wo;
    private array $photos;
    private array $options;
    private string $projectRoot;
    private array $skippedPhotos = [];

    public function __construct(array $workOrder, array $photos = [], array $options = [])
    {
        parent::__construct('P', 'mm', 'Letter');

        $this->wo = $workOrder;
        $this->photos = $photos;
        $this->options = array_merge($this->defaultOptions(), $options);
        $this->projectRoot = dirname(__DIR__, 2);

        $this->SetTitle('Customer Work Order Report - ' . $this->workOrderNumber());
        $this->SetAuthor(APP_NAME);
        $this->SetCreator(APP_NAME);
        $this->SetMargins(12, 15, 12);
        $this->SetAutoPageBreak(true, 16);
        $this->AliasNbPages();
    }

    public function render(): void
    {
        $this->AddPage();
        $this->renderSummary();
        $this->renderCustomerVehicle();
        $this->renderWorkItems();
        $this->renderCustomerNotes();
        if ($this->option('include_photos')) {
            $this->renderPhotos();
        }
        if ($this->option('include_signature')) {
            $this->renderSignature();
        }
    }

    public function Header(): void
    {
        $logoPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'Header.jpg';
        if (is_file($logoPath)) {
            $this->Image($logoPath, 12,9, 88);
        }

        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(25, 39, 52);
        $this->SetXY(62, 10);
        $this->Cell(0, 7, $this->pdfText(APP_NAME), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(85, 95, 105);
        $this->SetX(62);
        $this->Cell(0, 5, 'Customer Work Order Report', 0, 1, 'R');

        $this->SetDrawColor(210, 216, 222);
        $this->Line(12, 27, 204, 27);
        $this->Ln(12);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetDrawColor(225, 229, 234);
        $this->Line(12, $this->GetY(), 204, $this->GetY());
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(95, 105, 115);
        $this->Cell(96, 6, 'Generated ' . date('m/d/Y h:i A'), 0, 0, 'L');
        $this->Cell(96, 6, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    private function defaultOptions(): array
    {
        return [
            'include_customer_note' => true,
            'include_work_order_note' => true,
            'include_action_taken' => true,
            'include_photos' => true,
            'include_general_photos' => true,
            'include_work_item_photos' => true,
            'include_signature' => true,
        ];
    }

    private function option(string $key): bool
    {
        return !empty($this->options[$key]);
    }

    private function renderSummary(): void
    {
        $this->sectionTitle('Work Order Summary');

        $items = [
            ['Work Order', $this->workOrderNumber()],
            ['Date / Time', formatDateTime($this->wo['WO_Date'] ?? '')],
            ['Status', $this->wo['WO_Status'] ?? ''],
            ['Priority', $this->wo['Priority'] ?? ''],
            ['Mileage', $this->wo['Mileage'] ?? ''],
            ['Mechanic', $this->wo['Mechanic'] ?? 'Unassigned'],
        ];

        $this->keyValueGrid($items, 3);
        $this->Ln(4);
    }

    private function renderCustomerVehicle(): void
    {
        $this->sectionTitle('Customer and Vehicle');

        $customer = [
            ['Customer', trim((string)($this->wo['FirstName'] ?? '') . ' ' . (string)($this->wo['LastName'] ?? ''))],
            ['Customer ID', $this->wo['CustomerID'] ?? ''],
            ['Phone', $this->wo['Phone'] ?? ''],
            ['Cell', $this->wo['Cell'] ?? ''],
            ['Email', $this->wo['Email'] ?? ''],
            ['Address', $this->formatAddress()],
        ];

        $vehicle = [
            ['Vehicle', trim((string)($this->wo['Year'] ?? '') . ' ' . (string)($this->wo['Make'] ?? '') . ' ' . (string)($this->wo['Model'] ?? ''))],
            ['Plate', $this->wo['Plate'] ?? ''],
            ['VIN', $this->wo['VIN'] ?? ''],
            ['Color', $this->wo['Color'] ?? ''],
            ['Engine', $this->wo['Engine'] ?? ''],
        ];

        $startY = $this->GetY();
        $this->infoPanel(12, $startY, 94, 'Customer', $customer);
        $this->infoPanel(110, $startY, 94, 'Vehicle', $vehicle);
        $this->SetY(max($this->GetY(), $startY + 49));
        $this->Ln(4);
    }

    private function renderWorkItems(): void
    {
        $includeActionTaken = $this->option('include_action_taken');
        $this->sectionTitle($includeActionTaken ? 'Work Requested and Action Taken' : 'Work Requested');

        $widths = $includeActionTaken ? [14, 86, 92] : [14, 178];
        $headers = $includeActionTaken ? ['W.I.', 'Requested', 'Action Taken / Result'] : ['W.I.', 'Requested'];

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(238, 242, 246);
        $this->SetDrawColor(205, 212, 220);
        foreach ($headers as $index => $header) {
            $this->Cell($widths[$index], 7, $header, 1, 0, 'L', true);
        }
        $this->Ln();

        $hasItems = false;
        $this->SetFont('Arial', '', 8);
        for ($i = 1; $i <= 5; $i++) {
            $requested = trim((string)($this->wo['WO_Req' . $i] ?? ''));
            if ($requested === '') {
                continue;
            }

            $hasItems = true;
            $action = trim((string)($this->wo['WO_Action' . $i] ?? ''));
            $values = $includeActionTaken
                ? ['W.I. ' . $i, $requested, $action]
                : ['W.I. ' . $i, $requested];
            $this->tableRow($widths, $values);
        }

        if (!$hasItems) {
            $this->Cell(array_sum($widths), 8, 'No work items recorded.', 1, 1, 'L');
        }

        $this->Ln(5);
    }

    private function renderCustomerNotes(): void
    {
        $customerNote = $this->option('include_customer_note') ? trim((string)($this->wo['Customer_Note'] ?? '')) : '';
        $workOrderNote = $this->option('include_work_order_note') ? trim((string)($this->wo['WO_Note'] ?? '')) : '';

        if ($customerNote === '' && $workOrderNote === '') {
            return;
        }

        $this->sectionTitle('Notes');
        if ($customerNote !== '') {
            $this->noteBlock('Customer Note', $customerNote);
        }
        if ($workOrderNote !== '') {
            $this->noteBlock('Work Order Note', $workOrderNote);
        }
        $this->Ln(3);
    }

    private function renderPhotos(): void
    {
        $this->sectionTitle('Customer Photos');

        if (empty($this->photos)) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 7, 'No photos were selected for the customer PDF.', 0, 1);
            $this->Ln(3);
            return;
        }

        $cardW = 92;
        $cardH = 70;
        $gap = 8;
        $xLeft = 12;
        $xRight = $xLeft + $cardW + $gap;
        $x = $xLeft;
        $y = $this->GetY();
        $column = 0;

        foreach ($this->photos as $photo) {
            $path = $this->absolutePhotoPath($photo['file_path'] ?? '');
            if (!$this->canRenderPhoto($path)) {
                $this->skippedPhotos[] = $photo['original_name'] ?? $photo['file_path'] ?? 'photo';
                continue;
            }

            if ($y + $cardH > 250) {
                $this->AddPage();
                $y = $this->GetY();
                $column = 0;
                $x = $xLeft;
            }

            $this->photoCard($photo, $path, $x, $y, $cardW, $cardH);

            if ($column === 0) {
                $column = 1;
                $x = $xRight;
            } else {
                $column = 0;
                $x = $xLeft;
                $y += $cardH + 6;
            }
        }

        if ($column === 1) {
            $y += $cardH + 6;
        }
        $this->SetY($y);

        if (!empty($this->skippedPhotos)) {
            $this->ensureSpace(16);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(120, 72, 0);
            $this->MultiCell(0, 4.5, $this->pdfText('Some selected photos were skipped because PDF rendering supports JPG, PNG, and GIF only.'));
            $this->SetTextColor(0, 0, 0);
        }

        $this->Ln(3);
    }

    private function renderSignature(): void
    {
        $this->ensureSpace(34);
        $this->sectionTitle('Customer Acknowledgement');

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(70, 78, 86);
        $this->MultiCell(0, 4.5, $this->pdfText('This report summarizes work order activity and selected photo evidence. This document is not an invoice or estimate.'));
        $this->Ln(8);

        $this->SetDrawColor(120, 130, 140);
        $this->Line(12, $this->GetY(), 92, $this->GetY());
        $this->Line(124, $this->GetY(), 204, $this->GetY());
        $this->Ln(2);

        $this->SetFont('Arial', '', 8);
        $this->Cell(100, 5, 'Customer Signature', 0, 0, 'L');
        $this->Cell(92, 5, 'Date', 0, 1, 'L');
    }

    private function sectionTitle(string $title): void
    {
        $this->ensureSpace(14);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(35, 51, 65);
        $this->Cell(0, 7, $this->pdfText($title), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    private function keyValueGrid(array $items, int $columns): void
    {
        $colW = 192 / $columns;
        $labelW = 23;
        $valueW = $colW - $labelW;

        $this->SetDrawColor(215, 221, 227);
        $this->SetFillColor(248, 250, 252);
        $this->SetFont('Arial', 'B', 8);

        foreach (array_chunk($items, $columns) as $row) {
            $this->ensureSpace(8);
            for ($i = 0; $i < $columns; $i++) {
                $pair = $row[$i] ?? ['', ''];
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($labelW, 7, $this->pdfText($pair[0]), 1, 0, 'L', true);
                $this->SetFont('Arial', '', 8);
                $this->Cell($valueW, 7, $this->pdfText($this->emptyDash($pair[1])), 1, 0, 'L');
            }
            $this->Ln();
        }
    }

    private function infoPanel(float $x, float $y, float $w, string $title, array $items): void
    {
        $this->SetXY($x, $y);
        $this->SetFillColor(238, 242, 246);
        $this->SetDrawColor(205, 212, 220);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($w, 7, $this->pdfText($title), 1, 1, 'L', true);

        foreach ($items as $pair) {
            if (trim((string)($pair[1] ?? '')) === '') {
                continue;
            }
            $this->SetX($x);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell(24, 6, $this->pdfText($pair[0]), 1, 0, 'L');
            $this->SetFont('Arial', '', 8);
            $this->Cell($w - 24, 6, $this->pdfText($this->emptyDash($pair[1])), 1, 1, 'L');
        }
    }

    private function tableRow(array $widths, array $values): void
    {
        $lineHeight = 4.5;
        $height = 3;
        foreach ($values as $index => $value) {
            $height = max($height, $this->lineCount($widths[$index] - 2, (string)$value) * $lineHeight + 3);
        }
        $this->ensureSpace($height);

        $x = $this->GetX();
        $y = $this->GetY();

        foreach ($values as $index => $value) {
            $w = $widths[$index];
            $align = $index === 2 ? 'C' : 'L';
            $this->Rect($x, $y, $w, $height);
            $this->SetXY($x + 1, $y + 1.5);
            $this->MultiCell($w - 2, $lineHeight, $this->pdfText($this->emptyDash($value)), 0, $align);
            $x += $w;
            $this->SetXY($x, $y);
        }

        $this->SetXY(12, $y + $height);
    }

    private function noteBlock(string $label, string $text): void
    {
        $lineHeight = 4.5;
        $height = $this->lineCount(188, $text) * $lineHeight + 12;
        $this->ensureSpace($height);

        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(35, 51, 65);
        $this->Cell(0, 5, $this->pdfText($label), 0, 1);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(250, 252, 254);
        $this->MultiCell(0, $lineHeight, $this->pdfText($text), 1, 'L', true);
        $this->Ln(2);
    }

    private function photoCard(array $photo, string $path, float $x, float $y, float $w, float $h): void
    {
        $this->SetDrawColor(205, 212, 220);
        $this->SetFillColor(250, 252, 254);
        $this->Rect($x, $y, $w, $h, 'DF');

        $title = $this->photoTargetLabel($photo) . ' - ' . ucfirst((string)($photo['stage'] ?? 'photo'));
        $category = $this->formatCategory($photo['category'] ?? '');
        if ($category !== '') {
            $title .= ' - ' . $category;
        }

        $this->SetXY($x + 2, $y + 2);
        $this->SetFont('Arial', 'B', 7);
        $this->SetTextColor(35, 51, 65);
        $this->Cell($w - 4, 4, $this->pdfText($title), 0, 1, 'L');

        $imageBoxX = $x + 2;
        $imageBoxY = $y + 7;
        $imageBoxW = $w - 4;
        $imageBoxH = 47;

        $size = @getimagesize($path);
        if ($size && $size[0] > 0 && $size[1] > 0) {
            $scale = min($imageBoxW / $size[0], $imageBoxH / $size[1]);
            $drawW = $size[0] * $scale;
            $drawH = $size[1] * $scale;
            $drawX = $imageBoxX + (($imageBoxW - $drawW) / 2);
            $drawY = $imageBoxY + (($imageBoxH - $drawH) / 2);
            $this->Image($path, $drawX, $drawY, $drawW, $drawH);
        }

        $caption = trim((string)($photo['caption'] ?? ''));
        if ($caption === '') {
            $caption = trim((string)($photo['created_at'] ?? ''));
        }

        $this->SetXY($x + 2, $y + 56);
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(75, 85, 95);
        $this->MultiCell($w - 4, 3.5, $this->pdfText($caption), 0, 'L');
        $this->SetTextColor(0, 0, 0);
    }

    private function lineCount(float $w, string $text): int
    {
        $text = $this->pdfText($text);
        if ($text === '') {
            return 1;
        }

        $cw = &$this->CurrentFont['cw'];
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $text);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] === "\n") {
            $nb--;
        }

        $sep = -1;
        $i = 0;
        $j = 0;
        $lineLength = 0;
        $lines = 1;

        while ($i < $nb) {
            $c = $s[$i];
            if ($c === "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $lineLength = 0;
                $lines++;
                continue;
            }
            if ($c === ' ') {
                $sep = $i;
            }
            $lineLength += $cw[$c] ?? 0;
            if ($lineLength > $wmax) {
                if ($sep === -1) {
                    if ($i === $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $lineLength = 0;
                $lines++;
            } else {
                $i++;
            }
        }

        return $lines;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->GetY() + $height > 258) {
            $this->AddPage();
        }
    }

    private function workOrderNumber(): string
    {
        if (function_exists('generateWONumber')) {
            return generateWONumber((int)($this->wo['WOID'] ?? 0));
        }

        return 'PREC-' . str_pad((string)(int)($this->wo['WOID'] ?? 0), 6, '0', STR_PAD_LEFT);
    }

    private function formatAddress(): string
    {
        $parts = array_filter([
            trim((string)($this->wo['Address'] ?? '')),
            trim((string)($this->wo['City'] ?? '')),
            trim((string)($this->wo['Province'] ?? '')),
            trim((string)($this->wo['PostalCode'] ?? '')),
        ]);

        return implode(', ', $parts);
    }

    private function photoTargetLabel(array $photo): string
    {
        if (($photo['work_item_index'] ?? null) === null || $photo['work_item_index'] === '') {
            return 'General';
        }

        return 'W.I. ' . (int)$photo['work_item_index'];
    }

    private function formatCategory($category): string
    {
        $category = trim((string)$category);
        if ($category === '') {
            return '';
        }

        return ucwords(str_replace('_', ' ', $category));
    }

    private function emptyDash($value): string
    {
        $value = trim((string)$value);
        return $value === '' ? '-' : $value;
    }

    private function absolutePhotoPath(string $relativePath): string
    {
        $relativePath = trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            return '';
        }

        $candidate = $this->projectRoot . DIRECTORY_SEPARATOR . $relativePath;
        $root = realpath($this->projectRoot);
        $resolved = realpath($candidate);

        if (!$root || !$resolved || !str_starts_with($resolved, $root)) {
            return '';
        }

        return $resolved;
    }

    private function canRenderPhoto(string $path): bool
    {
        if ($path === '' || !is_file($path)) {
            return false;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif'], true);
    }

    private function pdfText($value): string
    {
        $text = (string)$value;
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? '';

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return utf8_decode($text);
    }
}
