#!/bin/bash
# tools/disciplinary-smoke-test.sh — Prova END-TO-END del flux disciplinari via API (dades TEST.*).
# Exercita: catàleg → obrir cas → GUARDES (comunicat sense evidència = bloquejat; resolt sense
# motivació = bloquejat) → instrucció → comunicat → document → signatura humana → resolució → historial.
#   bash tools/disciplinary-smoke-test.sh
set -e
cd "$(dirname "$0")/.."
API="http://127.0.0.1:8000/api/v1"

TOK=$(curl -s -X POST "$API/auth/login" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"email":"admin@crtbcn.cat","password":"123456"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')
[ -n "$TOK" ] || { echo "✗ login KO"; exit 1; }
H="Authorization: Bearer $TOK"; J="Content-Type: application/json"; A="Accept: application/json"
echo "✓ login (token ${TOK:0:8}…)"

N=$(curl -s -H "$H" -H "$A" "$API/disciplinary/fault-types" | php -r 'echo count(json_decode(stream_get_contents(STDIN)));')
echo "✓ catàleg: $N tipus de falta"

CASE=$(curl -s -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases" \
  -d '{"professional":"TEST.Flux","vincle":"laboral","tipus_falta":"puntualitat","data_coneixement":"2026-07-28","data_fet":"2026-07-27"}')
ID=$(echo "$CASE" | php -r 'echo json_decode(stream_get_contents(STDIN))->id;')
echo "$CASE" | php -r '$c=json_decode(stream_get_contents(STDIN));
  echo "✓ cas #".$c->id." obert · estat=".$c->estat." · gravetat=".$c->gravetat." · prescriu=".$c->data_prescripcio." (".$c->dies_prescripcio." dies)\n";'

echo "── GUARDA 1: comunicat SENSE evidència lícita (esperat: 422 bloquejat) ──"
CODE=$(curl -s -o /tmp/g1.json -w '%{http_code}' -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/transition" -d '{"estat":"comunicat"}')
echo "  HTTP $CODE · $(php -r 'echo json_decode(file_get_contents("/tmp/g1.json"))->message ?? "";')"
[ "$CODE" = "422" ] && echo "  ✓ guarda funciona" || echo "  ✗ ATENCIÓ: no ha bloquejat!"

curl -s -X PUT -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID" -d '{"te_evidencia_licita":true}' -o /dev/null
curl -s -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/transition" -d '{"estat":"instruccio"}' -o /dev/null
R=$(curl -s -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/transition" -d '{"estat":"comunicat"}')
echo "$R" | php -r '$c=json_decode(stream_get_contents(STDIN));
  echo "✓ instrucció → comunicat · termini al·legacions=".$c->termini_alegacions."\n";'

echo "── GUARDA 2: resolt SENSE motivació (esperat: 422 bloquejat) ──"
CODE=$(curl -s -o /tmp/g2.json -w '%{http_code}' -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/transition" -d '{"estat":"resolt","resolucio_tipus":"amonestacio"}')
echo "  HTTP $CODE · $(php -r 'echo json_decode(file_get_contents("/tmp/g2.json"))->message ?? "";')"
[ "$CODE" = "422" ] && echo "  ✓ guarda funciona" || echo "  ✗ ATENCIÓ: no ha bloquejat!"

DOC=$(curl -s -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/documents" -d '{"tipus":"amonestacio"}')
DID=$(echo "$DOC" | php -r 'echo json_decode(stream_get_contents(STDIN))->id;')
curl -s -X POST -H "$H" -H "$A" "$API/disciplinary/documents/$DID/sign" | php -r '$d=json_decode(stream_get_contents(STDIN));
  echo "✓ document #".$d->id." «".$d->tipus."» → ".$d->estat." per ".$d->signat_per."\n";'

curl -s -X POST -H "$H" -H "$J" -H "$A" "$API/disciplinary/cases/$ID/transition" \
  -d '{"estat":"resolt","resolucio_tipus":"amonestacio","resolucio_motivacio":"Cas de prova del flux complet (TEST)."}' \
  | php -r '$c=json_decode(stream_get_contents(STDIN)); echo "✓ RESOLT · signat per ".$c->resolt_per." · ".$c->resolucio_tipus."\n";'

echo "── Historial (cadena de custòdia) ──"
curl -s -H "$H" -H "$A" "$API/disciplinary/cases/$ID" | php -r '$c=json_decode(stream_get_contents(STDIN));
  foreach($c->events as $e) echo "  ".$e->created_at."  ".($e->estat_de?:"·")." → ".$e->estat_a."  (".$e->actor.")  ".($e->nota??"")."\n";'
echo "✓ SMOKE TEST COMPLET (cas TEST.Flux #$ID queda com a evidència de prova; arxivable des de la UI)"
