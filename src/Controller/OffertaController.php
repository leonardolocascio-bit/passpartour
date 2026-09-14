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
    public function __construct(private readonly CalcolatoreMargine $margine)
    {
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
            $offerta->setAssicurazioni($this->decodeChip($request->request->get('assicurazioni')));

            // alloggio: campi manuali name="alloggio[...]" (persistiti come JSON)
            $offerta->setAlloggio($this->normalizzaAlloggio($request->request->all('alloggio')));

            // trasporti: editor ripetibile (hidden JSON), whitelist chiavi per sezione
            $offerta->setVoli($this->decodeSegmenti($request->request->get('voli'), ['tipo', 'da', 'a', 'data', 'stay', 'compagnia', 'descrizione']));
            $offerta->setTreni($this->decodeSegmenti($request->request->get('treni'), ['da', 'a', 'data', 'compagnia', 'descrizione']));
            $offerta->setNavi($this->decodeSegmenti($request->request->get('navi'), ['da', 'a', 'data', 'compagnia', 'descrizione']));
            $offerta->setTrasferimenti($this->decodeSegmenti($request->request->get('trasferimenti'), ['descrizione', 'da', 'a', 'data', 'compagnia']));
            $offerta->setExtra($this->decodeSegmenti($request->request->get('extra'), ['tipo', 'descrizione', 'da', 'a', 'data', 'compagnia']));
            $offerta->setBagaglio($this->normalizzaBagaglio($request->request->all('bagaglio')));

            // quote di vendita (editor ripetibile)
            $offerta->setQuote($this->decodeSegmenti($request->request->get('quote'), ['tipo', 'sistemazione', 'importo']));

            // costi/MOL (dato interno)
            $offerta->setCosti($this->normalizzaCosti($request->request->all('costi')));

            if ($isNuovo) {
                $em->persist($offerta);
            }
            $em->flush();
            $this->addFlash('success', 'Offerta salvata.');

            return $this->redirectToRoute('app_offerta_index');
        }

        return $this->render('offerta/form.html.twig', [
            'form' => $form,
            'offerta' => $offerta,
            'unsplash_configurato' => $unsplash->isConfigured(),
            'margine' => $this->margine->calcola($offerta),
            'tipologie_scelte' => TipologiaViaggio::scelte(),
            'temi_scelte' => TemaViaggio::scelte(),
            'assicurazioni_scelte' => TipoAssicurazione::scelte(),
            'extra_scelte' => TipoExtra::labels(),
            'tipologia_alloggio_scelte' => TipologiaAlloggio::scelte(),
            'stelle_scelte' => StelleAlloggio::scelte(),
            'trattamento_scelte' => array_combine(
                array_map(static fn (TrattamentoHotel $t) => $t->value, TrattamentoHotel::cases()),
                array_map(static fn (TrattamentoHotel $t) => $t->label(), TrattamentoHotel::cases()),
            ),
            'titolo' => $isNuovo ? 'Nuova offerta' : 'Modifica offerta',
        ]);
    }

    /**
     * Normalizza i campi dell'alloggio dal form (whitelist chiavi, trim, tagli).
     *
     * @param array<string, mixed> $dati
     *
     * @return array<string, string>
     */
    private function normalizzaAlloggio(array $dati): array
    {
        $out = [];
        foreach (['nome', 'citta', 'indirizzo', 'tipologia', 'stelle', 'trattamento'] as $k) {
            $v = trim((string) ($dati[$k] ?? ''));
            if ($v !== '') {
                $out[$k] = mb_substr($v, 0, 180);
            }
        }

        return $out;
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
