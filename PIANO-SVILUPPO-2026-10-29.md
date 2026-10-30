# Piano di sviluppo — riunione Passpartour del 29/10/2026

> Fonte: 3 trascrizioni Plaud del 29/10 (riunione in agenzia con Maria sul collaudo).
> **Stato: CONFERMATO dall'utente il 29/10/2026** (con struttura menu definitiva).
> Ordine di lavoro: prima le sezioni, la Dashboard per ultima.

## 1. Struttura menu definitiva (sidebar)

```
Dashboard
Agenda
Inbox
Rubrica (clienti e fornitori)
──────────────────────────── (separatore)
Sales (collassabile)
├── Lead          ← qui arrivano i lead delle campagne
├── Preventivi    ← archivio dei preventivi generati nella scheda lead;
│                   un preventivo si può creare da qui ma VA SEMPRE associato a un lead
└── Vendite       ← i preventivi accettati diventano vendite; i lead con preventivo
                    accettato diventano clienti. Pipeline dalla creazione della vendita
                    alla conclusione della pratica. (SVILUPPO IN SECONDA BATTUTA)
Portale (collassabile)      ← da qui si gestirà il sito web
├── Temi
├── Tipologie
├── Destinazioni
└── Offerte
Creator Studio (collassabile)   ← editing per social e campagne marketing
├── Campagne     ← generatore di landing page che pesca dalle Offerte
└── Editor
    ├── Video        ← editor completo per montaggio video, segni distintivi dal Media Kit
    ├── Content      ← impaginatore post/caroselli Instagram e Facebook;
    │                  pesca da Offerte o upload di altro contenuto; output JPG RGB
    ├── Print        ← formati A3/A4 orizzontale/verticale; pesca da Offerte; output PDF CMYK
    ├── Archivio
    │   ├── Digital  ← badge di stato: inedito / programmato (se ha data di pubblicazione) /
    │   │              pubblicato (se il flag "pubblicato" viene spuntato in anteprima)
    │   └── Print    ← archivio PDF, nome file: [offerta]_[formato]_[orientamento]_[data generazione]
    └── Media Kit    ← loghi (più versioni: quadricromia, light, dark),
                       format video (segni distintivi da piazzare nei video),
                       format post (segni distintivi per post singolo),
                       format carosello (segni distintivi con prima immagine,
                       immagine interna e ultima immagine),
                       font (zip di tutte le suite dei font da utilizzare)
Impostazioni
```

## 2. Rubrica

- **Clienti e Fornitori** in un'unica sezione Rubrica.
- **Fornitori**: nuova entità/anagrafica.
- **Clienti**: il lead resta nella pipeline Sales finché non compra; quando un suo preventivo
  viene accettato (→ vendita) diventa **cliente in automatico**, conservando la storicità da lead (timeline).

## 3. Vendite (sviluppo in seconda battuta)

Trasformazione **preventivo accettato → vendita**, con pipeline dalla creazione della vendita
alla conclusione della pratica. Alla conversione:

- **Piano pagamenti**: nel preventivo si impostano modalità, date e scadenze di pagamento;
  le scadenze diventano **promemoria in Agenda**.
- **Attività operative interne**: maschera (solo lato agenzia, mai lato cliente) per schedulare
  le attività necessarie al viaggio — es. "bloccare il treno il 1/11 perché prenotabile solo
  1 mese prima della partenza". Anche queste finiscono in Agenda.
- **Promemoria fattura** a fine pratica.
- **Contratti**: generare il contratto dal template (Maria ha il template contratto Giramondo;
  verificare se vale per tutti i viaggi).
- In prospettiva: **modulo Contabilità** o connettore **Fatture in Cloud** (solo valutazione per ora).

## 4. Agenda come hub dei promemoria

Tutto genera attività/promemoria in Agenda:
- attività sui lead (già c'è),
- scadenze di pagamento delle vendite,
- attività operative post-vendita,
- scadenze di **pubblicazione contenuti** (dal Creator Studio).

Google Calendar: collegamento per operatore già esistente; account Google di Maria creato
in riunione (lato app nulla da fare, solo collegarlo da Impostazioni).

## 5. Portale

Sezione che raccoglie i contenuti del futuro sito passpartourviaggi.com: Temi, Tipologie,
Destinazioni, Offerte (ordine logico del menu). È l'attuale catalogo + offerte, rinominato.

## 6. Creator Studio

- **Campagne**: generatore di landing page che pesca dalle Offerte; le sponsorizzate Meta
  puntano alla landing con form "richiedi contatto" → i lead generati finiscono in Lead
  (obiettivo lead generation). Collegamento a Meta per le sponsorizzate (gestite dal social
  media manager, non da Maria). Valutare in seguito pubblicazione diretta su IG/FB/TikTok.
- **Editor Video**: editor completo per montaggio video; segni distintivi presi dal Media Kit;
  valutare connessione Canva.
- **Editor Content**: impaginatore post singoli/caroselli per Instagram e Facebook
  (generalizzazione dell'impaginatore attuale dell'offerta); sorgente: Offerte o upload libero;
  **output JPG RGB**.
- **Editor Print**: cartelli vetrina/locandine **A3/A4, orizzontale/verticale**; pesca da
  Offerte; **output PDF CMYK**.
- **Archivio Digital**: badge di stato **inedito → programmato** (se ha data di pubblicazione)
  **→ pubblicato** (flag spuntato manualmente in anteprima). Le date di pubblicazione generano
  promemoria in Agenda.
- **Archivio Print**: archivio dei PDF generati, naming `[offerta]_[formato]_[orientamento]_[data generazione]`.
- **Media Kit**: loghi in più versioni (quadricromia, light, dark), format video, format post,
  format carosello (prima immagine / immagine interna / ultima immagine), font (zip delle suite).
  Gli editor pescano da qui → un rebranding si fa cambiando solo il Media Kit.

## 7. Dashboard direzionale (ULTIMA, a sezioni finite)

Overview di tutto: lead (numero, potenziale, **opportunità** = preventivi in attesa di esito),
vendite, fatturato.

## 8. Fix e attività minori emerse

- **Collaudo: ricollegare il connettore immagini** (Unsplash/libreria) — in staging le immagini
  di temi/destinazioni non si vedono (in locale ci sono) → chiave `UNSPLASH_ACCESS_KEY` mancante in staging.
- Pagina Campagne attuale (placeholder) assorbita dal Creator Studio.
- ManyChat: valutazione esterna (con Francesca) per automazioni commenti/DM — non è sviluppo CRM.

## Ordine di lavoro

| # | Blocco | Dipendenze |
|---|--------|------------|
| 1 ✓ | Riorganizzazione sidebar: nuovo menu a sezioni collassabili (Sales, Portale, Creator Studio) + separatore | — |
| 2 ✓ | Rubrica: Fornitori (nuova anagrafica) + spostamento Clienti (lead→cliente automatico arriverà con le Vendite, blocco 6) | 1 |
| 3 ✓ | Creator Studio: struttura, Archivio (Digital+Print) e Media Kit | 1 |
| 4 ✓ | Editor Content (JPG RGB) e Print (PDF, RGB con nota CMYK in prestampa) generalizzati — Editor Video rinviato (valutare Canva) | 3 |
| 5 ✓ | Campagne: generatore landing da offerte + lead gen (l'URL landing si usa nelle sponsorizzate Meta) | 3 |
| 6 ✓ | Vendite: pipeline pratica, piano pagamenti, attività operative, promemoria fattura, lead→cliente automatico (contratti: in attesa del template Giramondo) | 2 |
| 7 ✓ | Agenda hub: promemoria da vendite (blocco 6) + scadenze pubblicazione contenuti nelle viste calendario e nella lista agenda | 4, 6 |
| 8 ✓ | Dashboard nuova: lead, opportunità (preventivi in attesa + potenziale), vendite, fatturato/incassato, ultime pratiche | tutto |
| — | Fix connettore immagini in collaudo (chiave `UNSPLASH_ACCESS_KEY` nello staging) | al prossimo deploy |

> **Piano completato il 29/10/2026 (mattina).**

---

# Revisione del 29/10/2026 (seconda riunione, trascrizione «Revisione CRM e Sviluppo Funzionalità»)

## Applicato ✓

- **Dashboard**: 4ª finestra **Margine** (markup agenzia + commissione TO) nella riga vendite/fatturato/incassato.
- **Vendita**: sezione **Economics** (costo fornitore/TO + margine, precompilati dal preventivo alla trasformazione; margine derivato da importo−costo se lasciato vuoto).
- **Pipeline lead — automatismi** (`PipelineLead`): attività di contatto → *contattato*; preventivo creato → *preventivo inviato*; preventivo inviato via email → *trattativa*; mai regredire, lead chiusi intoccati.
- **Fonti lead**: «Inserimento manuale» → «In agenzia / di persona»; nuove fonti TikTok, Instagram, Vetrina, Passaparola (canali non collegati al CRM).
- **Tour operator**: sezione in Portale (prima di Offerte), nome+logo; select nell'offerta; logo in scheda web/landing e nel PDF di stampa («In collaborazione con»).
- **Scheda offerta**: ordine tema → tipologia → destinazione; un tema custom crea la scheda in Temi; destinazione macro/micro con suggerimenti dalle Destinazioni e collegamento automatico (via campo doppio «collega destinazione»); tab «Post social» rimosso (si lavora dal Creator Studio, Anteprima web resta); supplemento singola dopo le quote; bagaglio con check «Altro» + testo libero; itinerario con giorno singolo o intervallo (es. «Giorni 2–3») e stay se senza orari.
- **Creator Studio**: l'impaginatore si apre sotto `/creator-studio/editor/content/{id}` (sidebar CS attiva); archivi Digital e Print senza upload manuale (i contenuti li generano gli editor); bottone «Anteprima landing» nella scheda campagna.
- **Alloggi multipli** nella scheda offerta: editor ripetibile (nome, città, indirizzo, tipologia, stelle, trattamento), scheda web ed email mostrano tutte le strutture; il formato storico a struttura singola resta leggibile.
- **Media Kit multi-set**: slot organizzati in set (Set 1 = predefinito usato dagli editor; «Aggiungi set N» per i successivi); la scelta del set negli editor resta rimandata («lo vedremo con calma»).
- **Media Kit rifinito** (richieste del 29/10 sera): layout degli slot corretto (niente campi accavallati); **font indicati per nome** (Google Fonts, titoli+testo) e applicati automaticamente dall'impaginatore (titolo/prezzi col font titoli); **sezione Icone** con upload per ogni dettaglio del viaggio — compaiono nel picker delle righe (configuratore offerta e impaginatore) come `mk:<id>` e vengono tinte nel colore del tema come le emoji.

## Completato in seconda battuta (29/10 sera)

- **Canale Email in Inbox**: sync IMAP (`app:email:sincronizza`, config IMAP_* in .env.local), conversazioni email con oggetto, dedup per Message-ID, aggancio lead per email, risposta dal thread via mailer (Re: oggetto), badge e filtro canale.
- **Nuova email dall'inbox**: bottone «✉️ Nuova email» → destinatario (con suggerimenti da clienti/lead), oggetto, testo; apre/riprende la conversazione.
- **Mailing massivo in Inbox**: bottone «📣 Mailing» → newsletter a clienti e/o lead **solo con consenso marketing**, dedup destinatari, personalizzazione `{nome}`.
- **Editor Video v2 — timeline multi-traccia** (tutto nel browser): tracce Video / Testi / Musica; clip con trim (anche dal cursore), ✂ split, riordino drag&drop, duplica, velocità 0.5–2×, preset colore + regolazioni luminosità/contrasto/saturazione, zoom lento Ken Burns, volume per clip; **transizioni** (taglio, dissolvenza incrociata, nero, scorrimento, wipe) con durata regolabile; **testi a tempo** multipli (posizione, dimensione, colore, pillola/banda, col font del Media Kit); logo posizionabile nei 4 angoli; **traccia musicale** con volume e fade in/out mixata all'audio delle clip; scrub sulla timeline, zoom, anteprima; export MP4 (dove supportato, altrimenti WebM) con download e «Salva in Archivio Digital». Collaudato end-to-end con clip generate: export MP4 con transizione, taglio, testo e audio, archiviato e verificato.

## Rimandato (in attesa / da specificare)

| Tema | Stato |
|------|-------|
| **Scelta del set Media Kit dentro gli editor** (multi-set fatto; gli editor usano il set 1) | «lo vedremo con calma» |
| **Campagne massive WhatsApp** (servono i template approvati Meta) | secondo momento |
| **Righe/opzioni di viaggio → editor** (via dalla scheda offerta) | «credo» in riunione — da confermare |
| **Percorso recupero lead persi** (mailing/messaggistica) | «definiremo in un secondo momento» — intanto c'è il mailing manuale |
| **Landing su dominio passpartourviaggi.com** | quando c'è il sito |
| **Rifinitura UI commissioni** | resta da ottimizzare |
| Contratto Giramondo (template da Maria), pubblicazione diretta social, ManyChat, chiave Unsplash in staging | come da piano mattina |

---

# Registrazione del 30/10/2026 («Riorganizzazione Media Kit, Editor Offerte e miglioramenti Print/Content/Video»)

## Applicato ✓ (30/10)

- **Media Kit riorganizzato**: set indipendenti **per categoria** (Loghi / Format / Sting / Font / Icone, ciascuno col suo «Aggiungi set N»); nuovo slot **Pittogramma**; **Base cartello print** orizzontale e verticale (SVG/immagine, usata dall'Editor Print come sfondo adattato ad A4/A3); **Sting apertura/chiusura** (video) selezionabili nell'Editor Video; **icone in doppia variante** light (bianca) e dark (blu) con anteprima su fondo navy/chiaro.
- **Editor Content**: niente più card → pulsante **«Genera contenuto»** con scelta offerta restando nel Creator Studio; rimossi TUTTI gli ingressi all'impaginatore dalla sezione Offerte (catalogo e dettaglio); la sezione «Stampa» è uscita dall'impaginatore (rimando all'Editor Print).
- **Scheda offerta — Generale**: temi e tipologie pescano dalle **tabelle del Portale** (fallback enum; slug storici riconciliati); una voce custom crea la scheda in Temi/**Tipologie**; descrizione usata per caption e print, sottotitolo per i post.
- **Caratteristiche**: voli con tipo **«Andata e ritorno»** (ritorno auto a tratta invertita su scheda/email; doppia data e doppio scalo); **rubrica Compagnie** (voli/treni/navi/trasferimenti) censita in automatico al salvataggio + autocomplete in digitazione + gestione in Impostazioni; tipologia/trattamento alloggio e tipo extra a **datalist aperta** (i valori nuovi entrano nei suggerimenti futuri); **«comprende» generato** dalle caratteristiche se lasciato vuoto (`GeneratoreComprende`, sempre modificabile); «non comprende» con **template riutilizzabili con titolo** (`TemplateTesto`, salva/richiama dall'editor).
- **Economics per quota**: ogni quota ha prezzo di vendita + modalità costo (netta → importo / commissionabile → %) → **MOL per quota** (`CalcolatoreMargine::perQuote`, aliquote IVA/ritenuta/lordo IVA restano globali); rimossi i doppioni «quota di vendita» e campo «prezzo a partire da» (ora derivato dalla quota più bassa, `prezzoDa()`).
- **Info utili**: tab con clima/dress code/usi locali (colonna JSON `infoUtili`), bottone **«Genera automaticamente»** (`InfoUtiliGenerator`: Claude se configurata, fallback deterministico), mostrate nella scheda cliente; fix del salvataggio bloccato: un campo obbligatorio in una scheda nascosta ora apre la scheda giusta (handler `invalid`).
- **Icone preset righe**: se nel Media Kit c'è un'icona con nome affine all'argomento del preset, il preset usa quella (mk:id) al posto dell'emoji.
- **Impaginatore**: varianti **partenza senza prezzo in grafica** (il dato resta); **«Validità dal/al» esponibile** in grafica (checkbox, usa le date dell'offerta se non ci sono varianti validità).
- **Editor Print**: la base cartello del Media Kit diventa sfondo a tutta pagina del PDF dompdf.
- **Editor Video v3** (CapCut-like): **3 tracce video** + testi + **tracce musicali multiple**; blocchi a **posizionamento libero** con **maniglie** di trim e **magnete** (snap a bordi/cursore); **estrazione audio da clip** (bottone → traccia musicale, clip muta); PiP per le tracce sopra; transizioni per clip (taglio/dissolvenza/nero/scorri/wipe/zoom-in); **testi potenziati**: trascinabili direttamente sul video, MAIUSCOLO, enfasi, palette Passpartour per colore testo e sfondo, effetti entrata/uscita (dissolvenza, scorri, zoom, macchina da scrivere); **guide zone sicure IG/TikTok** in anteprima (mai esportate) + **linea occhi regolabile**; musiche con trim/posizione/fade/volume indipendenti; **export senza musica** (resta il parlato delle clip); **sting apertura/chiusura** dal Media Kit con un click; l'export sopravvive al cambio scheda (timer quando il tab è nascosto). Collaudato end-to-end (clip sintetiche: multi-clip, estrazione audio, testo a effetti, export MP4).
- **Editor Video v4** (potenziamento richiesto il 30/10 pomeriggio, riferimenti presi da CapCut web/Premiere/Canva): **annulla/ripeti** (fino a 60 passi) con pulsanti e ⌘Z/⇧⌘Z; **scorciatoie da tastiera** (spazio play/pausa, S dividi, ⌫ elimina, ⇧⌫ elimina con chiusura del buco «ripple», ⌘D duplica, ←/→ frame per frame, M magnete, G guide); **play/pausa vera** + ⏮; **più transizioni** (flash bianco, dissolvenza sfocata, scorri dal basso/alto, cerchio, dividi) e **più filtri colore** (Hollywood, Fuji, Autunno, Crema, Notte…); **effetti per clip** (vignetta, grana pellicola, sfocatura, glitch, scossa, impulso, aberrazione cromatica); **Trasforma** per clip (riempi/adatta/**adatta+sfondo sfocato**, scala, posizione X/Y, rotazione, specchia, opacità); **testi**: 5 stili pronti alla Canva (Titolo/Sottotitolo/Caption/CTA/Impatto), più righe, contorno, ombra, scelta font titoli/testo del kit; **traccia Grafiche** (immagini caricate + icone del Media Kit, trascinabili e ridimensionabili sul video); **musica** con forma d'onda sul blocco e **ducking automatico** (si abbassa quando le clip parlano); formato 4:5. Dividi e Duplica ora clonano la clip su un **nuovo elemento video** (lo stesso elemento condiviso rompeva seek e MediaElementSource). Collaudato end-to-end nel browser (split/undo/redo, estrazione audio col ducking, stile Impatto multiriga, grafica, export MP4 con transizione a cerchio).

## Rimandato (30/10)

| Tema | Stato |
|------|-------|
| Colori badge «abbinati al template» (early/last minute/cancellazione già colorati come da scheda) | da rivedere se serve altro |
| Scelta set Media Kit negli editor | come da 29/10 |
