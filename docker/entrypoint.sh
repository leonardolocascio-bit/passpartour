#!/bin/sh
# Avvio dell'immagine di produzione: attende/riprova il DB, applica le
# migrazioni, fa il seed dei dati demo se richiesto, poi avvia FrankenPHP.
#
# La stessa immagine serve produzione (gestionale.passpartour.com, dati reali)
# e collaudo (stagingest.passpartour.com, dati finti): la differenza è tutta
# nelle variabili d'ambiente, non qui.
set -e
cd /app

echo "→ Migrazioni (con attesa del database)..."
i=0
until php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration; do
    i=$((i + 1))
    if [ "$i" -ge 10 ]; then
        echo "✗ Database non raggiungibile dopo 10 tentativi."
        exit 1
    fi
    echo "  …database non pronto, riprovo tra 3s ($i/10)"
    sleep 3
done

# Seed dei dati demo SOLO se esplicitamente richiesto (DEMO_SEED=1) e a
# database vuoto. In produzione basta NON impostare DEMO_SEED: il database
# resta pulito e gli account veri si creano dal gestionale.
if [ "${DEMO_SEED:-0}" = "1" ]; then
    UTENTI=$(php bin/console dbal:run-sql "SELECT COUNT(*) FROM utente" 2>/dev/null | grep -oE '[0-9]+' | head -1 || echo 0)
    if [ "${UTENTI:-0}" = "0" ]; then
        echo "→ DEMO_SEED attivo e database vuoto: carico i dati demo..."
        php bin/console doctrine:fixtures:load --no-interaction
    else
        echo "→ Dati già presenti (${UTENTI} utenti): salto il seed."
    fi
else
    echo "→ DEMO_SEED non attivo: nessun dato demo (modalità release)."
fi

# Dietro il proxy si ascolta in HTTP semplice: l'HTTPS lo termina Caddy nel
# container proxy (docker/produzione/proxy/).
export SERVER_NAME="${SERVER_NAME:-:80}"
echo "→ Avvio FrankenPHP su ${SERVER_NAME}"
exec frankenphp run --config /etc/caddy/Caddyfile
