<?php

namespace Database\Seeders;

use App\Models\DisciplinaryFaultType;
use Illuminate\Database\Seeder;

/**
 * Catàleg de tipus de falta (XII Conveni sanitari de Catalunya + ET). Idempotent (updateOrCreate per
 * clau). prescripcio_dies segons ET 60.2 (menys_greu tractat com lleu=10, a validar per assessoria).
 */
class DisciplinaryFaultTypeSeeder extends Seeder
{
    public function run(): void
    {
        $faltes = [
            ['clau' => 'puntualitat', 'descripcio' => 'Puntualitat injustificada (>10 i <20 min)', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.A', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'abandon_servei', 'descripcio' => 'Abandó del servei sense permís', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.B', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'negligencia_servei', 'descripcio' => 'Negligència o desídia que afecta la marxa del servei', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.C', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => true],
            ['clau' => 'faltar_dia', 'descripcio' => 'Faltar un dia sense causa justificada', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.B', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'simulacio_malaltia', 'descripcio' => 'Simulació de malaltia o accident', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.D', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'incompliment_legal_hc', 'descripcio' => 'Incompliment legal reincident de la HC (negligència professional)', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.G', 'base_et' => 'art.54.2.d', 'prescripcio_dies' => 20, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'absencia_repetida', 'descripcio' => 'Faltes repetides i injustificades d\'assistència/puntualitat', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.64.4.A', 'base_et' => 'art.54.2.a', 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'rendiment_disminucio', 'descripcio' => 'Disminució continuada i voluntària del rendiment', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.64.4.A', 'base_et' => 'art.54.2.e', 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => true, 'requereix_afectacio_servei' => true],
            ['clau' => 'transgressio_bona_fe', 'descripcio' => 'Transgressió de la bona fe contractual / abús de confiança', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.64.4.A', 'base_et' => 'art.54.2.d', 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'assetjament', 'descripcio' => 'Assetjament sexual (art.67: greu o molt greu segons les circumstàncies)', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.67', 'base_et' => 'art.54.2.g', 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'desatencio', 'descripcio' => 'Desatenció o inconsideració amb persones ateses durant el servei', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.B', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'pulcritud', 'descripcio' => 'Manca de pulcritud personal', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.C', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'baixa_no_cursada', 'descripcio' => 'No cursar en temps oportú la baixa per malaltia', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.D', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'canvi_domicili', 'descripcio' => 'No comunicar el canvi de domicili en 5 dies', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.E', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'inobservanca_lleu', 'descripcio' => 'Inobservança intranscendent de normes o mesures reglamentàries', 'grau_base' => 'lleu', 'base_conveni' => 'art.64.1.F', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 3, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'reiteracio_lleus', 'descripcio' => 'Reiteració o reincidència en faltes lleus', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.A', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => true, 'requereix_afectacio_servei' => false],
            ['clau' => 'vicissituds_familiars', 'descripcio' => 'No comunicar en 5 dies vicissituds familiars que afecten assegurances socials i plus familiar', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.D', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'desobediencia', 'descripcio' => 'Desobediència als superiors en matèria de feina (si no és de paraula, pot ser greu)', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.E', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 2, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'imprudencia_seguretat', 'descripcio' => 'Imprudència respecte de les normes de seguretat i higiene sense accident greu', 'grau_base' => 'menys_greu', 'base_conveni' => 'art.64.2.F', 'base_et' => null, 'prescripcio_dies' => 10, 'reincidencia_puja' => true, 'llindar_reincidencia' => 2, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'reiteracio_menys_greus', 'descripcio' => 'Reiteració o reincidència en faltes menys greus', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.A', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => true, 'requereix_afectacio_servei' => false],
            ['clau' => 'discussions_violentes', 'descripcio' => 'Blasfèmies o discussions injustificades o violentes durant el servei', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.C', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'falsejar_dades', 'descripcio' => 'Falsejar dades aportades en declaracions a efectes legals', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.E', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'embriaguesa', 'descripcio' => 'Embriaguesa no habitual (si és habitual, és molt greu)', 'grau_base' => 'greu', 'base_conveni' => 'art.64.3.F', 'base_et' => null, 'prescripcio_dies' => 20, 'reincidencia_puja' => true, 'llindar_reincidencia' => 2, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'abus_autoritat', 'descripcio' => 'Abús d\'autoritat per part dels caps', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.66', 'base_et' => null, 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
            ['clau' => 'ofenses_intimitat', 'descripcio' => 'Manca de respecte a la intimitat i ofenses verbals o físiques d\'ordre sexual', 'grau_base' => 'molt_greu', 'base_conveni' => 'art.64.4.B', 'base_et' => 'art.54.2.g', 'prescripcio_dies' => 60, 'reincidencia_puja' => false, 'llindar_reincidencia' => null, 'requereix_apercebiment' => false, 'requereix_afectacio_servei' => false],
        ];

        foreach ($faltes as $f) {
            DisciplinaryFaultType::updateOrCreate(['clau' => $f['clau']], $f);
        }
    }
}
