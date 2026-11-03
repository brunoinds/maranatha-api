<?php

namespace App\Support\Creators\Reports;

use App\Models\Invoice;
use Carbon\Carbon;

// Uma boleta em PDF não entrou no PDF do reporte: leva a boleta junto para o app dizer qual é
class ReportInvoicePdfException extends \RuntimeException
{
    public function __construct(public readonly Invoice $invoice, \Throwable $previous)
    {
        $date = $invoice->date ? Carbon::parse($invoice->date)->format('d/m/Y') : '';
        $label = trim($invoice->description . ($date ? " ($date)" : ''));

        parent::__construct("No se pudo añadir el PDF de la boleta \"$label\" al reporte. Reemplace el archivo de esa boleta por una foto o por otro PDF.", 0, $previous);
    }
}
