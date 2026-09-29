#!/bin/bash
# tools/disciplinary-activar-local.sh — Activa el procediment disciplinari + aplica l'estratègia
# de comunicació de l'assessoria als documents de compliment. IDEMPOTENT. Executar des de l'arrel:
#   bash tools/disciplinary-activar-local.sh
set -e
cd "$(dirname "$0")/.."

echo "── 1) Lint dels fitxers nous ──"
for f in app/Http/Controllers/Api/DisciplinaryController.php app/Services/DisciplinaryEngine.php \
         app/Models/DisciplinaryCase.php app/Models/DisciplinaryElement.php \
         app/Models/DisciplinaryCaseEvent.php app/Models/DisciplinaryDocument.php \
         app/Models/DisciplinaryFaultType.php database/seeders/DisciplinaryFaultTypeSeeder.php \
         database/seeders/ComplianceSeeder.php \
         database/migrations/2026_07_29_000003_create_disciplinary_tables.php; do
  php -l "$f" >/dev/null || { echo "✗ ERROR de sintaxi a $f"; exit 1; }
done
echo "✓ lint net"

echo "── 2) Migració (5 taules disciplinàries) ──"
php artisan migrate --force

echo "── 3) Catàleg de faltes (conveni XII + ET) ──"
php artisan db:seed --class=DisciplinaryFaultTypeSeeder --force

echo "── 4) Documents de compliment: textos FINALS de l'assessoria (v2.0) ──"
php artisan tinker --execute="
\App\Models\ComplianceDocument::whereIn('tipus',['info_art90','politica_algoritmica','ropa'])->delete();
echo 'docs antics esborrats'.PHP_EOL;
"
php artisan db:seed --class=ComplianceSeeder --force

echo "── 5) Estratègia de comunicació (assessoria): publicar 1 i 2 amb acusament; RoPA NOMÉS pujat ──"
php artisan tinker --execute="
\App\Models\ComplianceDocument::whereIn('tipus',['info_art90','politica_algoritmica'])
  ->update(['estat'=>'publicat','published_at'=>now(),'published_by'=>1]);
echo 'publicats: info_art90 + politica_algoritmica (amb acusament individual)'.PHP_EOL;
echo 'ropa: es queda com a esborrany intern (transparència selectiva, no es comunica)'.PHP_EOL;
"

echo "── 6) Verificació ──"
php artisan tinker --execute="
foreach (\App\Models\ComplianceDocument::whereIn('tipus',['info_art90','politica_algoritmica','ropa'])->get() as \$d)
  echo str_pad(\$d->tipus,22).' v'.\$d->versio.' → '.\$d->estat.(\$d->requereix_acus?' (amb acus)':'').PHP_EOL;
echo 'tipus de falta al catàleg: '.\App\Models\DisciplinaryFaultType::count().PHP_EOL;
echo 'taules disciplinàries: '.(\Illuminate\Support\Facades\Schema::hasTable('disciplinary_cases')?'ok':'FALTEN').PHP_EOL;
"
echo "✓ FET. Pantalles: #/disciplinary (procediment) · #/compliance (documents) · #/worker/compliance (acusament)"
