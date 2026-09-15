#!/bin/sh
# Aggiornamento di un ambiente Passpartour.
#
#   ./aggiorna.sh passpartour-staging staging.env     ← prima qui
#   ./aggiorna.sh passpartour-prod    prod.env        ← poi qui, se il collaudo regge
#
# Scarica il codice, ricostruisce l'immagine e riavvia. Le migrazioni le applica
# da sé il contenitore all'avvio.
set -eu

PROGETTO="${1:?Indicare il progetto, es. passpartour-prod}"
VARIABILI="${2:?Indicare il file di variabili, es. prod.env}"

cd "$(dirname "$0")"
[ -f "$VARIABILI" ] || { echo "✗ Manca $VARIABILI (copialo da ${VARIABILI}.example)." >&2; exit 1; }

# Il backup si fa SEMPRE, non solo in produzione: sul collaudo ci lavorano le
# persone, e la strada indietro deve esistere già, non essere cercata dopo.
echo "→ Backup di sicurezza prima di aggiornare"
./backup-db.sh "$PROGETTO"

echo "→ Scarico il codice aggiornato"
git -C ../.. pull --ff-only

echo "→ Ricostruisco e riavvio '${PROGETTO}'"
docker compose -p "$PROGETTO" --env-file "$VARIABILI" up -d --build

echo "→ Attendo che l'applicazione risponda"
i=0
until docker compose -p "$PROGETTO" exec -T app php -r 'exit(0);' >/dev/null 2>&1; do
    i=$((i + 1))
    [ "$i" -lt 30 ] || { echo "✗ L'applicazione non è ripartita. Log:"; docker compose -p "$PROGETTO" logs --tail 50 app; exit 1; }
    sleep 2
done

echo "→ Rimuovo le immagini non più usate"
docker image prune -f >/dev/null

echo "✓ '${PROGETTO}' aggiornato. Controlla i log:  docker compose -p ${PROGETTO} logs -f app"
