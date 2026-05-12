<?php

require_once __DIR__ . '/../lib/fpdf/fpdf.php';

class MultiPointInspectionPdf extends FPDF
{
    private array $inspection;
    private array $itemsByCategory;
    private array $summary;
    private array $recommendations;
    private string $projectRoot;

    public function __construct(array $inspection, array $itemsByCategory, array $summary, array $recommendations)
    {
        parent::__construct('P', 'mm', 'Letter');
        $this->inspection = $inspection;
        $this->itemsByCategory = $itemsByCategory;
        $this->summary = $summary;
        $this->recommendations = $recommendations;
        $this->projectRoot = dirname(__DIR__, 2);

        $this->SetTitle('Multi-Point Inspection - ' . $this->workOrderNumber());
        $this->SetAuthor(APP_NAME);
        $this->SetCreator(APP_NAME);
        $this->SetMargins(7, 8, 7);
        $this->SetAutoPageBreak(true, 8);
        $this->AliasNbPages();
    }

    public function render(): void
    {
        $this->AddPage('L');
        $this->renderVehicleSummary();
        $this->renderSummaryCounts();
        $this->renderRecommendations();
        $this->AddPage('L');
        $this->renderDetails();
    }

    public function Header(): void
    {
        if ($this->PageNo() > 1) {
            $this->SetY(8);
            return;
        }

        $logoPath = $this->projectRoot . DIRECTORY_SEPARATOR . 'Header.jpg';
        if (is_file($logoPath)) {
            $this->Image($logoPath, $this->pageLeft(), 6, 128);
        }

        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(25, 39, 52);
        $this->SetXY(142, 7);
        $this->Cell(0, 5.5, $this->pdfText(APP_NAME), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(85, 95, 105);
        $this->SetX(142);
        $this->Cell(0, 4.8, 'Multi-Point Vehicle Inspection Report', 0, 1, 'R');

        $this->SetDrawColor(210, 216, 222);
        $this->Line($this->pageLeft(), 20, $this->pageRight(), 20);
        $this->SetY(22);
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetDrawColor(225, 229, 234);
        $this->Line($this->pageLeft(), $this->GetY(), $this->pageRight(), $this->GetY());
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(95, 105, 115);
        $half = $this->usableWidth() / 2;
        $this->Cell($half, 6, 'Generated ' . date('m/d/Y h:i A'), 0, 0, 'L');
        $this->Cell($half, 6, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

	    private function renderVehicleSummary(): void
	    {
	        $this->sectionTitle('Vehicle and Inspection Summary');
        $left = [
            ['Work Order', $this->workOrderNumber()],
            ['Customer', trim((string)($this->inspection['FirstName'] ?? '') . ' ' . (string)($this->inspection['LastName'] ?? ''))],
            ['Vehicle', trim((string)($this->inspection['Year'] ?? '') . ' ' . (string)($this->inspection['Make'] ?? '') . ' ' . (string)($this->inspection['Model'] ?? ''))],
            ['VIN', $this->inspection['VIN'] ?? ''],
        ];
        $right = [
            ['Mileage', $this->inspection['mileage_at_inspect'] ?? ''],
            ['Inspection Date', formatDateTime($this->inspection['created_at'] ?? '')],
            ['Mechanic', $this->inspection['mechanic'] ?? ''],
            ['Status', strtoupper(str_replace('_', ' ', (string)($this->inspection['status'] ?? '')))],
        ];
	
	        $y = $this->GetY();
	        $panelGap = 4;
	        $panelW = ($this->usableWidth() - $panelGap) / 2;
	        $this->infoPanel($this->pageLeft(), $y, $panelW, 'Customer / Vehicle', $left);
	        $this->infoPanel($this->pageLeft() + $panelW + $panelGap, $y, $panelW, 'Inspection', $right);
	        $this->SetY(max($this->GetY(), $y + 25));
	        $this->Ln(2);
	    }

    private function renderSummaryCounts(): void
    {
        $this->sectionTitle('At-a-Glance Results');
        $labels = [
            'good' => 'Good',
            'watch' => 'Watch',
            'repair' => 'Repair',
            'na' => 'N/A',
            'unrated' => 'Unrated'
        ];
	
	        $x = $this->pageLeft();
	        $y = $this->GetY();
	        $gap = 3;
	        $w = ($this->usableWidth() - ($gap * (count($labels) - 1))) / count($labels);
	        foreach ($labels as $key => $label) {
            [$r, $g, $b] = $this->colorFor($key, true);
            [$tr, $tg, $tb] = $this->colorFor($key, false);
            $this->SetFillColor($r, $g, $b);
            $this->Rect($x, $y, $w, 15, 'F');
            $this->SetXY($x, $y + 1.5);
            $this->SetFont('Arial', 'B', 13);
            $this->SetTextColor($tr, $tg, $tb);
            $this->Cell($w, 6, (string)(int)($this->summary[$key] ?? 0), 0, 2, 'C');
            $this->SetFont('Arial', 'B', 9);
            $this->Cell($w, 5, $label, 0, 0, 'C');
	            $x += $w + $gap;
	        }
        $this->SetTextColor(0, 0, 0);
        $this->SetY($y + 18);
    }

    private function renderRecommendations(): void
    {
        $repair = [];
        $watch = [];
        foreach ($this->recommendations as $item) {
            if ($item['rating'] === 'repair') {
                $repair[] = $item;
            } elseif ($item['rating'] === 'watch') {
                $watch[] = $item;
            }
        }
	
	        $this->sectionTitle('Recommendations');
	        $y = $this->GetY();
	        $boxGap = 4;
	        $boxW = ($this->usableWidth() - $boxGap) / 2;
	        $leftHeight = $this->recommendationBoxAt($this->pageLeft(), $y, $boxW, 'Repair Now', $repair, 'repair');
	        $rightHeight = $this->recommendationBoxAt($this->pageLeft() + $boxW + $boxGap, $y, $boxW, 'Watch Soon', $watch, 'watch');
	        $this->SetY($y + max($leftHeight, $rightHeight) + 2);

        $overall = trim((string)($this->inspection['overall_notes'] ?? ''));
        if ($overall !== '') {
            $this->sectionTitle('Overall Notes');
            $this->SetFont('Arial', '', 9);
            $this->MultiCell(0, 4.5, $this->pdfText($overall), 1, 'L');
        }
    }

    private function renderDetails(): void
    {
        $this->sectionTitle('Inspection Detail');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(95, 105, 115);
        $this->Cell(0, 4.5, 'Detail view. Long notes are summarized in Recommendations on page 1.', 0, 1, 'L');
        $this->Ln(0.5);

        $columns = $this->splitCategoriesForCompactColumns();
        $startY = $this->GetY();
        $colGap = 4;
        $colW = ($this->usableWidth() - $colGap) / 2;
        $xPositions = [$this->pageLeft(), $this->pageLeft() + $colW + $colGap];

        foreach ($columns as $colIndex => $columnCategories) {
            $this->SetXY($xPositions[$colIndex], $startY);
            $this->compactTableHeader($xPositions[$colIndex], $colW);
            foreach ($columnCategories as $category => $items) {
                $this->compactCategoryHeader($xPositions[$colIndex], $colW, (string)$category);
                foreach ($items as $item) {
                    $this->compactItemRow($xPositions[$colIndex], $colW, $item);
                }
                $this->Ln(0.8);
            }
        }
    }

    private function sectionTitle(string $title): void
    {
        $this->ensureSpace(9);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(35, 51, 65);
        $this->Cell(0, 5, $this->pdfText($title), 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(1);
    }

    private function infoPanel(float $x, float $y, float $w, string $title, array $items): void
    {
        $this->SetXY($x, $y);
        $this->SetFillColor(238, 242, 246);
        $this->SetDrawColor(205, 212, 220);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($w, 6, $this->pdfText($title), 1, 1, 'L', true);

        foreach ($items as $pair) {
            if (trim((string)($pair[1] ?? '')) === '') {
                continue;
            }
            $this->SetX($x);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(28, 5.5, $this->pdfText($pair[0]), 1, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->Cell($w - 28, 5.5, $this->fitText((string)$pair[1], $w - 30), 1, 1, 'L');
        }
    }

    private function recommendationBoxAt(float $x, float $y, float $w, string $title, array $items, string $rating): float
    {
        $this->SetXY($x, $y);
        [$r, $g, $b] = $this->colorFor($rating, true);
        [$tr, $tg, $tb] = $this->colorFor($rating, false);
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor($tr, $tg, $tb);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($w, 6, $title, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 9);

        $startY = $y;
        $this->SetX($x);
        if (empty($items)) {
            $this->MultiCell($w, 4.5, 'No items in this category.', 'LR', 'L');
        } else {
            foreach ($items as $item) {
                $line = '- ' . VehicleInspection::formatItemCode($item) . ' ' . $item['item_label'];
                $note = trim((string)($item['note'] ?? ''));
                if ($note !== '') {
                    $line .= ': ' . $note;
                }
                $this->SetX($x);
                $this->MultiCell($w, 4.5, $this->pdfText($line), 'LR', 'L');
            }
        }
        $this->SetX($x);
        $this->Cell($w, 1, '', 'T', 1);

        return $this->GetY() - $startY;
    }

    private function splitCategoriesForCompactColumns(): array
    {
        $totalRows = 0;
        foreach ($this->itemsByCategory as $items) {
            $totalRows += count($items) + 1;
        }

        $target = (int)ceil($totalRows / 2);
        $columns = [[], []];
        $currentRows = 0;
        $col = 0;

        foreach ($this->itemsByCategory as $category => $items) {
            $weight = count($items) + 1;
            if ($col === 0 && $currentRows > 0 && ($currentRows + $weight) > $target) {
                $col = 1;
                $currentRows = 0;
            }

            $columns[$col][$category] = $items;
            $currentRows += $weight;
        }

        return $columns;
    }

    private function compactTableHeader(float $x, float $w): void
    {
        $widths = $this->compactWidths($w);
        $this->SetXY($x, $this->GetY());
        $this->SetFillColor(225, 230, 236);
        $this->SetTextColor(35, 51, 65);
        $this->SetFont('Arial', 'B', 9);
        foreach (['Code', 'Item', 'Rate', 'Note'] as $i => $header) {
            $this->Cell($widths[$i], 5, $header, 1, 0, 'L', true);
        }
        $this->Ln();
        $this->SetX($x);
    }

    private function compactCategoryHeader(float $x, float $w, string $category): void
    {
        $this->SetX($x);
        $this->SetFillColor(238, 242, 246);
        $this->SetTextColor(25, 39, 52);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell($w, 5, $this->fitText($category, $w - 2), 1, 1, 'L', true);
        $this->SetX($x);
    }

    private function compactItemRow(float $x, float $w, array $item): void
    {
        $widths = $this->compactWidths($w);
        $height = 5;
        $rating = (string)($item['rating'] ?? '');
        $values = [
            VehicleInspection::formatItemCode($item),
            (string)($item['item_label'] ?? ''),
            $this->ratingLabel($rating),
            trim((string)($item['note'] ?? ''))
        ];

        $this->SetX($x);
        foreach ($values as $i => $value) {
            if ($i === 2) {
                [$r, $g, $b] = $this->colorFor($rating, true);
                [$tr, $tg, $tb] = $this->colorFor($rating, false);
                $this->SetFillColor($r, $g, $b);
                $this->SetTextColor($tr, $tg, $tb);
                $this->SetFont('Arial', 'B', 9);
                $this->Cell($widths[$i], $height, $this->fitText($value, $widths[$i] - 1), 1, 0, 'C', true);
                $this->SetTextColor(0, 0, 0);
            } else {
                $this->SetFont('Arial', $i === 0 ? 'B' : '', 9);
                $this->Cell($widths[$i], $height, $this->fitText($value, $widths[$i] - 1.5), 1, 0, 'L');
            }
        }
        $this->Ln();
        $this->SetX($x);
    }

    private function compactWidths(float $w): array
    {
        return [12, $w - 86, 16, 58];
    }

    private function recommendationBox(string $title, array $items, string $rating): void
    {
        [$r, $g, $b] = $this->colorFor($rating, true);
        [$tr, $tg, $tb] = $this->colorFor($rating, false);
        $this->SetFillColor($r, $g, $b);
        $this->SetTextColor($tr, $tg, $tb);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(0, 7, $title, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 9);

        if (empty($items)) {
            $this->MultiCell(0, 5, 'No items in this category.', 'LR', 'L');
        } else {
            foreach ($items as $item) {
                $line = '- ' . $item['item_label'];
                $note = trim((string)($item['note'] ?? ''));
                if ($note !== '') {
                    $line .= ': ' . $note;
                }
                $this->MultiCell(0, 5, $this->pdfText($line), 'LR', 'L');
            }
        }
        $this->Cell(0, 1, '', 'T', 1);
        $this->Ln(3);
    }

    private function tableRow(array $widths, array $values, string $rating): void
    {
        $lineHeight = 4.5;
        $height = 8;
        foreach ($values as $i => $value) {
            $height = max($height, $this->lineCount($widths[$i] - 2, (string)$value) * $lineHeight + 3);
        }
        $this->ensureSpace($height);

        $x = $this->GetX();
        $y = $this->GetY();
        foreach ($values as $i => $value) {
            $w = $widths[$i];
            $this->Rect($x, $y, $w, $height);
            if ($i === 2) {
                [$r, $g, $b] = $this->colorFor($rating, true);
                [$tr, $tg, $tb] = $this->colorFor($rating, false);
                $this->SetFillColor($r, $g, $b);
                $this->Rect($x + 1.5, $y + 1.5, $w - 3, 5, 'F');
                $this->SetTextColor($tr, $tg, $tb);
                $this->SetFont('Arial', 'B', 9);
                $this->SetXY($x + 1.5, $y + 2);
                $this->Cell($w - 3, 4, $this->pdfText($value), 0, 0, 'C');
                $this->SetTextColor(0, 0, 0);
            } else {
                $this->SetFont('Arial', '', 9);
                $this->SetXY($x + 1, $y + 1.5);
                $this->MultiCell($w - 2, $lineHeight, $this->pdfText($value), 0, 'L');
            }
            $x += $w;
            $this->SetXY($x, $y);
        }
        $this->SetXY(12, $y + $height);
    }

    private function lineCount(float $w, string $text): int
    {
        $text = $this->pdfText($text);
        if ($text === '') {
            return 1;
        }

        $words = preg_split('/\s+/', $text) ?: [];
        $lines = 1;
        $line = '';
        foreach ($words as $word) {
            $candidate = $line === '' ? $word : ($line . ' ' . $word);
            if ($this->GetStringWidth($candidate) > $w) {
                $lines++;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        return $lines;
    }

    private function pageLeft(): float
    {
        return 7;
    }

    private function pageRight(): float
    {
        return $this->GetPageWidth() - 7;
    }

    private function usableWidth(): float
    {
        return $this->pageRight() - $this->pageLeft();
    }

    private function fitText(string $text, float $width): string
    {
        $text = trim($this->pdfText($text));
        if ($text === '' || $this->GetStringWidth($text) <= $width) {
            return $text;
        }

        $ellipsis = '...';
        while ($text !== '' && $this->GetStringWidth($text . $ellipsis) > $width) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text) . $ellipsis;
    }

    private function ensureSpace(float $height): void
    {
        if ($this->GetY() + $height > $this->GetPageHeight() - 12) {
            $this->AddPage($this->CurOrientation);
        }
    }

    private function colorFor(string $rating, bool $soft): array
    {
        return match ($rating) {
            'good' => $soft ? [231, 246, 238] : [23, 138, 85],
            'watch' => $soft ? [255, 244, 214] : [166, 111, 0],
            'repair' => $soft ? [253, 232, 234] : [191, 47, 56],
            'na' => $soft ? [238, 241, 245] : [102, 112, 133],
            default => $soft ? [238, 241, 245] : [74, 85, 101],
        };
    }

    private function ratingLabel(string $rating): string
    {
        return match ($rating) {
            'good' => 'Good',
            'watch' => 'Watch',
            'repair' => 'Repair',
            'na' => 'N/A',
            default => 'Unrated',
        };
    }

    private function workOrderNumber(): string
    {
        return generateWONumber((int)($this->inspection['WOID'] ?? 0));
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
