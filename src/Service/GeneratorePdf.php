<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera PDF lato server da HTML (dompdf).
 */
class GeneratorePdf
{
    public function daHtml(string $html, string $orientamento = 'portrait'): string
    {
        $options = new Options();
        // DejaVu Sans è incluso in dompdf e supporta € e accenti
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientamento);
        $dompdf->render();

        return $dompdf->output();
    }
}
