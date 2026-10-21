<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exporta la pestaña de "Decisiones" que el usuario tiene abierta. Recibe
 * encabezados y filas ya armados (sin las columnas de dinero si el usuario
 * no tiene reportes.montos), así que sirve para los 7 reportes.
 */
class DecisionesExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    public function __construct(
        private string $titulo,
        private array $headings,
        private array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Excel limita el nombre de la hoja a 31 caracteres y prohíbe : \ / ? * [ ]
        return mb_substr(preg_replace('/[:\\\\\/?*\[\]]/', ' ', $this->titulo), 0, 31);
    }
}
