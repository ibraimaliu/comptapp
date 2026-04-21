<?php
/**
 * Script pour créer un fichier exemple Excel (.xlsx)
 * À exécuter une fois pour générer plan_comptable_exemple.xlsx
 */

require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Plan Comptable');

// Header styling
$sheet->getStyle('A1:D1')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 12
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '667eea']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);

// Header row
$sheet->setCellValue('A1', 'Numéro');
$sheet->setCellValue('B1', 'Intitulé');
$sheet->setCellValue('C1', 'Catégorie');
$sheet->setCellValue('D1', 'Type');

// Data rows
$data = [
    ['1000', 'Caisse', 'Actif', 'Bilan'],
    ['1020', 'Banque', 'Actif', 'Bilan'],
    ['1030', 'CCP', 'Actif', 'Bilan'],
    ['1100', 'Clients', 'Actif', 'Bilan'],
    ['1170', 'TVA déductible', 'Actif', 'Bilan'],
    ['1200', 'Stocks de marchandises', 'Actif', 'Bilan'],
    ['1300', 'Immobilisations corporelles', 'Actif', 'Bilan'],
    ['1400', 'Immobilisations incorporelles', 'Actif', 'Bilan'],
    ['2000', 'Fournisseurs', 'Passif', 'Bilan'],
    ['2200', 'TVA due', 'Passif', 'Bilan'],
    ['2300', 'Charges sociales à payer', 'Passif', 'Bilan'],
    ['2500', 'Emprunts bancaires', 'Passif', 'Bilan'],
    ['2800', 'Capital social', 'Passif', 'Bilan'],
    ['2900', 'Résultat de l\'exercice', 'Passif', 'Bilan'],
    ['3000', 'Ventes de marchandises', 'Produit', 'Résultat'],
    ['3200', 'Prestations de services', 'Produit', 'Résultat'],
    ['3400', 'Produits financiers', 'Produit', 'Résultat'],
    ['3800', 'Autres produits d\'exploitation', 'Produit', 'Résultat'],
    ['4000', 'Achat de marchandises', 'Charge', 'Résultat'],
    ['4200', 'Achat de matières premières', 'Charge', 'Résultat'],
    ['5000', 'Charges de personnel', 'Charge', 'Résultat'],
    ['5700', 'Charges sociales', 'Charge', 'Résultat'],
    ['6000', 'Loyers et charges locatives', 'Charge', 'Résultat'],
    ['6100', 'Entretien et réparations', 'Charge', 'Résultat'],
    ['6200', 'Assurances', 'Charge', 'Résultat'],
    ['6300', 'Fournitures de bureau', 'Charge', 'Résultat'],
    ['6400', 'Téléphone et internet', 'Charge', 'Résultat'],
    ['6500', 'Frais postaux', 'Charge', 'Résultat'],
    ['6600', 'Publicité et marketing', 'Charge', 'Résultat'],
    ['6700', 'Déplacements et voyages', 'Charge', 'Résultat'],
    ['6800', 'Charges financières', 'Charge', 'Résultat'],
    ['6900', 'Amortissements', 'Charge', 'Résultat'],
    ['7000', 'Autres charges d\'exploitation', 'Charge', 'Résultat']
];

// Insert data
$row = 2;
foreach ($data as $rowData) {
    $sheet->setCellValue('A' . $row, $rowData[0]);
    $sheet->setCellValue('B' . $row, $rowData[1]);
    $sheet->setCellValue('C' . $row, $rowData[2]);
    $sheet->setCellValue('D' . $row, $rowData[3]);
    $row++;
}

// Auto-size columns
foreach (['A', 'B', 'C', 'D'] as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add borders
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC'],
        ],
    ],
];
$sheet->getStyle('A1:D' . ($row - 1))->applyFromArray($styleArray);

// Freeze first row
$sheet->freezePane('A2');

// Save file
$writer = new Xlsx($spreadsheet);
$writer->save('plan_comptable_exemple.xlsx');

echo "Fichier Excel créé avec succès : plan_comptable_exemple.xlsx\n";
?>
