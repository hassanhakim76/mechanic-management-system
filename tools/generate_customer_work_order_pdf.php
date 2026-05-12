<?php
/**
 * Generate a customer-facing work order PDF sample.
 *
 * Usage:
 *   php tools/generate_customer_work_order_pdf.php 7129
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$projectRoot = dirname(__DIR__);
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?? (dirname($projectRoot) . DIRECTORY_SEPARATOR);
require_once $projectRoot . '/config/config.php';

$woid = isset($argv[1]) ? (int)$argv[1] : 7129;
if ($woid <= 0) {
    fwrite(STDERR, "Invalid work order ID.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$stmt = $pdo->prepare(
    "SELECT wo.*,
            c.FirstName, c.LastName, c.Phone, c.Cell, c.Email, c.Address, c.City, c.Province, c.PostalCode,
            cv.Plate, cv.VIN, cv.Make, cv.Model, cv.Year, cv.Color, cv.Engine
     FROM work_order wo
     JOIN customers c ON wo.CustomerID = c.CustomerID
     LEFT JOIN customer_vehicle cv ON wo.CVID = cv.CVID
     WHERE wo.WOID = ?"
);
$stmt->execute([$woid]);
$wo = $stmt->fetch();

if (!$wo) {
    fwrite(STDERR, "Work order not found: {$woid}\n");
    exit(1);
}

final class CustomerWorkOrderPdf
{
    private array $pages = [];
    private string $content = '';
    private float $y = 0.0;
    private int $pageNo = 0;

    public function __construct()
    {
        $this->addPage();
    }

    public function addPage(): void
    {
        if ($this->content !== '') {
            $this->drawFooter();
            $this->pages[] = $this->content;
        }

        $this->pageNo++;
        $this->content = '';
        $this->y = 748;
    }

    public function save(string $path): void
    {
        if ($this->content !== '') {
            $this->drawFooter();
            $this->pages[] = $this->content;
            $this->content = '';
        }

        $objects = [];
        $objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objects[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";

        $kids = [];
        $nextId = 5;
        foreach ($this->pages as $pageContent) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}endstream";
        }

        $objects[2] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 {$size}\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents($path, $pdf);
    }

    public function text(float $x, float $y, string $text, int $size = 10, string $font = 'F1'): void
    {
        $this->content .= sprintf(
            "BT /%s %d Tf %.2F %.2F Td (%s) Tj ET\n",
            $font,
            $size,
            $x,
            $y,
            $this->escape($text)
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->content .= sprintf("%.2F %.2F m %.2F %.2F l S\n", $x1, $y1, $x2, $y2);
    }

    public function rect(float $x, float $y, float $w, float $h): void
    {
        $this->content .= sprintf("%.2F %.2F %.2F %.2F re S\n", $x, $y, $w, $h);
    }

    public function sectionTitle(string $title): void
    {
        $this->ensureSpace(34);
        $this->line(45, $this->y + 10, 567, $this->y + 10);
        $this->text(45, $this->y - 5, strtoupper($title), 10, 'F2');
        $this->y -= 24;
    }

    public function keyValue(float $x, string $label, string $value, int $labelWidth = 82): void
    {
        $this->text($x, $this->y, $label . ':', 9, 'F2');
        $this->text($x + $labelWidth, $this->y, $value !== '' ? $value : 'Not provided', 9);
        $this->y -= 15;
    }

    public function paragraph(float $x, string $text, int $maxChars, int $size = 9): void
    {
        $lines = $this->wrap($text !== '' ? $text : 'Not provided', $maxChars);
        foreach ($lines as $line) {
            $this->ensureSpace(16);
            $this->text($x, $this->y, $line, $size);
            $this->y -= 13;
        }
    }

    public function workItem(int $index, string $request, bool $complete, string $action): void
    {
        $this->ensureSpace(76);
        $boxTop = $this->y + 8;
        $this->rect(45, $this->y - 48, 522, 61);
        $this->text(57, $this->y - 6, "W.I. {$index}", 9, 'F2');
        $this->text(105, $this->y - 6, $request, 9);
        $this->text(57, $this->y - 23, 'Completed:', 9, 'F2');
        $this->text(116, $this->y - 23, $complete ? 'Yes' : 'No', 9);
        $this->text(180, $this->y - 23, 'Action Taken:', 9, 'F2');
        $actionLines = $this->wrap($action !== '' ? $action : 'Not provided', 48);
        $lineY = $this->y - 23;
        foreach ($actionLines as $line) {
            $this->text(252, $lineY, $line, 9);
            $lineY -= 12;
        }
        $this->y = $boxTop - 69;
    }

    public function setY(float $y): void
    {
        $this->y = $y;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function ensureSpace(float $height): void
    {
        if ($this->y - $height < 74) {
            $this->addPage();
        }
    }

    private function drawFooter(): void
    {
        $this->line(45, 54, 567, 54);
        $this->text(45, 38, 'Thank you for choosing Precision Autoworks.', 9, 'F2');
        $this->text(505, 38, 'Page ' . $this->pageNo, 9);
    }

    private function wrap(string $text, int $maxChars): array
    {
        $text = preg_replace('/\s+/', ' ', trim($this->plain($text)));
        if ($text === '') {
            return [''];
        }

        $words = explode(' ', $text);
        $lines = [];
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : $line . ' ' . $word;
            if (strlen($candidate) > $maxChars && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->plain($text));
    }

    private function plain(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $text);
        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }
}

function wo_number(int $woid): string
{
    return 'PREC-' . str_pad((string)$woid, 6, '0', STR_PAD_LEFT);
}

function display_date(?string $date): string
{
    if (!$date || $date === '0000-00-00 00:00:00') {
        return '';
    }
    return date('F j, Y', strtotime($date));
}

function phone_display(?string $phone): string
{
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if (strlen($digits) === 10) {
        return '(' . substr($digits, 0, 3) . ') ' . substr($digits, 3, 3) . '-' . substr($digits, 6);
    }
    return (string)$phone;
}

function value(array $row, string $key): string
{
    return trim((string)($row[$key] ?? ''));
}

$pdf = new CustomerWorkOrderPdf();

$pdf->text(45, 752, 'PRECISION AUTOWORKS', 18, 'F2');
$pdf->text(45, 732, 'Customer Work Order', 12, 'F2');
$pdf->text(365, 752, 'Generated: ' . date('F j, Y'), 9);
$pdf->text(365, 737, 'Customer-facing sample', 9);
$pdf->line(45, 718, 567, 718);

$pdf->setY(696);
$pdf->text(45, $pdf->getY(), 'Work Order #: ' . wo_number((int)$wo['WOID']), 11, 'F2');
$pdf->text(245, $pdf->getY(), 'Date: ' . display_date(value($wo, 'WO_Date')), 10);
$pdf->text(415, $pdf->getY(), 'Status: ' . value($wo, 'WO_Status'), 10, 'F2');
$pdf->setY($pdf->getY() - 28);

$pdf->sectionTitle('Customer');
$customerY = $pdf->getY();
$pdf->keyValue(45, 'Name', trim(value($wo, 'FirstName') . ' ' . value($wo, 'LastName')));
$pdf->keyValue(45, 'Phone', phone_display(value($wo, 'Phone')));
$pdf->keyValue(45, 'Cell', phone_display(value($wo, 'Cell')));
$pdf->keyValue(45, 'Email', value($wo, 'Email'));
$leftEndY = $pdf->getY();

$pdf->setY($customerY);
$address = trim(value($wo, 'Address') . ', ' . value($wo, 'City') . ', ' . value($wo, 'Province') . ' ' . value($wo, 'PostalCode'), ' ,');
$pdf->keyValue(330, 'Address', $address, 60);
$rightEndY = $pdf->getY();
$pdf->setY(min($leftEndY, $rightEndY) - 8);

$pdf->sectionTitle('Vehicle');
$vehicleY = $pdf->getY();
$pdf->keyValue(45, 'Vehicle', trim(value($wo, 'Year') . ' ' . value($wo, 'Make') . ' ' . value($wo, 'Model')));
$pdf->keyValue(45, 'VIN', value($wo, 'VIN'));
$pdf->keyValue(45, 'Plate', value($wo, 'Plate'));
$leftEndY = $pdf->getY();

$pdf->setY($vehicleY);
$pdf->keyValue(330, 'Mileage', value($wo, 'Mileage') !== '' ? number_format((int)value($wo, 'Mileage')) . ' km' : '', 68);
$pdf->keyValue(330, 'Color', value($wo, 'Color'), 68);
$pdf->keyValue(330, 'Engine', value($wo, 'Engine'), 68);
$rightEndY = $pdf->getY();
$pdf->setY(min($leftEndY, $rightEndY) - 8);

$pdf->sectionTitle('Requested Work / Service Performed');
for ($i = 1; $i <= 5; $i++) {
    $request = value($wo, 'WO_Req' . $i);
    if ($request === '') {
        continue;
    }
    $pdf->workItem($i, $request, !empty($wo['Req' . $i]), value($wo, 'WO_Action' . $i));
}

$pdf->sectionTitle('Customer Note');
$pdf->paragraph(45, value($wo, 'Customer_Note'), 88);
$pdf->setY($pdf->getY() - 8);

$pdf->sectionTitle('Work Order Note');
$pdf->paragraph(45, value($wo, 'WO_Note'), 88);
$pdf->setY($pdf->getY() - 8);

$pdf->sectionTitle('Assignment / Road Test');
$pdf->keyValue(45, 'Assigned Mechanic', value($wo, 'Mechanic'), 110);
$pdf->keyValue(45, 'Test Drive', !empty($wo['TestDrive']) ? 'Completed' : 'Not completed', 110);
$pdf->setY($pdf->getY() - 10);

$pdf->sectionTitle('Customer Authorization');
$pdf->paragraph(
    45,
    'I authorize the repair/service work listed above and acknowledge the service details shown on this customer-facing work order sample.',
    88
);
$pdf->setY($pdf->getY() - 26);
$pdf->line(45, $pdf->getY(), 275, $pdf->getY());
$pdf->line(360, $pdf->getY(), 567, $pdf->getY());
$pdf->text(45, $pdf->getY() - 14, 'Customer Signature', 9);
$pdf->text(360, $pdf->getY() - 14, 'Date', 9);

$outputDir = __DIR__ . '/../docs/generated';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0775, true);
}

$outputPath = $outputDir . '/customer_work_order_' . wo_number((int)$wo['WOID']) . '_sample.pdf';
$pdf->save($outputPath);

echo $outputPath . PHP_EOL;
