# Immagine di produzione del gestionale Passpartour.
# Server FrankenPHP, APP_ENV=prod: nessun profiler esposto, asset compilati.
#
# La stessa immagine serve produzione e collaudo: cambia solo il file di
# variabili con cui la si avvia (vedi docker/produzione/compose.yaml).
FROM dunglas/frankenphp:1-php8.5

# Estensioni PHP richieste (PostgreSQL, intl, zip, opcache; gd per le immagini
# nei PDF generati con dompdf).
RUN install-php-extensions pdo_pgsql intl zip opcache gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Build in modalità produzione: dipendenze, asset compilati in public/assets,
# cache di prod riscaldata. I valori reali (DATABASE_URL, chiavi) arrivano a
# runtime dal file di variabili; qui basta un APP_SECRET segnaposto.
ENV APP_ENV=prod
ENV APP_SECRET=build-placeholder
# Nel container Composer gira come root: senza questo disabilita i plugin
# (symfony/runtime e symfony/flex) e il build fallisce ("Symfony Runtime is missing").
ENV COMPOSER_ALLOW_SUPERUSER=1
# install --no-scripts (salta gli auto-scripts basati su `symfony-cmd`, non
# disponibile in build) → dump-autoload rigenera vendor/autoload_runtime.php →
# comandi di preparazione + compilazione asset prod eseguiti esplicitamente.
# I pacchetti dev restano: il bundle fixtures serve al seed del collaudo.
RUN composer install --no-interaction --no-progress --prefer-dist --no-scripts \
 && composer dump-autoload --no-interaction \
 && php bin/console importmap:install \
 && php bin/console asset-map:compile \
 && php bin/console cache:clear \
 && chmod +x docker/entrypoint.sh

ENTRYPOINT ["/app/docker/entrypoint.sh"]
