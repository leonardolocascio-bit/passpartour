# App Review Meta — materiale per la pratica

Obiettivo: ottenere l'**Advanced Access** sui permessi di messaging dell'app
**PASSPARTOUR CRM DEF** (ID 1408622660597944, business Iconapubli, già
verificato) e portare l'app in modalità **Live**, così Messenger e Instagram
Direct dei clienti veri arrivano nell'inbox del CRM. WhatsApp funziona già
senza review (numero proprio, WABA del business).

Stato: canali collaudati su stagingest il 12/11/2026 (WhatsApp +39 351 489 8586,
Messenger, Instagram Direct, nei due sensi).

## Permessi da richiedere

| Permesso | A cosa serve nel CRM |
|---|---|
| `pages_messaging` | ricevere e rispondere ai Messenger della pagina Passpartour Viaggi |
| `instagram_manage_messages` | ricevere e rispondere ai Direct dell'account IG collegato |
| `Business Asset User Profile Access` (funzione) | mostrare il nome del contatto al posto del PSID |

`pages_manage_metadata` è già usato (sottoscrizione webhook) e va incluso se
Meta lo richiede nel flusso. I permessi WhatsApp **non** richiedono review per
l'uso sul numero proprio.

## Prerequisiti nelle impostazioni di base dell'app (da completare PRIMA di inviare)

Impostazioni app → Di base:

- **URL dell'informativa sulla privacy**: `https://stagingest.passpartourviaggi.com/privacy`
  (pagina pubblica servita dal CRM, fuori da login e basic auth; quando il
  gestionale andrà in produzione sostituire con
  `https://gestionale.passpartourviaggi.com/privacy`).
- **Cancellazione dei dati**: scegliere «Indirizzo email» o URL istruzioni —
  la pagina privacy contiene già la sezione «Cancellazione dei dati dai canali
  Meta» con la procedura.
- **Icona dell'app** (1024×1024): usare il pittogramma `public/images/favicon.png`
  riesportato a 1024px su fondo bianco.
- **Categoria**: Business and pages.

## Accesso per il reviewer

Il reviewer di Meta deve poter provare il flusso. Fornire nella pratica:

- URL: `https://stagingest.passpartourviaggi.com/inbox`
- Basic auth del proxy: utente `collaudo`, password: *(quella del cancello di
  staging — NON scriverla qui: si inserisce solo nel form riservato della
  pratica)*
- Login CRM: creare un utente dedicato `reviewer@passpartour.local` con ruolo
  agente e password monouso, da disattivare a review conclusa (NON usare gli
  account demo documentati).

## Testo per la richiesta (EN, da incollare nella pratica)

**Come viene usato `pages_messaging`:**

> Passpartour CRM is the internal management system of Passpartour Viaggi, an
> Italian travel agency. The app powers the agency's unified inbox: messages
> that customers send to the agency's Facebook Page (Passpartour Viaggi) are
> received via webhook and shown to the agency's staff in the CRM inbox, where
> an operator reads them and replies. Replies are sent through the Send API as
> human agent responses within the standard messaging window. Messages are
> used only to handle the customer's travel enquiry; no automation, no
> broadcast, no data resale.

**Come viene usato `instagram_manage_messages`:**

> Same unified inbox: Instagram Direct messages sent to the professional
> Instagram account connected to the Passpartour Viaggi Page are delivered via
> webhook and handled by the agency's staff in the CRM, which is also where
> operators type their replies. Only human responses to customer enquiries.

**Come viene usata `Business Asset User Profile Access`:**

> Used solely to display the sender's name next to the conversation in the
> inbox (instead of a numeric ID), so operators know who they are talking to.

**Istruzioni di riproduzione per il reviewer:**

> 1. Open https://stagingest.passpartourviaggi.com/inbox (HTTP basic auth and
>    CRM credentials provided below).
> 2. Send a Facebook message to the Page "Passpartour Viaggi" (or an Instagram
>    DM to its connected professional account) from your test user.
> 3. Within seconds the conversation appears in the Inbox list; open it.
> 4. Type a reply in the message box and press "Invia": the reply is delivered
>    to the sender on Messenger/Instagram.

## Screencast (da registrare sull'inbox di staging)

Durata 60–90 secondi, senza audio, risoluzione piena. Sequenza:

1. Browser su `stagingest.passpartourviaggi.com/inbox`: si vede l'inbox con i
   tre filtri canale (WhatsApp / Messenger / Instagram Direct).
2. Telefono inquadrato o finestra affiancata: invio di un Messenger alla
   pagina Passpartour Viaggi («Buongiorno, vorrei informazioni su un viaggio»).
3. L'inbox si aggiorna: la conversazione compare con il badge non letti.
4. Click sulla conversazione: si apre il thread con il messaggio appena
   arrivato.
5. L'operatore scrive la risposta e preme Invia.
6. Sul telefono arriva la risposta su Messenger.
7. Ripetere i punti 2–6 con un Direct Instagram (basta una volta sola se la
   pratica lo consente; in caso di dubbio registrare due clip separate).

## Dopo l'approvazione

1. Impostazioni app → in alto: interruttore da «In fase di sviluppo» a **Live**.
2. Verificare che un messaggio da un profilo SENZA ruoli nell'app arrivi in
   inbox (test con un collega non censito).
3. Il filtro dev-mode sparisce: i clienti veri arrivano su tutti e tre i canali.
4. Quando il CRM va in produzione: aggiornare l'URL privacy nelle impostazioni
   di base e il webhook (`/webhook/meta/<token>` su gestionale.) — oppure
   mantenere staging come endpoint fino allo switch definitivo.
