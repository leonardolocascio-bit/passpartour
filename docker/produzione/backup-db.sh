#!/bin/sh
# Backup del database di un ambiente Passpartour.
#
#   ./backup-db.sh passpartour-prod
#
# Fa un dump compresso, lo mette in /var/backups/passpartour e tiene gli ultimi
# 30 giorni. Pensato per essere lanciato da un timer di sistema (vedi
# passpartour-backup.timer), ma funziona anche a mano prima di un aggiornamento
# delicato.
#
# Il dump è la SOLA cosa che separa un guasto da una perdita di dati: se questo
# script smette di funzionare in silenzio, non se ne accorge nessuno. Per questo
# esce con errore rumoroso e verifica che il file prodotto non sia vuoto.
set -eu

PROGETTO="${1:-passpartour-prod}"
DESTINAZIONE="${BACKUP_DIR:-/var/backups/passpartour}"
GIORNI_RITENZIONE="${BACKUP_GIORNI:-30}"

STAMPA="$(date +%Y%m%d-%H%M%S)"
FILE="${DESTINAZIONE}/${PROGETTO}-${STAMPA}.sql.gz"

mkdir -p "$DESTINAZIONE"

# Il contenitore del database di quello stack, qualunque nome gli abbia dato
# Docker Compose.
CONTENITORE="$(docker compose -p "$PROGETTO" ps -q db)"
if [ -z "$CONTENITORE" ]; then
    echo "✗ Nessun database in esecuzione per il progetto '$PROGETTO'." >&2
    exit 1
fi

# L'utente e il database si leggono dal contenitore stesso: così lo script non
# ha una seconda copia delle credenziali da tenere allineata.
UTENTE="$(docker exec "$CONTENITORE" printenv POSTGRES_USER)"
NOME_DB="$(docker exec "$CONTENITORE" printenv POSTGRES_DB)"

echo "→ Dump di ${NOME_DB} (${PROGETTO}) in ${FILE}"
docker exec "$CONTENITORE" pg_dump -U "$UTENTE" -d "$NOME_DB" --clean --if-exists \
    | gzip -9 > "$FILE"

# Un dump troncato è peggio di nessun dump, perché dà falsa sicurezza.
DIMENSIONE="$(wc -c < "$FILE")"
if [ "$DIMENSIONE" -lt 1024 ]; then
    echo "✗ Il dump è di soli ${DIMENSIONE} byte: qualcosa è andato storto." >&2
    rm -f "$FILE"
    exit 1
fi

chmod 600 "$FILE"

echo "→ Elimino i backup più vecchi di ${GIORNI_RITENZIONE} giorni"
find "$DESTINAZIONE" -name "${PROGETTO}-*.sql.gz" -type f -mtime "+${GIORNI_RITENZIONE}" -delete

echo "✓ Fatto: $(ls -lh "$FILE" | awk '{print $5}') — $(ls -1 "$DESTINAZIONE"/${PROGETTO}-*.sql.gz | wc -l | tr -d ' ') copie conservate"
