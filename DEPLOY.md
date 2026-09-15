# Deploy — gli ambienti del gestionale Passpartour

Due ambienti sul dominio `passpartour.com`, **una sola immagine**. Quello che li
distingue non è il codice ma poche variabili: chi ha i dati veri e chi si
popola da sé con dati finti.

| | Produzione | Collaudo |
|---|---|---|
| Dominio | `gestionale.passpartour.com` | `stagingest.passpartour.com` |
| Dati | reali (lead, clienti, offerte) | finti, rigenerabili dalle fixtures |
| `DEMO_SEED` | assente | `1` |
| Indicizzazione | no (è uno strumento interno: il proxy risponde `Disallow` a `/robots.txt`) | no |
| Accesso | login del gestionale | login + password nel proxy (utente `collaudo`) |
| Backup | notturno, 30 giorni | notturno, 30 giorni |

Il dominio nudo `passpartour.com` (e `www`) è riservato al **sito web
pubblico**, che è un progetto a parte: nel `Caddyfile` il suo blocco è già
predisposto e commentato, insieme a `stagingweb.passpartour.com`. Quando il
sito esisterà basterà attivare quei blocchi e aggiungere due righe di DNS —
stesso server, stesso proxy.

**A cosa serve il collaudo:** aggiornamenti e integrazioni (Google Calendar,
Twilio, SMTP) si provano lì e si promuovono in produzione solo dopo. Non si
collauda sul gestionale che contiene i dati dei clienti. Fino a fine sviluppo
il collaudo è anche l'ambiente da far vedere: la produzione si accende quando
il gestionale è pronto.

---

## Produzione su Hetzner — installazione

Da fare una volta sola. Serve un server Hetzner Cloud con Ubuntu 24.04 e
**4 vCPU / 8 GB** (come per Billicon: CX32 se disponibile, altrimenti CPX32).
Sulla macchina girano due ambienti — produzione e collaudo, ognuno col suo
PostgreSQL e il suo worker — più, in prospettiva, il sito web: il disco ospita
i database, le immagini caricate, le immagini Docker e trenta giorni di backup.

Su Hetzner il disco si può solo ingrandire, mai ridurre — è l'unica scelta
irreversibile del giorno uno. CPU e RAM invece si cambiano quando serve, in su
e in giù, con un riavvio.

### 0. Prima ancora del server

- **Repository remoto.** `aggiorna.sh` fa `git pull`: serve un repository
  remoto (es. GitHub privato, come per Billicon) e questo progetto ancora non
  ce l'ha. Crearlo e fare il primo push prima di installare.
- **Intestazione.** Server nel progetto Hetzner intestato a **Passpartour srl**
  (i dati di lead e clienti sono suoi: titolare del trattamento e intestatario
  del contratto devono coincidere).

### 1. DNS — prima di tutto il resto

I certificati HTTPS si ottengono solo se i nomi puntano già al server, quindi
il DNS va messo per primo: la propagazione richiede da pochi minuti a qualche
ora.

Dal pannello del registrar di `passpartour.com`, con `IP_DEL_SERVER` preso
dalla console Hetzner:

| Tipo | Nome | Valore | Quando |
|---|---|---|---|
| `A` | `gestionale` | `IP_DEL_SERVER` | subito |
| `A` | `stagingest` | `IP_DEL_SERVER` | subito |
| `AAAA` | `gestionale`, `stagingest` | `IPv6_DEL_SERVER` (se assegnato) | subito |
| `A` | `@`, `www`, `stagingweb` | `IP_DEL_SERVER` | quando parte il sito web |

Verifica prima di proseguire:

```bash
dig +short gestionale.passpartour.com stagingest.passpartour.com
```

### 2. Il server

```bash
ssh root@IP_DEL_SERVER
```

```bash
apt update && apt upgrade -y && apt install -y docker.io docker-compose-v2 git ufw
```

Firewall: solo ssh e web. Il database non si affaccia mai su Internet.

```bash
ufw allow OpenSSH && ufw allow 80/tcp && ufw allow 443/tcp && ufw allow 443/udp && ufw --force enable
```

### 3. Il codice

```bash
git clone URL_DEL_REPOSITORY /opt/passpartour
```

### 4. Il proxy

È l'unico container affacciato su Internet: termina l'HTTPS per tutti i domini
e smista in base al nome richiesto. Senza di lui produzione e collaudo si
contenderebbero la porta 443.

Il `Caddyfile` non si tocca: i due valori che cambiano da server a server —
l'email dei certificati e la password del collaudo — stanno in `proxy.env`,
che resta sul server. Così un aggiornamento non va mai in conflitto con
modifiche fatte a mano.

```bash
cd /opt/passpartour/docker/produzione/proxy && cp proxy.env.example proxy.env && chmod 600 proxy.env
```

L'impronta della password del collaudo si genera con:

```bash
docker run --rm caddy:2-alpine caddy hash-password
```

Compilato `proxy.env`:

```bash
docker network create passpartour-web
```

```bash
cd /opt/passpartour/docker/produzione/proxy && docker compose -p passpartour-proxy up -d
```

### 5. Il collaudo (per primo: fino a fine sviluppo è l'unico ambiente)

```bash
cd /opt/passpartour/docker/produzione && cp staging.env.example staging.env && chmod 600 staging.env
```

Compila `staging.env` (il file spiega ogni voce). I valori da generare:

```bash
openssl rand -hex 32      # APP_SECRET
```

```bash
openssl rand -base64 24   # POSTGRES_PASSWORD
```

```bash
openssl rand -hex 16      # PASSPARTOUR_WEBHOOK_TOKEN
```

Accensione:

```bash
cd /opt/passpartour/docker/produzione && docker compose -p passpartour-staging --env-file staging.env up -d --build
```

La prima costruzione richiede qualche minuto. Le migrazioni le applica da sé
il contenitore all'avvio; con `DEMO_SEED=1` e database vuoto carica anche i
dati demo (account `master@passpartour.local`, password `passpartour`).

```bash
docker compose -p passpartour-staging logs -f app
```

### 6. La produzione (quando lo sviluppo è finito)

```bash
cd /opt/passpartour/docker/produzione && cp prod.env.example prod.env && chmod 600 prod.env
```

Stessi valori da generare del collaudo (ma **diversi**: mai riusare segreti fra
ambienti). Poi:

```bash
cd /opt/passpartour/docker/produzione && docker compose -p passpartour-prod --env-file prod.env up -d --build
```

Il database resta **vuoto**: niente dati demo in produzione.

### 7. Backup notturni

```bash
cd /opt/passpartour/docker/produzione && cp systemd/passpartour-backup.* /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now passpartour-backup.timer
```

Verifica che il primo giro funzioni **subito**, senza aspettare la notte:

```bash
systemctl start passpartour-backup && journalctl -u passpartour-backup -n 20
```

Dump in `/var/backups/passpartour`, compressi, ultimi 30 giorni. Lo script si
ferma con errore se il dump esce vuoto: un backup troncato è peggio di nessun
backup, perché dà falsa sicurezza.

(Finché esiste solo il collaudo, il backup della produzione fallirà con
"Nessun database in esecuzione": è atteso, il collaudo viene comunque salvato.)

### 8. Sincronizzazione Google Calendar (facoltativa)

Quando le credenziali Google sono configurate e almeno un agente ha collegato
il calendario:

```bash
cd /opt/passpartour/docker/produzione && cp systemd/passpartour-google-sync.* /etc/systemd/system/ && systemctl daemon-reload && systemctl enable --now passpartour-google-sync.timer
```

Ogni 10 minuti esegue `app:google:sincronizza` nella produzione.

---

## Il worker delle email

Ogni ambiente ha un container `worker` (`messenger:consume async`): **le email
dei preventivi e delle offerte partono da lì**, non dalla richiesta web. Se le
email "non partono", i posti dove guardare sono due:

```bash
docker compose -p passpartour-prod logs -f worker
```

e la variabile `MAILER_DSN` in `prod.env` — finché vale `null://null` le email
si accodano e si scartano in silenzio, per progetto.

## Aggiornare

Prima il collaudo, poi la produzione.

```bash
cd /opt/passpartour/docker/produzione && ./aggiorna.sh passpartour-staging staging.env
```

Provato su `stagingest.passpartour.com` che tutto regga:

```bash
cd /opt/passpartour/docker/produzione && ./aggiorna.sh passpartour-prod prod.env
```

Lo script fa **prima** un backup: se una migrazione va storta, la strada
indietro deve esistere già, non essere cercata dopo.

## Ripristinare un backup

```bash
ls -lh /var/backups/passpartour
```

```bash
cd /opt/passpartour/docker/produzione && ./ripristina-db.sh passpartour-prod /var/backups/passpartour/NOMEFILE.sql.gz
```

**Provalo prima che serva**, ripristinando sul collaudo: un backup mai
ripristinato non è un backup, è un file.

---

## Servizi esterni da puntare sui domini

| Servizio | Cosa impostare |
|---|---|
| Piattaforme di lead gen | Webhook `POST https://gestionale.passpartour.com/webhook/lead/{token}` con il token di `prod.env`. Per le prove: stesso percorso su `stagingest.passpartour.com` (il proxy lascia passare `/webhook/*` senza password) con il token di `staging.env`. |
| Google Cloud (Calendar) | Redirect OAuth autorizzati: `https://gestionale.passpartour.com/impostazioni/google/callback` e `https://stagingest.passpartour.com/impostazioni/google/callback`. |
| SMTP | `MAILER_DSN` in `prod.env` + SPF/DKIM del dominio mittente, altrimenti le email dei preventivi finiscono in spam. |
| Twilio | Numeri/WhatsApp di produzione solo in `prod.env`; sul collaudo la sandbox. |
| Unsplash / Anthropic | Chiavi in `prod.env` (facoltative: senza, ricerca immagini e caption AI restano spente ma tutto il resto funziona). |

## Sicure già in piedi

- **`stagingest` è protetto due volte**: password nel proxy (tranne
  `/webhook/*`, che ha il suo token) e `Disallow` su `/robots.txt`.
- **Il gestionale di produzione non si fa indicizzare**: il `Disallow` sta nel
  proxy, quindi vale qualunque cosa faccia l'applicazione.
- **Le immagini della libreria stanno su un volume** (`/app/public/uploads`):
  un aggiornamento dell'immagine non se le porta via.
- **I database non pubblicano porte**: si raggiungono solo via ssh + docker.
- **Ogni ambiente ha il suo webhook token**: un token di prova finito su una
  piattaforma sbagliata non può scrivere lead in produzione.

## Da fare quando entrano i dati veri

1. **Backup fuori dal server.** I dump stanno sullo stesso disco dei dati: una
   Storage Box Hetzner costa pochi euro e `backup-db.sh` è pronto a spedirceli.
2. **Un controllo esterno che avvisi** se il gestionale cade.
3. **SMTP vero e dominio mittente allineato** (`noreply@passpartour.com` con
   SPF/DKIM) prima di inviare il primo preventivo a un cliente reale.
