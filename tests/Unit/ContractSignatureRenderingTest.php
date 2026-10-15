<?php

namespace Tests\Unit;

use App\Http\Controllers\ContractSignatureController;
use App\Models\SalesEndorsement;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ContractSignatureRenderingTest extends TestCase
{
    private function callPdfMethod(string $method, ...$arguments)
    {
        $reflection = new \ReflectionMethod(ContractSignatureController::class, $method);

        return $reflection->invoke(new ContractSignatureController(), ...$arguments);
    }

    public function test_signature_annotations_include_audit_labels_without_changing_original_page_fonts(): void
    {
        $source = $this->sourcePdf();
        $endorsement = new SalesEndorsement([
            'author_name' => 'Test Author', 'contract_signature_text' => 'Test Author',
            'contract_signed_at' => Carbon::parse('2026-10-13 18:30:00', 'UTC'),
            'contract_esign_fields' => [
                ['id' => 'signature', 'type' => 'signature', 'page' => 1, 'x' => 10, 'y' => 70, 'w' => 30, 'h' => 9, 'fontSize' => 20],
                ['id' => 'date', 'type' => 'date', 'page' => 1, 'x' => 55, 'y' => 70, 'w' => 25, 'h' => 9],
            ],
            'contract_esign_field_values' => ['signature' => 'Test Author', 'date' => '10/13/2026'],
        ]);
        $endorsement->setRelation('brand', null)->setRelation('contractSender', null);
        $result = $this->callPdfMethod('appendSignatureCertificatePage', $source, $endorsement);
        $before = $this->callPdfMethod('pdfObjects', $source);
        $after = $this->callPdfMethod('pdfObjects', $result);

        $this->assertEquals($before[4], $after[4], 'Original page content must not be rewritten.');
        $this->assertEquals($before[5], $after[5], 'Original font must not be rewritten.');
        $this->assertStringContainsString('/Resources << /Font << /F1 5 0 R >> >>', $after[3]);
        $this->assertStringContainsString('/Annots [', $after[3]);
        $this->assertStringContainsString('(E-Signed by:)', $result);
        $this->assertStringContainsString('(10/13/2026 @ 02:30 PM)', $result);
        $this->assertStringContainsString('3 28 Td (10/13/2026 @ 02:30 PM)', $result);
        $this->assertStringContainsString('(10/13/2026)', $result);
        $this->assertSame(1, substr_count($result, '(Signature Certificate)'));
        $this->assertSame($result, $this->callPdfMethod('appendSignatureCertificatePage', $result, $endorsement));
    }

    public function test_saved_signature_image_is_accepted_but_invalid_and_oversized_images_are_rejected(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is needed for signature appearance images.');
        }
        $image = imagecreatetruecolor(1000, 180);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $data = 'data:image/png;base64,' . base64_encode($bytes);

        $this->assertSame($bytes, $this->callPdfMethod('signaturePngBytes', $data));
        $this->assertStringContainsString('/Subtype /Image', $this->callPdfMethod('signatureImagePdfObject', '', 200, 40, $data));
        $this->assertNull($this->callPdfMethod('signaturePngBytes', 'data:image/png;base64,' . base64_encode('not a PNG')));
        $this->assertNull($this->callPdfMethod('signaturePngBytes', 'data:image/svg+xml;base64,' . base64_encode('<svg/>')));
        $this->assertNull($this->callPdfMethod('signaturePngBytes', 'data:image/png;base64,' . str_repeat('A', 100001)));
    }

    public function test_saved_signature_whitespace_is_cropped_before_fitting_the_field(): void
    {
        if (! function_exists('imagepng')) {
            $this->markTestSkipped('GD is needed for signature appearance images.');
        }

        $image = imagecreatetruecolor(1000, 180);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 450, 70, 549, 109, imagecolorallocate($image, 0, 0, 0));
        $cropped = $this->callPdfMethod('cropSignatureImage', $image);

        $this->assertSame(104, imagesx($cropped));
        $this->assertSame(44, imagesy($cropped));
        imagedestroy($cropped);
    }

    private function sourcePdf(): string
    {
        $content = 'BT /F1 12 Tf 72 700 Td (Original agreement text) Tj ET';
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            4 => '<< /Length ' . strlen($content) . ">>\nstream\n" . $content . "\nendstream",
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
