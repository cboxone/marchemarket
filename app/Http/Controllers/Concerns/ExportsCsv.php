<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV en flux : les lignes sont écrites au fur et à mesure
 * (`chunkById` côté appelant), sans jamais charger tout le jeu de résultats
 * en mémoire — utile dès que l'historique de commandes ou de reversements
 * grossit.
 *
 * Le point-virgule est utilisé comme séparateur plutôt que la virgule : les
 * montants FCFA n'ont pas de décimales à séparer, et Excel en français
 * (celui de la plupart des utilisateurs visés) attend un point-virgule par
 * défaut à l'ouverture d'un CSV. Un BOM UTF-8 est ajouté en tête pour que
 * les caractères accentués s'affichent correctement dans Excel.
 */
trait ExportsCsv
{
    /** @param list<string> $headers @param iterable<int, array<int, string|int|float|null>> $rows */
    private function csvDownload(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Filtre optionnel `from`/`to` (format YYYY-MM-DD) sur une colonne de date, partagé par les quatre exports. */
    private function applyDateRange($query, string $column, ?string $from, ?string $to)
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }

        if ($to) {
            $query->whereDate($column, '<=', $to);
        }

        return $query;
    }
}
