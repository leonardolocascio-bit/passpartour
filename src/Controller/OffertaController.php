<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Entity\Offerta;
use App\Enum\StelleAlloggio;
use App\Enum\TemaViaggio;
use App\Enum\TipoAssicurazione;
use App\Enum\TipoExtra;
use App\Enum\TipologiaAlloggio;
use App\Enum\TipologiaViaggio;
use App\Enum\TrattamentoHotel;
use App\Form\OffertaType;
use App\Service\CalcolatoreMargine;
use App\Service\UnsplashClient;
use App\Service\UploaderImmagini;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class OffertaController extends AbstractController
{
    public function __construct(
        private readonly CalcolatoreMargine $margine,
        private readonly \App\Service\GeneratoreComprende $comprende,
    ) {
    }

    #[Route('/offerte', name: 'app_offerta_index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('offerta/index.html.twig', [
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/offerte/nuova', name: 'app_offerta_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salva(new Offerta(), $request, $em, $unsplash, $uploader, true);
    }

    #[Route('/offerte/{id}', name: 'app_offerta_dettaglio', requirements: ['id' => '\d+'])]
    public function dettaglio(Offerta $offerta): Response
    {
        return $this->render('offerta/dettaglio.html.twig', ['offerta' => $offerta]);
    }

    /** Anteprima pubblica dell'offerta: come apparirà sul sito (senza chrome del CRM). */
    #[Route('/offerte/{id}/anteprima', name: 'app_offerta_anteprima', requirements: ['id' => '\d+'])]
    public function anteprima(Offerta $offerta): Response
    {
        return $this->render('offerta/anteprima.html.twig', ['offerta' => $offerta]);
    }

    #[Route('/offerte/{id}/modifica', name: 'app_offerta_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Offerta $offerta, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader): Response
    {
        return $this->salva($offerta, $request, $em, $unsplash, $uploader, false);
    }

    private function salva(Offerta $offerta, Request $request, EntityManagerInterface $em, UnsplashClient $unsplash, UploaderImmagini $uploader, bool $isNuovo): Response
    {
        $form = $this->createForm(OffertaType::class, $offerta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $offerta->setRighe($this->decodificaRighe((string) $request->request->get('righe_json')));
            $offerta->setVarianti($this->decodificaVarianti((string) $request->request->get('varianti_json'), $offerta->getRighe()));

            /** @var UploadedFile|null $file */
            $file = $form->get('immagineFile')->getData();
            $unsplashUrl = trim((string) $request->request->get('unsplash_url'));

            if ($file !== null) {
                $nomeFile = $uploader->salvaUpload($file, $offerta->getTitolo());
                if ($nomeFile !== null) {
                    $offerta->setImmagine($nomeFile)->setFotografo(null)->setFotografoUrl(null);
                }
            } elseif ($unsplashUrl !== '') {
                $nomeFile = $uploader->salvaDaUrl($unsplashUrl, $offerta->getTitolo());
                if ($nomeFile !== null) {
                    $offerta->setImmagine($nomeFile)
                        ->setFotografo($request->request->get('unsplash_autore') ?: null)
                        ->setFotografoUrl($request->request->get('unsplash_autore_url') ?: null);
                }
            }

            // multi-selezioni gestite dal chip picker (hidden JSON, non mappate nel form)
            $offerta->setTipologie($this->decodeChip($request->request->get('tipologie')));
            $offerta->setTemi($this->decodeChip($request->request->get('temi')));

            // la destinazione collegata si risolve dalle macro/micro (niente campo doppio)
            $offerta->setDestinazione($this->risolviDestinazione($em, $offerta));
            // un tema/tipologia custom diventa una scheda del Portale (e quindi del sito)
            $this->creaTemiMancanti($em, $offerta->getTemi() ?? []);
            $this->creaTipologieMancanti($em, $offerta->getTipologie() ?? []);
            $offerta->setAssicurazioni($this->decodeChip($request->request->get('assicurazioni')));

            // alloggi: editor ripetibile (più strutture), hidden JSON
            $offerta->setAlloggi($this->decodeSegmenti($request->request->get('alloggi'), ['nome', 'citta', 'indirizzo', 'tipologia', 'stelle', 'trattamento']));

            // trasporti: editor ripetibile (hidden JSON), whitelist chiavi per sezione
            $offerta->setVoli($this->decodeSegmenti($request->request->get('voli'), ['tipo', 'da', 'a', 'data', 'dataRitorno', 'stay', 'stayRitorno', 'compagnia', 'descrizione']));
            $offerta->setTreni($this->decodeSegmenti($request->request->get('treni'), ['da', 'a', 'data', 'compagnia', 'descrizione']));
            $offerta->setNavi($this->decodeSegmenti($request->request->get('navi'), ['da', 'a', 'data', 'compagnia', 'descrizione']));
            $offerta->setTrasferimenti($this->decodeSegmenti($request->request->get('trasferimenti'), ['descrizione', 'da', 'a', 'data', 'compagnia']));
            $offerta->setExtra($this->decodeSegmenti($request->request->get('extra'), ['tipo', 'descrizione', 'da', 'a', 'data', 'compagnia']));
            $offerta->setBagaglio($this->normalizzaBagaglio($request->request->all('bagaglio')));

            // itinerario (crociere/tour) e quote di vendita (editor ripetibile);
            // ogni quota porta la sua modalità di costo (netta/commissionabile)
            $offerta->setItinerario($this->decodeSegmenti($request->request->get('itinerario'), ['giorno', 'luogo', 'arrivo', 'partenza', 'descrizione']));
            $offerta->setQuote($this->decodeSegmenti($request->request->get('quote'), ['tipo', 'sistemazione', 'importo', 'modo', 'costo']));

            // costi/MOL (dato interno: aliquote globali, il resto è per quota)
            $offerta->setCosti($this->normalizzaCosti($request->request->all('costi')));

            // «prezzo a partire da» = quota più bassa (niente doppio inserimento)
            $minimo = $this->margine->prezzoDa($offerta);
            if ($minimo !== null) {
                $offerta->setPrezzoDa(number_format($minimo, 2, '.', ''));
            }

            // info utili (clima, dress code, usi locali): modificabili a mano
            $offerta->setInfoUtili([
                'clima' => (string) $request->request->get('info_clima', ''),
                'dressCode' => (string) $request->request->get('info_dresscode', ''),
                'usiLocali' => (string) $request->request->get('info_usilocali', ''),
            ]);

            // «comprende» vuoto → generato dalle caratteristiche (sempre modificabile)
            if (trim((string) $offerta->getComprende()) === '') {
                $offerta->setComprende($this->comprende->genera($offerta) ?: null);
            }

            // censimento compagnie in rubrica (suggerite alla digitazione)
            $this->censisciCompagnie($em, $offerta);

            if ($isNuovo) {
                $em->persist($offerta);
            }
            $em->flush();
            $this->addFlash('success', 'Offerta salvata.');

            return $this->redirectToRoute('app_offerta_index');
        }

        $destinazioniNomi = array_map(
            static fn (\App\Entity\Destinazione $d) => $d->getNome(),
            $em->getRepository(\App\Entity\Destinazione::class)->findBy([], ['nome' => 'ASC']),
        );

        // icone del Media Kit (set 1) per il picker delle righe
        $iconeKit = array_map(
            static fn (\App\Entity\MediaKitIcona $i) => ['id' => $i->getId(), 'nome' => $i->getNome(), 'url' => 'uploads/creator/mediakit/' . $i->getFile()],
            $em->getRepository(\App\Entity\MediaKitIcona::class)->findBy(['gruppo' => 1], ['nome' => 'ASC']),
        );

        // temi e tipologie pescano dalle sezioni del Portale (fallback: enum);
        // dove la voce combacia con una label enum si riusa il valore enum come
        // chiave, così le offerte salvate con gli slug storici restano selezionate
        $temiScelte = $this->scelteDaPortale($em, \App\Entity\Tema::class, TemaViaggio::scelte());
        $tipologieScelte = $this->scelteDaPortale($em, \App\Entity\Tipologia::class, TipologiaViaggio::scelte());

        return $this->render('offerta/form.html.twig', [
            'form' => $form,
            'offerta' => $offerta,
            'destinazioni_nomi' => $destinazioniNomi,
            'icone_kit' => $iconeKit,
            'unsplash_configurato' => $unsplash->isConfigured(),
            'margini_quote' => $this->margine->perQuote($offerta),
            'compagnie' => $this->rubricaCompagnie($em),
            'template_testi' => $em->getRepository(\App\Entity\TemplateTesto::class)->findBy(['ambito' => 'non_comprende'], ['titolo' => 'ASC']),
            'tipologie_scelte' => $tipologieScelte,
            'temi_scelte' => $temiScelte,
            'assicurazioni_scelte' => TipoAssicurazione::scelte(),
            'extra_scelte' => $this->conValoriUsati($em, TipoExtra::labels(), 'extra', 'tipo'),
            // datalist: label predefinite + valori custom già usati (rubrica implicita)
            'tipologia_alloggio_scelte' => $this->unisci(array_values(TipologiaAlloggio::scelte()), $this->valoriAlloggioUsati($em, 'tipologia')),
            'trattamento_scelte' => $this->unisci(
                array_map(static fn (TrattamentoHotel $t) => $t->label(), TrattamentoHotel::cases()),
                $this->valoriAlloggioUsati($em, 'trattamento'),
            ),
            'stelle_scelte' => StelleAlloggio::scelte(),
            'titolo' => $isNuovo ? 'Nuova offerta' : 'Modifica offerta',
        ]);
    }

    /** Collega la Destinazione di libreria che combacia con micro (poi macro). */
    private function risolviDestinazione(EntityManagerInterface $em, Offerta $offerta): ?\App\Entity\Destinazione
    {
        foreach ([$offerta->getDestinazioneMicro(), $offerta->getDestinazioneMacro()] as $nome) {
            if ($nome === null || trim($nome) === '') {
                continue;
            }
            $trovata = $em->getRepository(\App\Entity\Destinazione::class)->createQueryBuilder('d')
                ->where('LOWER(d.nome) = :n')->setParameter('n', mb_strtolower(trim($nome)))
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($trovata !== null) {
                return $trovata;
            }
        }

        return null;
    }

    /** Ogni tema scelto nell'offerta che non esiste come scheda Tema viene creato. */
    private function creaTemiMancanti(EntityManagerInterface $em, array $temi): void
    {
        $repo = $em->getRepository(\App\Entity\Tema::class);
        foreach ($temi as $nome) {
            // i valori predefiniti sono slug enum: risolviamo in label per la scheda
            $nome = trim(TemaViaggio::etichetta((string) $nome));
            if ($nome === '') {
                continue;
            }
            $esiste = $repo->createQueryBuilder('t')
                ->where('LOWER(t.nome) = :n')->setParameter('n', mb_strtolower($nome))
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($esiste === null) {
                $em->persist((new \App\Entity\Tema())->setNome($nome));
            }
        }
    }

    /** Ogni tipologia scelta nell'offerta che non ha una scheda Tipologia la crea. */
    private function creaTipologieMancanti(EntityManagerInterface $em, array $tipologie): void
    {
        $repo = $em->getRepository(\App\Entity\Tipologia::class);
        foreach ($tipologie as $nome) {
            // i valori predefiniti sono slug enum: risolviamo in label per la scheda
            $nome = trim(TipologiaViaggio::etichetta((string) $nome));
            if ($nome === '') {
                continue;
            }
            $esiste = $repo->createQueryBuilder('t')
                ->where('LOWER(t.nome) = :n')->setParameter('n', mb_strtolower($nome))
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();
            if ($esiste === null) {
                $em->persist((new \App\Entity\Tipologia())->setNome($nome));
            }
        }
    }

    /**
     * Scelte del chip picker da una sezione del Portale: mappa valore => etichetta.
     *
     * @param array<string, string> $scelteEnum fallback e riconciliazione slug storici
     *
     * @return array<string, string>
     */
    private function scelteDaPortale(EntityManagerInterface $em, string $classe, array $scelteEnum): array
    {
        $out = [];
        foreach ($em->getRepository($classe)->findBy([], ['nome' => 'ASC']) as $voce) {
            $nome = $voce->getNome();
            $chiave = array_search($nome, $scelteEnum, true) ?: $nome;
            $out[$chiave] = $nome;
        }

        return $out ?: $scelteEnum;
    }

    /** Rubrica compagnie per sezione: ['volo' => [...], 'treno' => [...], …]. */
    private function rubricaCompagnie(EntityManagerInterface $em): array
    {
        $out = array_fill_keys(array_keys(\App\Entity\Compagnia::TIPI), []);
        foreach ($em->getRepository(\App\Entity\Compagnia::class)->findBy([], ['nome' => 'ASC']) as $c) {
            $out[$c->getTipo()][] = $c->getNome();
        }

        return $out;
    }

    /** Censisce in rubrica le compagnie usate nelle caratteristiche salvate. */
    private function censisciCompagnie(EntityManagerInterface $em, Offerta $offerta): void
    {
        $sezioni = [
            'volo' => $offerta->getVoli() ?? [],
            'treno' => $offerta->getTreni() ?? [],
            'nave' => $offerta->getNavi() ?? [],
            'trasferimento' => $offerta->getTrasferimenti() ?? [],
        ];
        $repo = $em->getRepository(\App\Entity\Compagnia::class);
        $visti = []; // dedup anche dentro la stessa richiesta (persist non ancora flushati)
        foreach ($sezioni as $tipo => $righe) {
            foreach ($righe as $r) {
                $nome = mb_substr(trim((string) ($r['compagnia'] ?? '')), 0, 120);
                $chiave = $tipo . '|' . mb_strtolower($nome);
                if ($nome === '' || isset($visti[$chiave])) {
                    continue;
                }
                $visti[$chiave] = true;
                $esiste = $repo->createQueryBuilder('c')
                    ->where('c.tipo = :t AND LOWER(c.nome) = :n')
                    ->setParameter('t', $tipo)->setParameter('n', mb_strtolower($nome))
                    ->setMaxResults(1)->getQuery()->getOneOrNullResult();
                if ($esiste === null) {
                    $em->persist(new \App\Entity\Compagnia($tipo, $nome));
                }
            }
        }
    }

    /**
     * Scelte predefinite (label enum) + valori custom già usati nelle offerte:
     * ogni voce digitata a mano entra nei suggerimenti futuri (rubrica implicita).
     *
     * @param array<string, string> $labels valore enum => label
     *
     * @return list<string>
     */
    private function conValoriUsati(EntityManagerInterface $em, array $labels, string $colonna, string $chiave): array
    {
        $valori = array_values($labels);
        $getter = 'get' . ucfirst($colonna);
        foreach ($em->getRepository(Offerta::class)->findAll() as $o) {
            foreach ((array) $o->{$getter}() as $riga) {
                $v = trim((string) ($riga[$chiave] ?? ''));
                if ($v !== '' && !isset($labels[$v]) && !in_array($v, $valori, true)) {
                    $valori[] = $v;
                }
            }
        }
        sort($valori);

        return $valori;
    }

    /** Predefiniti (in ordine) + custom non già presenti, per le datalist. @return list<string> */
    private function unisci(array $predefiniti, array $custom): array
    {
        foreach ($custom as $v) {
            if (!in_array($v, $predefiniti, true)) {
                $predefiniti[] = $v;
            }
        }

        return $predefiniti;
    }

    /** Valori già usati negli alloggi per un campo (tipologia/trattamento), risolti in label. @return list<string> */
    private function valoriAlloggioUsati(EntityManagerInterface $em, string $campo): array
    {
        $valori = [];
        foreach ($em->getRepository(Offerta::class)->findAll() as $o) {
            foreach ($o->getAlloggi() as $a) {
                $v = trim((string) ($a[$campo] ?? ''));
                if ($v === '') {
                    continue;
                }
                $label = $campo === 'tipologia' ? $o->tipologiaAlloggioLabel($v) : $o->trattamentoAlloggioLabel($v);
                if ($label !== null && $label !== '' && !in_array($label, $valori, true)) {
                    $valori[] = $label;
                }
            }
        }
        sort($valori);

        return $valori;
    }

    /** Salva un template «non comprende» con titolo (dall'editor offerta, via fetch). */
    #[Route('/offerte/template-testo', name: 'app_offerta_template_testo', methods: ['POST'])]
    public function salvaTemplateTesto(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('template_testo', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Token non valido.'], 403);
        }
        $dati = json_decode($request->getContent() ?: '{}', true) ?: [];
        $titolo = mb_substr(trim((string) ($dati['titolo'] ?? '')), 0, 120);
        $testo = trim((string) ($dati['testo'] ?? ''));
        if ($titolo === '' || $testo === '') {
            return $this->json(['error' => 'Servono titolo e testo.'], 400);
        }

        $template = (new \App\Entity\TemplateTesto('non_comprende'))->setTitolo($titolo)->setTesto($testo);
        $em->persist($template);
        $em->flush();

        return $this->json(['ok' => true, 'id' => $template->getId(), 'titolo' => $titolo]);
    }

    /** Genera le «info utili» (clima, dress code, usi locali) per la destinazione indicata. */
    #[Route('/offerte/info-utili/genera', name: 'app_offerta_info_genera', methods: ['POST'])]
    public function generaInfoUtili(Request $request, \App\Service\InfoUtiliGenerator $generator): Response
    {
        $dati = json_decode($request->getContent() ?: '{}', true) ?: [];
        $temp = new Offerta();
        $temp->setTitolo(mb_substr(trim((string) ($dati['titolo'] ?? '')), 0, 200));
        $temp->setDestinazioneMacro(trim((string) ($dati['macro'] ?? '')) ?: null);
        $temp->setDestinazioneMicro(trim((string) ($dati['micro'] ?? '')) ?: null);
        $temp->setDurata(trim((string) ($dati['durata'] ?? '')) ?: null);

        return $this->json(['ok' => true, 'ai' => $generator->isConfigured()] + $generator->genera($temp));
    }

    /**
     * Decodifica una sezione ripetibile (voli/treni/navi/trasferimenti): filtra le
     * chiavi ammesse, trima, scarta le voci completamente vuote.
     *
     * @param list<string> $chiavi
     *
     * @return list<array<string, string>>
     */
    private function decodeSegmenti(?string $json, array $chiavi): array
    {
        $dati = json_decode((string) $json ?: '[]', true);
        if (!\is_array($dati)) {
            return [];
        }

        $out = [];
        foreach ($dati as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $riga = [];
            foreach ($chiavi as $k) {
                $v = trim((string) ($item[$k] ?? ''));
                if ($v !== '') {
                    $riga[$k] = mb_substr($v, 0, 500);
                }
            }
            if ($riga !== []) {
                $out[] = $riga;
            }
        }

        return $out;
    }

    /**
     * Normalizza i campi costi/MOL dal form (whitelist + flag lordo IVA).
     *
     * @param array<string, mixed> $dati
     *
     * @return array<string, mixed>
     */
    private function normalizzaCosti(array $dati): array
    {
        $out = [];
        foreach (['quotaVendita', 'quotaNetta', 'commissioneValore', 'ivaPercentuale', 'ritenutaPercentuale'] as $k) {
            $v = trim((string) ($dati[$k] ?? ''));
            if ($v !== '') {
                $out[$k] = $v;
            }
        }
        $tipo = (string) ($dati['tipo'] ?? '');
        if (\in_array($tipo, ['netta', 'commissionabile'], true)) {
            $out['tipo'] = $tipo;
        }
        $modo = (string) ($dati['commissioneModo'] ?? '');
        if (\in_array($modo, ['percentuale', 'fisso'], true)) {
            $out['commissioneModo'] = $modo;
        }
        if (!empty($dati['nettaLordoIva'])) {
            $out['nettaLordoIva'] = true;
        }

        return $out;
    }

    /**
     * Normalizza il bagaglio: flag booleani + numero/kg solo se la voce è attiva.
     *
     * @param array<string, mixed> $dati
     *
     * @return array<string, mixed>
     */
    private function normalizzaBagaglio(array $dati): array
    {
        $out = [];
        if (!empty($dati['borsaPiccola'])) {
            $out['borsaPiccola'] = true;
        }
        foreach (['mano', 'stiva'] as $tipo) {
            if (!empty($dati[$tipo])) {
                $out[$tipo] = true;
                $num = trim((string) ($dati[$tipo . 'Num'] ?? ''));
                $kg = trim((string) ($dati[$tipo . 'Kg'] ?? ''));
                if ($num !== '') {
                    $out[$tipo . 'Num'] = (int) $num;
                }
                if ($kg !== '') {
                    $out[$tipo . 'Kg'] = (float) $kg;
                }
            }
        }
        if (!empty($dati['altro'])) {
            $out['altro'] = true;
            $testo = trim((string) ($dati['altroTesto'] ?? ''));
            if ($testo !== '') {
                $out['altroTesto'] = mb_substr($testo, 0, 200);
            }
        }

        return $out;
    }

    /**
     * Decodifica i valori di un chip picker (hidden JSON: array di stringhe).
     *
     * @return list<string>
     */
    private function decodeChip(?string $json): array
    {
        $dati = json_decode((string) $json ?: '[]', true);
        if (!\is_array($dati)) {
            return [];
        }

        $out = [];
        foreach ($dati as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $out[] = mb_substr($v, 0, 60);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Normalizza le righe/opzioni del viaggio serializzate dal configuratore.
     *
     * @return list<array{id: string, icona: string, argomento: string, testo: string, evidenza: bool}>
     */
    private function decodificaRighe(string $json): array
    {
        $dati = json_decode($json ?: '[]', true);
        if (!\is_array($dati)) {
            return [];
        }

        $righe = [];
        foreach ($dati as $r) {
            if (!\is_array($r)) {
                continue;
            }
            $argomento = trim((string) ($r['argomento'] ?? ''));
            $testo = trim((string) ($r['testo'] ?? ''));
            if ($argomento === '' && $testo === '') {
                continue; // riga vuota
            }
            $righe[] = [
                'id' => preg_replace('/[^a-z0-9]/', '', (string) ($r['id'] ?? '')) ?: 'r' . bin2hex(random_bytes(4)),
                'icona' => mb_substr(trim((string) ($r['icona'] ?? '')), 0, 8),
                'argomento' => mb_substr($argomento, 0, 60),
                'testo' => mb_substr($testo, 0, 160),
                'evidenza' => !empty($r['evidenza']),
            ];
        }

        return $righe;
    }

    /**
     * Normalizza le varianti serializzate dal configuratore. `campo` è
     * 'durata' | 'validita' | 'partenza' oppure l'id di una riga esistente.
     *
     * @param list<array<string, mixed>> $righe
     *
     * @return list<array{id: string, campo: string, valore: string, prezzo: string}>
     */
    private function decodificaVarianti(string $json, array $righe): array
    {
        $dati = json_decode($json ?: '[]', true);
        if (!\is_array($dati)) {
            return [];
        }

        $idRighe = array_column($righe, 'id');
        $varianti = [];
        foreach ($dati as $v) {
            if (!\is_array($v)) {
                continue;
            }
            $campo = (string) ($v['campo'] ?? '');
            $valore = trim((string) ($v['valore'] ?? ''));
            if ($valore === '' || (!\in_array($campo, ['durata', 'validita', 'partenza'], true) && !\in_array($campo, $idRighe, true))) {
                continue;
            }
            $varianti[] = [
                'id' => preg_replace('/[^a-z0-9]/', '', (string) ($v['id'] ?? '')) ?: 'v' . bin2hex(random_bytes(4)),
                'campo' => $campo,
                'valore' => mb_substr($valore, 0, 120),
                'prezzo' => mb_substr(trim((string) ($v['prezzo'] ?? '')), 0, 40),
            ];
        }

        return $varianti;
    }

    #[Route('/offerte/{id}/elimina', name: 'app_offerta_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Offerta $offerta, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('offerta_elimina_' . $offerta->getId(), (string) $request->request->get('_token'))) {
            $em->remove($offerta);
            $em->flush();
            $this->addFlash('success', 'Offerta eliminata.');
        }

        return $this->redirectToRoute('app_offerta_index');
    }

    #[Route('/offerte/{id}/invia', name: 'app_offerta_invia', requirements: ['id' => '\d+'])]
    public function invia(
        Offerta $offerta,
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] string $mittente,
        #[Autowire('%env(MAILER_DSN)%')] string $mailerDsn,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('offerta_invia_' . $offerta->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token non valido.');

                return $this->redirectToRoute('app_offerta_invia', ['id' => $offerta->getId()]);
            }

            $a = trim((string) $request->request->get('destinatario'));
            if ($a === '' || !filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Indirizzo email destinatario non valido.');

                return $this->redirectToRoute('app_offerta_invia', ['id' => $offerta->getId()]);
            }

            $oggetto = trim((string) $request->request->get('oggetto')) ?: $offerta->getTitolo();
            $messaggio = trim((string) $request->request->get('messaggio'));

            $img = null;
            if ($offerta->getImmagineFile()) {
                $file = $this->getParameter('kernel.project_dir') . '/public/uploads/destinazioni/' . $offerta->getImmagineFile();
                if (is_file($file)) {
                    $img = 'data:image/jpeg;base64,' . base64_encode((string) file_get_contents($file));
                }
            }

            $email = (new Email())
                ->from(Address::create($mittente))
                ->to($a)
                ->subject($oggetto)
                ->html($this->renderView('emails/offerta.html.twig', [
                    'offerta' => $offerta,
                    'messaggio' => $messaggio,
                    'immagine' => $img,
                ]));

            try {
                $mailer->send($email);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Invio non riuscito: ' . $e->getMessage());

                return $this->redirectToRoute('app_offerta_invia', ['id' => $offerta->getId()]);
            }

            $this->addFlash('success', 'Offerta inviata a ' . $a . '.');

            return $this->redirectToRoute('app_offerta_index');
        }

        // prefill destinatario da lead
        $destinatario = '';
        if ($leadId = $request->query->get('lead')) {
            $destinatario = (string) ($em->getRepository(Lead::class)->find($leadId)?->getEmail() ?? '');
        }

        // rubrica per l'autocompletamento
        $rubrica = $em->getRepository(Lead::class)->createQueryBuilder('l')
            ->select('l.email')->where('l.email IS NOT NULL')->getQuery()->getSingleColumnResult();

        return $this->render('offerta/invia.html.twig', [
            'offerta' => $offerta,
            'destinatario' => $destinatario,
            'oggetto' => $offerta->getTitolo(),
            'rubrica' => array_values(array_unique($rubrica)),
            'mailer_reale' => !str_starts_with($mailerDsn, 'null'),
        ]);
    }
}
