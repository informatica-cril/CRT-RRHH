<?php

namespace Database\Seeders;

use App\Models\ComplianceDocument;
use Illuminate\Database\Seeder;

/**
 * Documents de governança (F0 punts 4, 5 i 7) — v2.0: TEXTOS FINALS validats per l'assessoria
 * jurídica (jul 2026), amb la seva estratègia de comunicació:
 *   · info_art90 → es PUBLICA amb acusament individual (trasllada la càrrega de la prova).
 *   · politica_algoritmica → es PUBLICA amb acusament individual (enfocament "suport, no decisori").
 *   · ropa → NOMÉS es puja (esborrany intern): transparència selectiva, no es comunica al treballador;
 *     la seva existència es menciona al doc 1 i està a disposició de l'autoritat de control.
 * Idempotent: firstOrCreate per tipus → si ja existeix (fins i tot publicat), no el toca.
 */
class ComplianceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->documents() as $doc) {
            ComplianceDocument::firstOrCreate(['tipus' => $doc['tipus']], $doc);
        }
    }

    private function documents(): array
    {
        return [
            [
                'tipus'          => 'info_art90',
                'titol'          => 'Informació sobre protecció de dades (control laboral)',
                'versio'         => '2.0',
                'base_legal'     => 'RGPD art. 13 · LOPDGDD art. 11 i 90 · ET art. 20.3',
                'requereix_acus' => true,
                'estat'          => 'esborrany',
                'contingut'      => <<<'MD'
# CRT – INFORMACIÓN SOBRE PROTECCIÓN DE DATOS (CONTROL LABORAL)

1. **Responsable del Tratamiento:** CRT.

2. **Finalidad:** Verificación del cumplimiento por el trabajador de sus obligaciones y deberes laborales. Incluye, de forma no exhaustiva: control de puntualidad y presencia; verificación del cumplimiento de la jornada laboral; control de la prestación efectiva del servicio y rendimiento; gestión del régimen disciplinario.

3. **Base Jurídica:** ejecución del contrato de trabajo (Art. 6.1.b RGPD) e interés legítimo de la empresa (Art. 6.1.f RGPD) en organizar y supervisar la actividad productiva, conforme al artículo 20.3 del Estatuto de los Trabajadores.

4. **Categorías de Datos:** datos identificativos, registros de acceso, horarios, logs de actividad en sistemas corporativos y cualquier dato derivado de las herramientas de trabajo facilitadas.

5. **Destinatarios:** no se prevén cesiones a terceros, salvo obligación legal (Seguridad Social, Inspección de Trabajo, órganos judiciales).

6. **Decisiones Automatizadas:** no se adoptan decisiones basadas únicamente en el tratamiento automatizado que produzcan efectos jurídicos significativos. Cualquier medida disciplinaria será precedida de una validación humana por parte de la Dirección.

7. **Derechos:** acceso, rectificación, supresión, oposición, limitación y portabilidad mediante comunicación al Delegado de Protección de Datos (DPD) en la dirección de correo habilitada. Existe un Registro de Actividades de Tratamiento a disposición de la autoridad de control.
MD,
            ],
            [
                'tipus'          => 'politica_algoritmica',
                'titol'          => 'Política d\'ús d\'eines tecnològiques de suport a la gestió',
                'versio'         => '2.0',
                'base_legal'     => 'ET art. 64.4.d · RGPD art. 22',
                'requereix_acus' => true,
                'estat'          => 'esborrany',
                'contingut'      => <<<'MD'
# CRT – POLÍTICA DE USO DE HERRAMIENTAS TECNOLÓGICAS DE APOYO A LA GESTIÓN

1. **Objeto:** informar sobre el uso de sistemas tecnológicos que asisten en la monitorización de la actividad laboral.

2. **Funcionamiento del Sistema:** las herramientas utilizadas por CRT actúan como soporte técnico para la acreditación de hechos objetivos (registros de actividad, tiempos de respuesta, cumplimiento de protocolos). El sistema genera borradores de revisión que son analizados exclusivamente por personal cualificado de la Dirección.

3. **Intervención Humana:** la calificación de cualquier conducta y la decisión final sobre medidas organizativas o disciplinarias corresponden siempre a una persona física con capacidad de decisión en la empresa.

4. **Procedimiento de Impugnación:** el trabajador que considere que una decisión basada en estos soportes afecta a sus derechos podrá solicitar una revisión en el plazo de 10 días hábiles desde la notificación de la decisión. La solicitud se dirigirá por escrito al Departamento de Recursos Humanos, expresando su punto de vista y aportando las alegaciones que considere oportunas. La empresa resolverá de forma motivada en un plazo máximo de 10 días hábiles (excluyendo sábados, domingos y festivos).
MD,
            ],
            [
                'tipus'          => 'ropa',
                'titol'          => 'Registre d\'activitats de tractament — Control laboral i règim disciplinari',
                'versio'         => '2.0',
                'base_legal'     => 'RGPD art. 30',
                'requereix_acus' => false,
                'estat'          => 'esborrany',
                'contingut'      => <<<'MD'
# ACTIVIDAD DE TRATAMIENTO: GESTIÓN DE CONTROL LABORAL Y RÉGIMEN DISCIPLINARIO

- **Finalidad:** control de la prestación laboral y ejercicio de la potestad disciplinaria.
- **Base Jurídica:** interés legítimo (Art. 6.1.f RGPD) y cumplimiento de obligaciones laborales (Art. 20.3 ET).
- **Colectivos afectados:**
  1. **Treballadors (personal en plantilla):** sujetos a control laboral pleno y régimen disciplinario del XII Convenio de la Sanidad Concertada de Catalunya.
  2. **Col·laboradors externs (autónomos/mercantiles):** el tratamiento se limita estrictamente a la verificación de la ejecución del encargo mercantil y coordinación de actividades preventivas. No se aplica control disciplinario laboral.
- **Categorías de Datos:** datos de contacto, registros de jornada, logs de sistemas, informes de rendimiento.
- **Plazos de Conservación:** durante la vigencia de la relación y, posteriormente, durante los plazos de prescripción de infracciones laborales (3 años para faltas muy graves) y acciones civiles (5 años).
- **Medidas de Seguridad:** cifrado de comunicaciones, control de acceso restringido a personal de RRHH, cadena de hash con sello de tiempo diario (FNMT) y auditorías periódicas.
MD,
            ],
        ];
    }
}
