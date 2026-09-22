# Passpartour CRM

Gestionale per **agenzia viaggi**: **CRM** + **preventivatore**. Riceve i lead dalle campagne di lead gen e crea scenari (varianti di preventivo + automazioni di nurturing) per convertire. Terminologia di dominio in italiano.

Progetto nuovo e separato (non è BILLICON, che è immobiliare): stessa filosofia architetturale, dominio diverso.

## Stack
- Symfony 8.1 + Twig (UX/Turbo dove serve, **non SPA**) + Doctrine ORM 3
- PostgreSQL 16 locale (Homebrew `postgresql@16`, niente Docker)
- PHP 8.5, Symfony CLI

## Avvio
```bash
symfony server:start -d          # dev server (durante lo sviluppo girava su :8001 perché :8000 era di BILLICON)
php bin/console doctrine:fixtures:load --no-interaction   # ricarica dati demo
php bin/phpunit                   # test
```
DB: ruolo/db `passpartour` + `passpartour_test` (password `passpartour`). Connessione in `.env.local` (dev) e `.env.test` (test, DB base `passpartour` + suffisso `_test` aggiunto da Symfony).

## Deploy
Vedi **`DEPLOY.md`**. Server Hetzner unico (stile BILLICON): immagine FrankenPHP (`docker/Dockerfile`), stack prod+collaudo in `docker/produzione/` con proxy Caddy multi-dominio, **worker Messenger** (senza di lui le email non partono) e backup systemd. Domini: `gestionale.passpartourviaggi.com` (produzione, a fine sviluppo), `stagingest.passpartourviaggi.com` (collaudo, basic auth tranne `/webhook/*`); `passpartourviaggi.com`/`www` riservati al futuro sito web e `stagingweb` al suo collaudo (blocchi già predisposti e commentati nel Caddyfile).

## Account demo (password: `passpartour`)
- `master@passpartour.local` — ROLE_ADMIN
- `giulia@passpartour.local`, `marco@passpartour.local` — ROLE_AGENTE

Tutti i dati sono **demo**, rigenerabili dalle fixtures. Nessun dato reale.

## Modello di dominio
`src/Entity` (namespace piatto `App\Entity`): **Utente**, **Campagna** (lead gen), **Lead** (entità centrale: fonte, campagna, assegnatario, stato pipeline, destinazione/periodo/n.passeggeri/budget, consensoMarketing, cliente), **Cliente**, **Attivita** (task CRM), **Preventivo** → **ScenarioPreventivo** (le varianti) → **VoceCosto**.

`src/Enum`: FonteLead, StatoLead (kanban: nuovo→contattato→preventivo_inviato→trattativa→vinto/perso), TipoAttivita, StatoPreventivo, CategoriaVoce.

`src/Service`: **PreventivoCalculator** — `prezzo = costo netto × (1 + markup/100)`.

## Convenzioni / guardrail
- **Nessun calcolo nei template**: la logica sta nei servizi di dominio (UI rifattibile).
- Design system in `templates/base.html.twig` (token CSS = **palette del logo**: navy royal `#123a72`, arancio `#ea5b24`, ciano `#1f9fd6`, ambra `#f6a41f`); shell con sidebar in `app.html.twig`.
- **Loghi ufficiali** in `public/images/` (NON reinterpretare/ricreare): `passpartour-color.png` quadricromia orizzontale su fondo chiaro (login), `passpartour-white.png` bianco orizzontale su fondo scuro (sidebar navy), `passpartour-color-stacked.png` impilato, `favicon.png` pittogramma. Regola brand: quadricromia su bianco, monocromo bianco su scuro.
- Passi piccoli e verificabili, test a ogni milestone, commit atomici.

## Roadmap
- **M1 ✓** scaffolding, entità, auth (ruoli admin/agente), dashboard KPI, pagine indice, fixtures, smoke test.
- **M2 ✓** intake lead: webhook generico + import CSV + creazione manuale, dedup, tag fonte/campagna.
- **M3 ✓** CRM: kanban lead (drag&drop stato), scheda lead, attività, timeline.
- **M4 ✓** preventivatore: editor scenari + voci (collezioni annidate), calcolo prezzi, scheda comparativa, anteprima stampabile/PDF.
- **M5 ✓** automazioni di conversione: RegolaNurturing per stato → attività auto (MotoreNurturing), Agenda azioni, pagina Automazioni.
- **M6 ✓** dashboard direzionale (CruscottoService): KPI, lead per fonte/campagna, valore pipeline, performance agenti.

Tutte le 6 milestone core completate. Extra: PDF nativo preventivo (dompdf), commissioni + icone per categoria, viste calendario (giorno/settimana/mese/anno) e sync bidirezionale Google Calendar, inbox Meta (vedi sotto). 79 test (`php bin/phpunit`).

**M15 — Scheda Offerta** (in corso): la scheda offerta è il fulcro di vendite/preventivi (offerta accettata → vendita, personalizzata → preventivo). Form a **schede** (tab JS Generale/Caratteristiche/Offerta/Info utili, tutti i pannelli nel DOM → salvataggio unico). Fasi: **A ✓** Generale (destinazione macro/micro + tipologia/tema multi-selezione), **B ✓** Caratteristiche (alloggio, voli+scali+bagaglio, treni, navi, trasferimenti, extra, assicurazioni, comprende/non comprende), **C ✓** Offerta/economics (quota multipla persona/gruppo+sistemazione, supplemento singola, valuta, badge early booking/last minute/cancellazione, costi/MOL), **D** Info utili smart (meteo nel periodo, dress code/usi locali via ricerca/AI — tutti editabili), **E** aggancio vendita/preventivo. **Read-side ✓** (parziale): pagina dettaglio client-facing `/offerte/{id}` (`dettaglio.html.twig` + `_partials/_scheda_offerta.html.twig`, SENZA costi/MOL), catalogo con card cliccabili + badge, email offerta arricchita (quote/alloggio/comprende/badge, mai i costi). Stampa A4/A3 lasciata invariata (l'editor di stampa avanzato attende specifiche). Componenti riutilizzabili: chip picker (multi-select+custom) e `_partials/_segmenti_editor.html.twig` (+ `_js`) editor ripetibile generico guidato da schema campi (type text/datetime/select/datalist/textarea), serializza hidden JSON al submit, letto in controller da `decodeSegmenti()`. Enum caratteristiche: `TipologiaAlloggio`, `StelleAlloggio`, `TipoAssicurazione`, `TipoExtra` (+ `TrattamentoHotel` esteso). Colonne JSON su Offerta: alloggio, voli, bagaglio, treni, navi, trasferimenti, extra, assicurazioni, quote, costi; testo: comprende/nonComprende; scalari: valuta, supplementoSingola, badge (earlyBooking/lastMinute/cancellazioneGratuita+giorni). **MOL** in `App\Service\CalcolatoreMargine` (dato interno, non pubblicabile): MOL = imponibile della commissione (al netto IVA); IVA (default 22%) e ritenuta d'acconto sono aliquote **configurabili per offerta**; commissione da quota netta (quota−netta) o commissionabile (%/importo fisso); flag «a lordo IVA» → scorporo, altrimenti IVA sopra l'imponibile. Il calcolo vive nel servizio, mai nei template (il form mostra il breakdown ricalcolato al salvataggio). **Decisioni architetturali** (utente): sezioni ripetibili in colonne **JSON** (come `righe`/`varianti`/`impaginato`), colonne reali solo per i campi chiave su cui si filtra/fa badge; integrazioni smart in coda al modello dati. Le liste (tipologia, tema, e in seguito extra/assicurazioni…) NON sono enum rigidi: gli enum (`TipologiaViaggio`, `TemaViaggio`) danno solo le **voci predefinite** del picker, i valori scelti si salvano come array di stringhe in JSON e l'operatore può **aggiungere voci custom** (requisito "per ogni elenco a cascata dammi possibilità di aggiungere voci"). Componente riutilizzabile: `_partials/_chip_picker.html.twig` (markup) + `_partials/_chip_picker_js.html.twig` (JS, includere una volta per pagina) → hidden JSON letto in controller da `decodeChip()`; stili `.chip`/`.chip-picker` in base.html.twig. Fase A su `Offerta`: `destinazioneMacro`/`destinazioneMicro` (VARCHAR), `tipologie`/`temi` (JSON) con helper `get…Label()` che risolve enum→label lasciando intatte le custom.

**Sezioni catalogo** (Destinazioni, **Tipologie**, **Temi**): voci con immagine+descrizione, future pagine-categoria del sito. Tipologie/Temi condividono `AbstractCatalogoController` + `CatalogoType` + template `catalogo/` (interfaccia `VoceCatalogo`, entità `Tipologia`/`Tema`); image picker condiviso. Gli enum `TipologiaViaggio`/`TemaViaggio` restano per il chip picker dell'offerta e seedano queste tabelle.

**Anteprima pubblica offerta** (`/offerte/{id}/anteprima`): pagina standalone (estende base, niente sidebar) come apparirà sul sito; riusa `_partials/_scheda_offerta.html.twig`, SENZA costi/MOL. Base del futuro sito.

**Impaginatore** cover in **format brand** (verificato contro il post tipo): logo centrato, eyebrow tipologia in corsivo, destinazione come titolo hero centrato (auto-fit), badge prezzo «da €X» a sinistra, righe con icona a filo + parola chiave in grassetto. **Tema colore per slide** (`slides[].tema`): `scuro` (set bianco, badge ambra) o `chiaro` (logo/testi/icone in blu Passpartour, badge navy); duotone coordinato; icone emoji rese monocromatiche nel colore del tema via canvas offscreen+source-atop. Toggle `mostra.destinazione`/`mostra.tipologia`.

**Impaginatore offerte** (`/offerte/{id}/impaginatore`, `ImpaginatoreController`): genera contenuti social Instagram 3:4 (1080×1440, carosello max 20 slide, anteprima/export via canvas JS) e stampa A4/A3 verticale/orizzontale (`/offerte/{id}/stampa/{a4v|a4o|a3v|a3o}`, layout base — l'editor di stampa avanzato è in attesa di specifiche dall'utente). **Le righe/opzioni del viaggio (icona/argomento/testo/evidenza) e le varianti con prezzo si compilano nel configuratore offerta** (`_partials/_righe_editor.html.twig`, hidden `righe_json`/`varianti_json` → `Offerta.righe`/`Offerta.varianti` JSON, id stabili; variante = {campo: 'durata'|'validita'|'partenza'|id-riga, valore, prezzo testo libero anche «+50»}); l'impaginatore le eredita e vi si sceglie solo cosa mostrare (checkbox 📱 per slide, 💬 per caption — le slide referenziano righe e varianti per id in `Offerta.impaginato`; `migraRigheLegacy()` solleva il vecchio formato a oggetti). Sulla slide: le varianti di durata con prezzo sostituiscono il badge prezzo unico (pillole impilate, es. «2 NOTTI € 120 / 4 NOTTI € 250»), partenza/validità diventano righe virtuali, le varianti di riga si accodano al testo della riga. Campo `claim` sull'offerta. Sfondi scelti da Unsplash e scaricati in locale (niente CORS sul canvas). Caption con tono di voce: `CaptionGenerator` usa l'API Claude se `ANTHROPIC_API_KEY` è in `.env.local`, altrimenti template deterministici — mantenere sempre il fallback.

**Inbox Meta** (WhatsApp / Messenger / Instagram Direct della pagina Passpartour): inbox unificata in `/inbox` (nav «Inbox»). Entità `Conversazione` (canale enum `CanaleMessaggio`, `idEsterno` = wa_id/PSID/IGSID, unique canale+idEsterno, nonLetti, anteprima, lead opzionale) e `Messaggio` (direzione enum `DirezioneMessaggio`, idEsterno = mid/wamid unique per dedup webhook, stato ricevuto/inviato/consegnato/letto/errore, autore). Webhook unico `MetaWebhookController` su `/webhook/meta/{META_VERIFY_TOKEN}`: GET = handshake di verifica Meta, POST = eventi (firma HMAC verificata se `META_APP_SECRET` è valorizzato; risponde 200 anche su payload invalidi perché Meta ritenta sui codici di errore). `MetaInboxService` elabora i payload (oggetti `whatsapp_business_account`/`page`/`instagram`, echo della pagina registrati come USCITA, statuses WA aggiornano lo stato, allegati come placeholder `[Immagine]` ecc.) e aggancia automaticamente il lead per telefono (solo WA, via `trovaDuplicato`). `MetaClient` invia le risposte: WA Cloud API (`/{phone_number_id}/messages`) e Send API pagina (`/me/messages`, Messenger+IG). Dalla conversazione: rispondi, collega/scollega lead, «Crea lead da questa chat» (passa da `LeadIntakeService`, dedup inclusa). Config in `.env.local`: `META_VERIFY_TOKEN`, `META_APP_SECRET`, `META_PAGE_ACCESS_TOKEN`, `META_WHATSAPP_TOKEN`, `META_WHATSAPP_PHONE_NUMBER_ID` (i form mostrano un hint se mancano; niente pannello UI). In collaudo `/webhook/*` è già escluso dalla basic auth. Test in `tests/InboxMetaTest.php`. **Tempo reale**: `InboxRealtime` pubblica su Mercure (hub **integrato in FrankenPHP**, acceso dall'entrypoint solo se `MERCURE_URL`+`MERCURE_JWT_SECRET` sono valorizzati — in locale restano vuoti e non c'è hub) un **segnale senza contenuti** (id conversazione, timestamp, totale non letti) sul topic pubblico `passpartour/inbox` a ogni messaggio in entrata (`MetaInboxService`) e in uscita (`rispondi`); mai pubblicare il testo dei messaggi su quel topic (sottoscrizione anonima). Il browser (script globale in `app.html.twig`, `window.InboxLive`) si sottoscrive via EventSource se `inbox_mercure_url()` non è vuoto e in parallelo fa **polling di riserva** su `/inbox/stato` (5s nelle pagine inbox, 30s altrove): aggiorna il badge non letti in sidebar (`inbox_non_letti()`, Twig `InboxExtension`) e il titolo della scheda, e propaga l'evento DOM `inbox:segnale` che la lista (reload) e la chat aperta (`/inbox/{id}/messaggi?dopo=<id>`, append bolle senza perdere la bozza) ascoltano. `MERCURE_JWT_SECRET` vuoto/placeholder = tutto spento, si va di solo polling.

**Google Calendar**: serve un progetto Google Cloud con Calendar API + ID client OAuth "App web"; redirect `<host>/impostazioni/google/callback`; credenziali in `.env` (`GOOGLE_OAUTH_CLIENT_ID/SECRET`). Sync manuale dalla pagina Impostazioni o `php bin/console app:google:sincronizza` (cron). Calendario/anno: usare `setDate()`, non `modify('first day of <meseIT>')`.

**Unsplash** (ricerca immagini per la libreria Destinazioni): `App\Service\UnsplashClient` (server-side, cache oraria, trackDownload obbligatorio, attribuzione UTM) + `App\Controller\Api\UnsplashController` (`/api/unsplash/search|photo/{id}|select`). Chiave in `.env.local` → `UNSPLASH_ACCESS_KEY=...` (in `.env` solo il segnaposto vuoto). Wiring via `#[Autowire]` sui parametri `$accessKey`/`$cache` (NON in un file di config sotto packages: verrebbe sovrascritto dal glob `App\` di services.yaml). Il form Destinazioni mostra il box di ricerca solo se `isConfigured()`.

**Config chiavi API**: tutte le chiavi/segreti delle integrazioni (Unsplash, Twilio, MAILER/SMTP, OAuth Google) stanno in `.env`/`.env.local` — decisione dell'utente: NIENTE pannello di configurazione in UI. Non riproporlo.

Nota preventivatore: i plurali IT (`scenari`, `voci`) non sono inflettibili da Symfony; le CollectionType usano `by_reference: true` + setter tolleranti `setScenari(iterable)`/`setVoci(iterable)` sulle entità. Non rimuoverli.

Nota login/CSRF (convivenza con altre app Symfony su 127.0.0.1): i cookie ignorano la porta, quindi app diverse su 127.0.0.1 condividono i cookie. Per evitare "Invalid CSRF token" al login: `framework.session.name = PASSPARTOUR_SESSID` (cookie sessione univoco) e CSRF **basato su sessione** (`csrf.yaml` con solo `csrf_protection.enabled: true`, NON stateless). Non riabilitare il CSRF stateless (richiede il controller JS Stimulus, qui non caricato).
