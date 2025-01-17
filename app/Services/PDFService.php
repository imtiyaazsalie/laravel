<?php

namespace App\Services;

class PDFService
{
    public function getPdfGenerator(): Dompdf
    {
        $pdfOptions = new Options();

        // Configure Dompdf according to your needs
        $pdfOptions
            ->setDefaultFont('Helvetica')
            ->setIsRemoteEnabled(true)
            ->setIsHtml5ParserEnabled(true);

        // Instantiate Dompdf with our options
        $dompdf = new Dompdf($pdfOptions);

        // (Optional) Set up the paper size and orientation 'portrait' or 'portrait'
        $dompdf->setPaper('A4');

        return $dompdf;
    }
}
