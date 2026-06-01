<?php

/**
 * Spectrum Condition Report Generation Job
 *
 * Background job for generating PDF condition reports
 *
 * @package    ahgSpectrumPlugin
 * @subpackage lib/job
 * @author     Johan Pieterse <johan@theahg.co.za>
 */

use Illuminate\Database\Capsule\Manager as DB;

class ahgSpectrumConditionReportJob extends arBaseJob
{
    /**
     * @see arBaseJob::$requiredParameters
     */
    protected $requiredParameters = ['conditionCheckId'];

    /**
     * Job name for display
     */
    protected $jobName = 'Spectrum: Generate Condition Report PDF';

    /**
     * Execute the job
     */
    public function runJob($parameters)
    {
        $this->info('Starting condition report generation job');

        $conditionCheckId = $parameters['conditionCheckId'];
        $includePhotos = $parameters['includePhotos'] ?? true;
        $format = $parameters['format'] ?? 'pdf';

        // Load condition check data
        $conditionCheck = $this->loadConditionCheck($conditionCheckId);

        if (!$conditionCheck) {
            $this->error('Condition check not found: ' . $conditionCheckId);
            return false;
        }

        // Load related object
        $object = $this->getInformationObject($conditionCheck->object_id);

        if (!$object) {
            $this->error('Object not found for condition check');
            return false;
        }

        $this->info('Generating report for: ' . ($object->title ?? $object->identifier ?? 'Unknown'));

        // Load photos if requested
        $photos = [];
        if ($includePhotos) {
            $photos = $this->getConditionPhotos($conditionCheckId);
            $this->info(sprintf('Including %d photos', count($photos)));
        }

        // Load conservation records
        $conservation = $this->loadConservationRecords($conditionCheck->object_id);

        // Generate report based on format
        switch ($format) {
            case 'pdf':
                $reportPath = $this->generatePdfReport($conditionCheck, $object, $photos, $conservation);
                break;
            case 'html':
                $reportPath = $this->generateHtmlReport($conditionCheck, $object, $photos, $conservation);
                break;
            case 'docx':
                $reportPath = $this->generateDocxReport($conditionCheck, $object, $photos, $conservation);
                break;
            default:
                $this->error('Unknown format: ' . $format);
                return false;
        }

        if ($reportPath) {
            $this->info('Report generated: ' . $reportPath);

            // Store report path in job output
            $this->job->setStatusNote('Report generated successfully');
            $this->job->setOutput(['report_path' => $reportPath]);
            $this->job->save();

            return true;
        }

        return false;
    }

    /**
     * Load condition check
     */
    protected function loadConditionCheck($id): ?object
    {
        return DB::table('spectrum_condition_check')
            ->where('id', $id)
            ->first();
    }

    /**
     * Load conservation records
     */
    protected function loadConservationRecords($objectId): array
    {
        return DB::table('spectrum_conservation')
            ->where('object_id', $objectId)
            ->orderByDesc('treatment_date')
            ->get()
            ->toArray();
    }

    /**
     * Get information object with i18n data
     */
    protected function getInformationObject(int $id): ?object
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', $id)
            ->select('io.*', 'i18n.title', 'i18n.scope_and_content', 'i18n.physical_characteristics')
            ->first();
    }

    /**
     * Get condition photos for a condition check
     */
    protected function getConditionPhotos(int $conditionCheckId): array
    {
        return DB::table('spectrum_condition_photo')
            ->where('condition_check_id', $conditionCheckId)
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Get uploads directory with fallback
     */
    protected function getUploadsDir(): string
    {
        if (class_exists('sfConfig')) {
            return sfConfig::get('sf_upload_dir', sfConfig::get('sf_upload_dir'));
        }
        return sfConfig::get('sf_upload_dir');
    }

    /**
     * Get plugins directory with fallback
     */
    protected function getPluginsDir(): string
    {
        if (class_exists('sfConfig')) {
            return sfConfig::get('sf_plugins_dir', sfConfig::get('sf_plugins_dir'));
        }
        return sfConfig::get('sf_plugins_dir');
    }

    /**
     * Get object title helper
     */
    protected function getObjectTitle(object $object): string
    {
        return $object->title ?? $object->identifier ?? 'Untitled';
    }

    /**
     * Generate PDF report
     */
    protected function generatePdfReport($conditionCheck, $object, $photos, $conservation)
    {
        // Check for TCPDF or similar
        if (!class_exists('TCPDF')) {
            // Try to use Dompdf
            if (class_exists('Dompdf\Dompdf')) {
                return $this->generatePdfWithDompdf($conditionCheck, $object, $photos, $conservation);
            }

            $this->error('No PDF library available (TCPDF or Dompdf)');
            return false;
        }

        $objectTitle = $this->getObjectTitle($object);

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('AtoM Spectrum Plugin');
        $pdf->SetAuthor($conditionCheck->checked_by ?? 'Unknown');
        $pdf->SetTitle('Condition Report - ' . $objectTitle);

        // Set margins
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);

        // Add page
        $pdf->AddPage();

        // Title
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'Condition Report', 0, 1, 'C');

        $pdf->SetFont('helvetica', '', 12);
        $pdf->Ln(5);

        // Object info
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Object Information', 0, 1);
        $pdf->SetFont('helvetica', '', 11);

        $pdf->Cell(50, 6, 'Title:', 0, 0);
        $pdf->Cell(0, 6, $objectTitle, 0, 1);

        $pdf->Cell(50, 6, 'Reference Number:', 0, 0);
        $pdf->Cell(0, 6, $object->identifier ?? 'N/A', 0, 1);

        $pdf->Ln(5);

        // Condition check info
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->Cell(0, 8, 'Condition Check Details', 0, 1);
        $pdf->SetFont('helvetica', '', 11);

        $pdf->Cell(50, 6, 'Reference:', 0, 0);
        $pdf->Cell(0, 6, $conditionCheck->condition_check_reference ?? 'N/A', 0, 1);

        $pdf->Cell(50, 6, 'Check Date:', 0, 0);
        $pdf->Cell(0, 6, $conditionCheck->check_date ?? 'N/A', 0, 1);

        $pdf->Cell(50, 6, 'Checked By:', 0, 0);
        $pdf->Cell(0, 6, $conditionCheck->checked_by ?? 'N/A', 0, 1);

        $pdf->Cell(50, 6, 'Check Reason:', 0, 0);
        $pdf->Cell(0, 6, $conditionCheck->check_reason ?? 'N/A', 0, 1);

        $pdf->Cell(50, 6, 'Condition:', 0, 0);
        $pdf->Cell(0, 6, ucfirst($conditionCheck->condition_status ?? 'N/A'), 0, 1);

        $pdf->Cell(50, 6, 'Completeness:', 0, 0);
        $pdf->Cell(0, 6, ucfirst($conditionCheck->completeness ?? 'N/A'), 0, 1);

        // Condition description
        if (!empty($conditionCheck->condition_description)) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'Condition Description:', 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->MultiCell(0, 5, $conditionCheck->condition_description, 0, 'L');
        }

        // Hazards
        if (!empty($conditionCheck->hazards_noted)) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'Hazards Noted:', 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->MultiCell(0, 5, $conditionCheck->hazards_noted, 0, 'L');
        }

        // Recommendations
        if (!empty($conditionCheck->recommendations)) {
            $pdf->Ln(3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(0, 6, 'Recommendations:', 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->MultiCell(0, 5, $conditionCheck->recommendations, 0, 'L');
        }

        // Photos
        if (!empty($photos)) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'Condition Photos', 0, 1);

            $uploadDir = $this->getUploadsDir();
            $x = 15;
            $y = $pdf->GetY() + 5;
            $photoWidth = 85;
            $photoHeight = 65;
            $count = 0;

            foreach ($photos as $photo) {
                $photoObj = is_array($photo) ? (object) $photo : $photo;
                $photoPath = $uploadDir . '/' . $photoObj->file_path;

                if (file_exists($photoPath)) {
                    if ($count > 0 && $count % 2 == 0) {
                        $x = 15;
                        $y += $photoHeight + 25;
                    }

                    if ($y > 250) {
                        $pdf->AddPage();
                        $y = 20;
                        $x = 15;
                    }

                    $pdf->Image($photoPath, $x, $y, $photoWidth, $photoHeight, '', '', '', false, 150);

                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetXY($x, $y + $photoHeight + 2);
                    $pdf->Cell($photoWidth, 4, $photoObj->caption ?? $photoObj->photo_type ?? '', 0, 0, 'C');

                    $x += $photoWidth + 10;
                    $count++;
                }
            }
        }

        // Conservation history
        if (!empty($conservation)) {
            $pdf->AddPage();
            $pdf->SetFont('helvetica', 'B', 14);
            $pdf->Cell(0, 8, 'Conservation History', 0, 1);
            $pdf->SetFont('helvetica', '', 11);

            foreach ($conservation as $record) {
                $recordObj = is_array($record) ? (object) $record : $record;

                $pdf->Ln(3);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 6, ($recordObj->conservation_reference ?? '') . ' - ' . ($recordObj->treatment_date ?? 'N/A'), 0, 1);
                $pdf->SetFont('helvetica', '', 10);

                if (!empty($recordObj->treatment_performed)) {
                    $pdf->MultiCell(0, 5, 'Treatment: ' . $recordObj->treatment_performed, 0, 'L');
                }

                if (!empty($recordObj->conservator_name)) {
                    $pdf->Cell(0, 5, 'Conservator: ' . $recordObj->conservator_name, 0, 1);
                }
            }
        }

        // Save PDF
        $uploadDir = $this->getUploadsDir();
        $outputDir = $uploadDir . '/spectrum/reports/' . date('Y/m');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = 'condition_report_' . $conditionCheck->id . '_' . date('Ymd_His') . '.pdf';
        $outputPath = $outputDir . '/' . $filename;

        $pdf->Output($outputPath, 'F');

        return 'spectrum/reports/' . date('Y/m') . '/' . $filename;
    }

    /**
     * Generate PDF with Dompdf
     */
    protected function generatePdfWithDompdf($conditionCheck, $object, $photos, $conservation)
    {
        $html = $this->generateHtmlContent($conditionCheck, $object, $photos, $conservation);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $uploadDir = $this->getUploadsDir();
        $outputDir = $uploadDir . '/spectrum/reports/' . date('Y/m');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = 'condition_report_' . $conditionCheck->id . '_' . date('Ymd_His') . '.pdf';
        $outputPath = $outputDir . '/' . $filename;

        file_put_contents($outputPath, $dompdf->output());

        return 'spectrum/reports/' . date('Y/m') . '/' . $filename;
    }

    /**
     * Generate HTML report
     */
    protected function generateHtmlReport($conditionCheck, $object, $photos, $conservation)
    {
        $html = $this->generateHtmlContent($conditionCheck, $object, $photos, $conservation);

        $uploadDir = $this->getUploadsDir();
        $outputDir = $uploadDir . '/spectrum/reports/' . date('Y/m');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = 'condition_report_' . $conditionCheck->id . '_' . date('Ymd_His') . '.html';
        $outputPath = $outputDir . '/' . $filename;

        file_put_contents($outputPath, $html);

        return 'spectrum/reports/' . date('Y/m') . '/' . $filename;
    }

    /**
     * Generate HTML content
     */
    protected function generateHtmlContent($conditionCheck, $object, $photos, $conservation)
    {
        $templatePath = $this->getPluginsDir() . '/ahgSpectrumPlugin/templates/_conditionReportHtml.php';

        if (file_exists($templatePath)) {
            ob_start();
            include $templatePath;
            return ob_get_clean();
        }

        // Fallback: generate basic HTML
        return $this->generateBasicHtml($conditionCheck, $object, $photos, $conservation);
    }

    /**
     * Generate basic HTML content (fallback)
     */
    protected function generateBasicHtml($conditionCheck, $object, $photos, $conservation): string
    {
        $objectTitle = $this->getObjectTitle($object);
        $uploadDir = $this->getUploadsDir();

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Condition Report - ' . htmlspecialchars($objectTitle) . '</title>
    <style nonce="' . htmlspecialchars(sfConfig::get('csp_nonce', '')) . '">
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { text-align: center; color: #333; }
        h2 { color: #555; border-bottom: 1px solid #ccc; padding-bottom: 5px; }
        .info-row { margin: 8px 0; }
        .label { font-weight: bold; display: inline-block; width: 150px; }
        .photos { display: flex; flex-wrap: wrap; gap: 15px; }
        .photo { text-align: center; max-width: 250px; }
        .photo img { max-width: 100%; height: auto; border: 1px solid #ddd; }
        .photo-caption { font-size: 12px; color: #666; margin-top: 5px; }
        .conservation-record { margin: 15px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #007bff; }
    </style>
</head>
<body>
    <h1>Condition Report</h1>
    
    <h2>Object Information</h2>
    <div class="info-row"><span class="label">Title:</span> ' . htmlspecialchars($objectTitle) . '</div>
    <div class="info-row"><span class="label">Reference:</span> ' . htmlspecialchars($object->identifier ?? 'N/A') . '</div>
    
    <h2>Condition Check Details</h2>
    <div class="info-row"><span class="label">Reference:</span> ' . htmlspecialchars($conditionCheck->condition_check_reference ?? 'N/A') . '</div>
    <div class="info-row"><span class="label">Check Date:</span> ' . htmlspecialchars($conditionCheck->check_date ?? 'N/A') . '</div>
    <div class="info-row"><span class="label">Checked By:</span> ' . htmlspecialchars($conditionCheck->checked_by ?? 'N/A') . '</div>
    <div class="info-row"><span class="label">Condition:</span> ' . htmlspecialchars(ucfirst($conditionCheck->condition_status ?? 'N/A')) . '</div>
    <div class="info-row"><span class="label">Completeness:</span> ' . htmlspecialchars(ucfirst($conditionCheck->completeness ?? 'N/A')) . '</div>';

        if (!empty($conditionCheck->condition_description)) {
            $html .= '
    <h3>Condition Description</h3>
    <p>' . nl2br(htmlspecialchars($conditionCheck->condition_description)) . '</p>';
        }

        if (!empty($conditionCheck->hazards_noted)) {
            $html .= '
    <h3>Hazards Noted</h3>
    <p>' . nl2br(htmlspecialchars($conditionCheck->hazards_noted)) . '</p>';
        }

        if (!empty($conditionCheck->recommendations)) {
            $html .= '
    <h3>Recommendations</h3>
    <p>' . nl2br(htmlspecialchars($conditionCheck->recommendations)) . '</p>';
        }

        if (!empty($photos)) {
            $html .= '
    <h2>Condition Photos</h2>
    <div class="photos">';
            foreach ($photos as $photo) {
                $photoObj = is_array($photo) ? (object) $photo : $photo;
                $photoPath = $uploadDir . '/' . $photoObj->file_path;
                if (file_exists($photoPath)) {
                    $html .= '
        <div class="photo">
            <img src="file://' . htmlspecialchars($photoPath) . '" alt="Condition photo">
            <div class="photo-caption">' . htmlspecialchars($photoObj->caption ?? $photoObj->photo_type ?? '') . '</div>
        </div>';
                }
            }
            $html .= '
    </div>';
        }

        if (!empty($conservation)) {
            $html .= '
    <h2>Conservation History</h2>';
            foreach ($conservation as $record) {
                $recordObj = is_array($record) ? (object) $record : $record;
                $html .= '
    <div class="conservation-record">
        <strong>' . htmlspecialchars($recordObj->conservation_reference ?? '') . '</strong> - ' . htmlspecialchars($recordObj->treatment_date ?? 'N/A') . '
        <br>Treatment: ' . htmlspecialchars($recordObj->treatment_performed ?? 'N/A') . '
        <br>Conservator: ' . htmlspecialchars($recordObj->conservator_name ?? 'N/A') . '
    </div>';
            }
        }

        $html .= '
    <hr>
    <p style="font-size: 11px; color: #999;">Generated on ' . date('Y-m-d H:i:s') . '</p>
</body>
</html>';

        return $html;
    }

    /**
     * Generate DOCX report
     */
    protected function generateDocxReport($conditionCheck, $object, $photos, $conservation)
    {
        // Requires PhpWord
        if (!class_exists('\PhpOffice\PhpWord\PhpWord')) {
            $this->error('PhpWord not available');
            return false;
        }

        $objectTitle = $this->getObjectTitle($object);

        $phpWord = new \PhpOffice\PhpWord\PhpWord();

        // Add title page
        $section = $phpWord->addSection();
        $section->addText('Condition Report', ['bold' => true, 'size' => 24], ['alignment' => 'center']);
        $section->addTextBreak(2);
        $section->addText($objectTitle, ['size' => 18], ['alignment' => 'center']);

        // Object information
        $section->addTextBreak(2);
        $section->addText('Object Information', ['bold' => true, 'size' => 14]);
        $section->addText('Title: ' . $objectTitle);
        $section->addText('Reference: ' . ($object->identifier ?? 'N/A'));

        // Condition check details
        $section->addTextBreak(1);
        $section->addText('Condition Check Details', ['bold' => true, 'size' => 14]);
        $section->addText('Reference: ' . ($conditionCheck->condition_check_reference ?? 'N/A'));
        $section->addText('Check Date: ' . ($conditionCheck->check_date ?? 'N/A'));
        $section->addText('Checked By: ' . ($conditionCheck->checked_by ?? 'N/A'));
        $section->addText('Condition: ' . ucfirst($conditionCheck->condition_status ?? 'N/A'));
        $section->addText('Completeness: ' . ucfirst($conditionCheck->completeness ?? 'N/A'));

        if (!empty($conditionCheck->condition_description)) {
            $section->addTextBreak(1);
            $section->addText('Condition Description', ['bold' => true]);
            $section->addText($conditionCheck->condition_description);
        }

        if (!empty($conditionCheck->hazards_noted)) {
            $section->addTextBreak(1);
            $section->addText('Hazards Noted', ['bold' => true]);
            $section->addText($conditionCheck->hazards_noted);
        }

        if (!empty($conditionCheck->recommendations)) {
            $section->addTextBreak(1);
            $section->addText('Recommendations', ['bold' => true]);
            $section->addText($conditionCheck->recommendations);
        }

        // Photos
        if (!empty($photos)) {
            $section->addPageBreak();
            $section->addText('Condition Photos', ['bold' => true, 'size' => 14]);

            $uploadDir = $this->getUploadsDir();

            foreach ($photos as $photo) {
                $photoObj = is_array($photo) ? (object) $photo : $photo;
                $photoPath = $uploadDir . '/' . $photoObj->file_path;

                if (file_exists($photoPath)) {
                    $section->addTextBreak(1);
                    $section->addImage($photoPath, [
                        'width' => 300,
                        'height' => 225,
                        'alignment' => 'center',
                    ]);
                    $section->addText(
                        $photoObj->caption ?? $photoObj->photo_type ?? '',
                        ['size' => 10, 'italic' => true],
                        ['alignment' => 'center']
                    );
                }
            }
        }

        // Conservation history
        if (!empty($conservation)) {
            $section->addPageBreak();
            $section->addText('Conservation History', ['bold' => true, 'size' => 14]);

            foreach ($conservation as $record) {
                $recordObj = is_array($record) ? (object) $record : $record;

                $section->addTextBreak(1);
                $section->addText(
                    ($recordObj->conservation_reference ?? '') . ' - ' . ($recordObj->treatment_date ?? 'N/A'),
                    ['bold' => true]
                );

                if (!empty($recordObj->treatment_performed)) {
                    $section->addText('Treatment: ' . $recordObj->treatment_performed);
                }

                if (!empty($recordObj->conservator_name)) {
                    $section->addText('Conservator: ' . $recordObj->conservator_name);
                }
            }
        }

        // Save document
        $uploadDir = $this->getUploadsDir();
        $outputDir = $uploadDir . '/spectrum/reports/' . date('Y/m');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $filename = 'condition_report_' . $conditionCheck->id . '_' . date('Ymd_His') . '.docx';
        $outputPath = $outputDir . '/' . $filename;

        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($outputPath);

        return 'spectrum/reports/' . date('Y/m') . '/' . $filename;
    }
}