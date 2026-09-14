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

Tutte le 6 milestone core completate. Extra: PDF nativo preventivo (dompdf), commissioni + icone per categoria, viste calendario (giorno/settimana/mese/anno) e sync bidirezionale Google Calendar. 51 test (`php bin/phpunit`).

**M15 — Scheda Offerta** (in corso): la scheda offerta è il fulcro di vendite/preventivi (offerta accettata → vendita, personalizzata → preventivo). Fasi: **A ✓** Generale (destinazione macro/micro + tipologia/tema multi-selezione), **B** Caratteristiche (alloggio, voli+scali+bagaglio, treni, navi, trasferimenti, extra, assicurazioni, comprende/non comprende), **C** Offerta/economics (quota+supplementi, sistemazioni, costi/MOL con IVA e ritenuta visibili solo all'operatore, badge commerciali), **D** Info utili smart (meteo nel periodo, dress code/usi locali via ricerca/AI — tutti editabili), **E** aggancio vendita/preventivo. **Decisioni architetturali** (utente): sezioni ripetibili in colonne **JSON** (come `righe`/`varianti`/`impaginato`), colonne reali solo per i campi chiave su cui si filtra/fa badge; integrazioni smart in coda al modello dati. Le liste (tipologia, tema, e in seguito extra/assicurazioni…) NON sono enum rigidi: gli enum (`TipologiaViaggio`, `TemaViaggio`) danno solo le **voci predefinite** del picker, i valori scelti si salvano come array di stringhe in JSON e l'operatore può **aggiungere voci custom** (requisito "per ogni elenco a cascata dammi possibilità di aggiungere voci"). Componente riutilizzabile: `_partials/_chip_picker.html.twig` (markup) + `_partials/_chip_picker_js.html.twig` (JS, includere una volta per pagina) → hidden JSON letto in controller da `decodeChip()`; stili `.chip`/`.chip-picker` in base.html.twig. Fase A su `Offerta`: `destinazioneMacro`/`destinazioneMicro` (VARCHAR), `tipologie`/`temi` (JSON) con helper `get…Label()` che risolve enum→label lasciando intatte le custom.

**Impaginatore offerte** (`/offerte/{id}/impaginatore`, `ImpaginatoreController`): genera contenuti social Instagram 3:4 (1080×1440, carosello max 20 slide, anteprima/export via canvas JS) e stampa A4/A3 verticale/orizzontale (`/offerte/{id}/stampa/{a4v|a4o|a3v|a3o}`, layout base — l'editor di stampa avanzato è in attesa di specifiche dall'utente). **Le righe/opzioni del viaggio (icona/argomento/testo/evidenza) e le varianti con prezzo si compilano nel configuratore offerta** (`_partials/_righe_editor.html.twig`, hidden `righe_json`/`varianti_json` → `Offerta.righe`/`Offerta.varianti` JSON, id stabili; variante = {campo: 'durata'|'validita'|'partenza'|id-riga, valore, prezzo testo libero anche «+50»}); l'impaginatore le eredita e vi si sceglie solo cosa mostrare (checkbox 📱 per slide, 💬 per caption — le slide referenziano righe e varianti per id in `Offerta.impaginato`; `migraRigheLegacy()` solleva il vecchio formato a oggetti). Sulla slide: le varianti di durata con prezzo sostituiscono il badge prezzo unico (pillole impilate, es. «2 NOTTI € 120 / 4 NOTTI € 250»), partenza/validità diventano righe virtuali, le varianti di riga si accodano al testo della riga. Campo `claim` sull'offerta. Sfondi scelti da Unsplash e scaricati in locale (niente CORS sul canvas). Caption con tono di voce: `CaptionGenerator` usa l'API Claude se `ANTHROPIC_API_KEY` è in `.env.local`, altrimenti template deterministici — mantenere sempre il fallback.

**Google Calendar**: serve un progetto Google Cloud con Calendar API + ID client OAuth "App web"; redirect `<host>/impostazioni/google/callback`; credenziali in `.env` (`GOOGLE_OAUTH_CLIENT_ID/SECRET`). Sync manuale dalla pagina Impostazioni o `php bin/console app:google:sincronizza` (cron). Calendario/anno: usare `setDate()`, non `modify('first day of <meseIT>')`.

**Unsplash** (ricerca immagini per la libreria Destinazioni): `App\Service\UnsplashClient` (server-side, cache oraria, trackDownload obbligatorio, attribuzione UTM) + `App\Controller\Api\UnsplashController` (`/api/unsplash/search|photo/{id}|select`). Chiave in `.env.local` → `UNSPLASH_ACCESS_KEY=...` (in `.env` solo il segnaposto vuoto). Wiring via `#[Autowire]` sui parametri `$accessKey`/`$cache` (NON in un file di config sotto packages: verrebbe sovrascritto dal glob `App\` di services.yaml). Il form Destinazioni mostra il box di ricerca solo se `isConfigured()`.

**Config chiavi API**: tutte le chiavi/segreti delle integrazioni (Unsplash, Twilio, MAILER/SMTP, OAuth Google) stanno in `.env`/`.env.local` — decisione dell'utente: NIENTE pannello di configurazione in UI. Non riproporlo.

Nota preventivatore: i plurali IT (`scenari`, `voci`) non sono inflettibili da Symfony; le CollectionType usano `by_reference: true` + setter tolleranti `setScenari(iterable)`/`setVoci(iterable)` sulle entità. Non rimuoverli.

Nota login/CSRF (convivenza con altre app Symfony su 127.0.0.1): i cookie ignorano la porta, quindi app diverse su 127.0.0.1 condividono i cookie. Per evitare "Invalid CSRF token" al login: `framework.session.name = PASSPARTOUR_SESSID` (cookie sessione univoco) e CSRF **basato su sessione** (`csrf.yaml` con solo `csrf_protection.enabled: true`, NON stateless). Non riabilitare il CSRF stateless (richiede il controller JS Stimulus, qui non caricato).
