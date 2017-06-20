<?php


trait HasPdfRandom
{
    /**
     * @return string
     */
    public function getPDFDocumentContent($text = 'Testing digital document!')
    {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, $text);
        return $pdf->Output('S');
    }
}