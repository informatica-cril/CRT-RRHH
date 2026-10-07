<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Plantilla d'alta massiva de personal (Excel de mostra amb capçalera).
 * UN sol fitxer per a les DUES apps: crea la fitxa a RRHH i la compte d'accés a domi,
 * creuades pel DNI. Vegeu docs/PROPOSTA-CREDENCIALS-ENS.md.
 *
 * GET /api/v1/users/import-template  -> descarrega alta_personal_mostra.xlsx
 */
class ImportTemplateController extends Controller
{
    /** Columnes de la plantilla: clau tècnica, capçalera visible, descripció, valors vàlids. */
    private function columnes(): array
    {
        return [
            ['nom_complet',        'Nom complet',            'Nom i cognoms de la persona.', ''],
            ['dni',                'DNI/NIE',                'CLAU que creua RRHH i domi. Obligatori i únic.', ''],
            ['email',              'Email corporatiu',       'Per a credencials i recuperació. Obligatori i únic.', ''],
            ['telefon',            'Telèfon',                'Opcional.', ''],
            ['data_naixement',     'Data naixement',         'Format AAAA-MM-DD. Opcional.', ''],
            ['usuari_domi',        'Usuari (domi)',          'Nom d\'usuari per entrar a Domiciliària (màx. 15 caràcters).', ''],
            ['privilegi_domi',     'Perfil (domi)',          'Rol d\'accés a Domiciliària.',
                'Domiciliaria · Coordinacio · Administrador · Direccio · Medico · Codificador · AtencioClient · tpo · Valorador'],
            ['rol_rrhh',           'Rol (RRHH)',             'Rol a l\'app de RRHH.', 'worker · admin · hr (coordinació ja no és un rol: posa-ho a la categoria)'],
            ['ambit',              'Àmbit',                  'Zona de treball.', 'DOMICILIARIA · DOMICILIARIA_VALLES · AMBULATORIA'],
            ['categoria',          'Categoria professional', 'Lloc de treball.',
                'Fisioterapeuta · Logopeda · Terapeuta Ocupacional · Coordinación Vallés · Coordinación BCN · Administracion · Recepción · Gerencia · Informatica · Limpieza · Responsable RRHH'],
            ['jornada_setmanal_h', 'Jornada (h/setmana)',    'Hores setmanals de contracte. Número.', ''],
            ['relacio',            'Relació',                'Tipus de relació. Determina el fichatge i les hores complementàries.', 'laboral · autonom'],
            ['disponibilitat_h',   'Disponibilitat (h/set)', 'NOMÉS autònoms: hores/setmana ofertes. Buit si és laboral.', ''],
            ['segon_factor',       'Segon factor',           'Mètode de 2FA. Per defecte dispositiu (llave d\'empresa).', 'dispositiu · totp'],
            ['municipis',          'Municipis (valorador)',  'Per a valoradors: municipis separats per ";" (ex: Sant Cugat del Vallès; Rubí).', ''],
            ['especialitat',       'Especialitat clínica',   'Opcional (respiratori, sòl pèlvic…). Separades per ";".', ''],
            ['lot',                'Lot territorial',        'Lot de clàusula del contracte. Separats per ";" si en cobreix més d\'un. '
                                                           . 'Sense lot, la persona NO surt al quadre de disponibilitat.', 'B1 · B9'],
            ['departament',        'Departament',            'Servei i modalitat. Separats per ";" si en fa més d\'un. '
                                                           . 'Sense departament, la persona NO surt al quadre de disponibilitat.',
                                                             'RHB_DOMI · RHB_AMBU · LOGO_DOMI · LOGO_AMBU · TO_DOMI'],
        ];
    }

    /** Files d'exemple: una persona laboral i una col·laboradora autònoma. */
    private function exemples(): array
    {
        return [
            ['Anna Exemple Fisio', '12345678Z', 'anna.exemple@crtbcn.cat', '600111222', '1990-05-14',
             'A.Exemple', 'Domiciliaria', 'worker', 'DOMICILIARIA', 'Fisioterapeuta', '37.5',
             'laboral', '', 'dispositiu', '', '', 'B1', 'RHB_DOMI'],
            ['Joan Autonom Valorador', '87654321X', 'joan.autonom@crtbcn.cat', '600333444', '1985-11-02',
             'J.Autonom', 'Valorador', 'worker', 'DOMICILIARIA_VALLES', 'Fisioterapeuta', '',
             'autonom', '20', 'totp', 'Sant Cugat del Vallès; Rubí', 'respiratori', 'B9', 'RHB_DOMI; RHB_AMBU'],
        ];
    }

    public function download()
    {
        $cols = $this->columnes();
        $ss = new Spreadsheet();

        // ── Full 1: Altes ──
        $s = $ss->getActiveSheet();
        $s->setTitle('Altes');
        foreach ($cols as $i => $c) {
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . '1';
            $s->setCellValue($cell, $c[1]);
            $s->getColumnDimensionByColumn($i + 1)->setWidth(max(14, min(30, mb_strlen($c[1]) + 4)));
        }
        // Capçalera amb estil.
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
        $head = $s->getStyle("A1:{$lastCol}1");
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A5C');
        $head->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $s->getRowDimension(1)->setRowHeight(22);
        $s->freezePane('A2');
        // Files d'exemple (en gris clar, per esborrar).
        $r = 2;
        foreach ($this->exemples() as $ex) {
            foreach ($ex as $i => $v) $s->setCellValueExplicit(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1) . $r,
                (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $s->getStyle("A{$r}:{$lastCol}{$r}")->getFont()->getColor()->setRGB('888888');
            $r++;
        }

        // ── Full 2: Instruccions ──
        $ins = $ss->createSheet();
        $ins->setTitle('Instruccions');
        $ins->setCellValue('A1', 'Columna');
        $ins->setCellValue('B1', 'Què hi va');
        $ins->setCellValue('C1', 'Valors vàlids');
        $ins->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $ins->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1A3A5C');
        $ins->getColumnDimension('A')->setWidth(24);
        $ins->getColumnDimension('B')->setWidth(60);
        $ins->getColumnDimension('C')->setWidth(70);
        $row = 2;
        foreach ($cols as $c) {
            $ins->setCellValue("A{$row}", $c[1]);
            $ins->setCellValue("B{$row}", $c[2]);
            $ins->setCellValue("C{$row}", $c[3]);
            $ins->getStyle("A{$row}:C{$row}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $row++;
        }
        $ins->setCellValue("A{$row}", '');
        $row++;
        $ins->setCellValue("A{$row}", 'IMPORTANT');
        $ins->setCellValue("B{$row}", 'Esborra les 2 files d\'exemple abans d\'omplir. El DNI creua RRHH i domi: si ja existeix, la persona s\'actualitza, no es duplica. La contrasenya no va a l\'Excel: es genera una de temporal i s\'envia per email.');
        $ins->getStyle("A{$row}")->getFont()->setBold(true);
        $ins->getStyle("B{$row}")->getAlignment()->setWrapText(true);

        $ss->setActiveSheetIndex(0);

        $filename = 'alta_personal_mostra.xlsx';
        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
