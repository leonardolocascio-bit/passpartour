#!/bin/sh
# Ripristino di un backup del database.
#
#   ./ripristina-db.sh passpartour-prod /var/backups/passpartour/passpartour-prod-20260915-031500.sql.gz
#
# Un backup che non è mai stato ripristinato non è un backup: è un file. Questo
# script serve anche per la PROVA periodica — ripristinare su
# "passpartour-staging" e verificare che l'applicazione parta, senza toccare la
# produzione.
#
# ATTENZIONE: sovrascrive il database di destinazione. Chiede conferma.
set -eu

PROGETTO="${1:?Indicare il progetto, es. passpartour-staging}"
ARCHIVIO="${2:?Indicare il file di backup .sql.gz}"

[ -f "$ARCHIVIO" ] || { echo "✗ File inesistente: $ARCHIVIO" >&2; exit 1; }

CONTENITORE="$(docker compose -p "$PROGETTO" ps -q db)"
[ -n "$CONTENITORE" ] || { echo "✗ Nessun database in esecuzione per '$PROGETTO'." >&2; exit 1; }

UTENTE="$(docker exec "$CONTENITORE" printenv POSTGRES_USER)"
NOME_DB="$(docker exec "$CONTENITORE" printenv POSTGRES_DB)"

echo "Stai per SOVRASCRIVERE il database '${NOME_DB}' dell'ambiente '${PROGETTO}'"
echo "con il contenuto di ${ARCHIVIO}."
printf "Scrivi il nome del progetto per confermare: "
read -r CONFERMA
[ "$CONFERMA" = "$PROGETTO" ] || { echo "Annullato."; exit 1; }

echo "→ Fermo il worker (non deve consumare la coda mentre si riscrive il database)"
docker compose -p "$PROGETTO" stop worker

echo "→ Ripristino in corso..."
gunzip -c "$ARCHIVIO" | docker exec -i "$CONTENITORE" psql -U "$UTENTE" -d "$NOME_DB" -v ON_ERROR_STOP=1 --quiet

echo "→ Riavvio l'applicazione (le migrazioni girano da sole all'avvio)"
docker compose -p "$PROGETTO" restart app
docker compose -p "$PROGETTO" start worker

echo "✓ Ripristinato. Controlla che il gestionale risponda prima di considerarlo finito."
