<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Entity\ScenarioPreventivo;
use App\Entity\Utente;
use App\Entity\VoceCosto;
use App\Enum\CategoriaVoce;
use App\Form\PreventivoType;
use App\Enum\StatoPreventivo;
use App\Service\GeneratorePdf;
use App\Service\PreventivoCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class PreventivoController extends AbstractController
{
    #[Route('/preventivi', name: 'app_preventivo_index')]
    public function index(EntityManagerInterface $em, PreventivoCalculator $calc): Response
    {
        $preventivi = $em->getRepository(Preventivo::class)->findBy([], ['createdAt' => 'DESC']);

        return $this->render('preventivo/index.html.twig', [
            'preventivi' => $preventivi,
            'calc' => $calc,
        ]);
    }

    #[Route('/preventivi/nuovo', name: 'app_preventivo_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em): Response
    {
        $preventivo = new Preventivo();

        // pre-compilazione da lead
        if ($leadId = $request->query->get('lead')) {
            $lead = $em->getRepository(Lead::class)->find($leadId);
            if ($lead !== null) {
                $preventivo->setLead($lead);
                $preventivo->setTitolo($lead->getDestinazione() ? $lead->getDestinazione() . ($lead->getPeriodo() ? ' · ' . $lead->getPeriodo() : '') : '');
            }
        }
        // scenario di partenza con una voce vuota
        $scenario = (new ScenarioPreventivo())->setNome('Proposta')->setConsigliato(true);
        $scenario->addVoce((new VoceCosto())->setCategoria(CategoriaVoce::VOLO));
        $preventivo->addScenario($scenario);

        return $this->salva($preventivo, $request, $em, true);
    }

    #[Route('/preventivi/{id}/modifica', name: 'app_preventivo_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Preventivo $preventivo, Request $request, EntityManagerInterface $em): Response
    {
        return $this->salva($preventivo, $request, $em, false);
    }

    private function salva(Preventivo $preventivo, Request $request, EntityManagerInterface $em, bool $isNuovo): Response
    {
        $form = $this->createForm(PreventivoType::class, $preventivo);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->collegaRelazioni($preventivo);
            if ($isNuovo) {
                $preventivo->setNumero($this->generaNumero($em));
                if ($this->getUser() instanceof Utente) {
                    $preventivo->setCreatoDa($this->getUser());
                }
                $em->persist($preventivo);
            }
            $em->flush();
            $this->addFlash('success', 'Preventivo salvato.');

            return $this->redirectToRoute('app_preventivo_scheda', ['id' => $preventivo->getId()]);
        }

        return $this->render('preventivo/form.html.twig', [
            'form' => $form,
            'preventivo' => $preventivo,
            'categorie' => CategoriaVoce::cases(),
            'trattamenti' => \App\Enum\TrattamentoHotel::cases(),
            'destinazioni' => $em->getRepository(\App\Entity\Destinazione::class)->findBy([], ['nome' => 'ASC']),
            'titolo' => $isNuovo ? 'Nuovo preventivo' : 'Modifica ' . $preventivo->getNumero(),
        ]);
    }

    #[Route('/preventivi/{id}', name: 'app_preventivo_scheda', requirements: ['id' => '\d+'])]
    public function scheda(Preventivo $preventivo, PreventivoCalculator $calc): Response
    {
        return $this->render('preventivo/scheda.html.twig', [
            'preventivo' => $preventivo,
            'calc' => $calc,
        ]);
    }

    #[Route('/preventivi/{id}/anteprima', name: 'app_preventivo_anteprima', requirements: ['id' => '\d+'])]
    public function anteprima(Preventivo $preventivo, PreventivoCalculator $calc): Response
    {
        return $this->render('preventivo/anteprima.html.twig', [
            'preventivo' => $preventivo,
            'calc' => $calc,
        ]);
    }

    #[Route('/preventivi/{id}/pdf', name: 'app_preventivo_pdf', requirements: ['id' => '\d+'])]
    public function pdf(Preventivo $preventivo, PreventivoCalculator $calc, GeneratorePdf $pdf): Response
    {
        return new Response($this->costruisciPdf($preventivo, $calc, $pdf), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $preventivo->getNumero() . '.pdf"',
        ]);
    }

    #[Route('/preventivi/{id}/email', name: 'app_preventivo_email', requirements: ['id' => '\d+'])]
    public function email(
        Preventivo $preventivo,
        Request $request,
        EntityManagerInterface $em,
        PreventivoCalculator $calc,
        GeneratorePdf $pdf,
        MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')] string $mittente,
        #[Autowire('%env(MAILER_DSN)%')] string $mailerDsn,
    ): Response {
        $destinatarioDefault = $preventivo->getCliente()?->getEmail()
            ?? $preventivo->getLead()?->getEmail() ?? '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('preventivo_email_' . $preventivo->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Token non valido.');

                return $this->redirectToRoute('app_preventivo_email', ['id' => $preventivo->getId()]);
            }

            $a = trim((string) $request->request->get('destinatario'));
            $oggetto = trim((string) $request->request->get('oggetto')) ?: ('Proposta di viaggio · ' . $preventivo->getTitolo());
            $messaggio = trim((string) $request->request->get('messaggio'));

            if ($a === '' || !filter_var($a, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Indirizzo email destinatario non valido.');

                return $this->redirectToRoute('app_preventivo_email', ['id' => $preventivo->getId()]);
            }

            $email = (new Email())
                ->from(Address::create($mittente))
                ->to($a)
                ->subject($oggetto)
                ->html($this->renderView('emails/preventivo.html.twig', [
                    'preventivo' => $preventivo,
                    'messaggio' => $messaggio,
                ]))
                ->attach($this->costruisciPdf($preventivo, $calc, $pdf), $preventivo->getNumero() . '.pdf', 'application/pdf');

            try {
                $mailer->send($email);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Invio non riuscito: ' . $e->getMessage());

                return $this->redirectToRoute('app_preventivo_email', ['id' => $preventivo->getId()]);
            }

            $preventivo->setInviatoIl(new \DateTimeImmutable());
            if ($preventivo->getStato() === StatoPreventivo::BOZZA) {
                $preventivo->setStato(StatoPreventivo::INVIATO);
            }
            $em->flush();

            $this->addFlash('success', 'Preventivo inviato a ' . $a . '.');

            return $this->redirectToRoute('app_preventivo_scheda', ['id' => $preventivo->getId()]);
        }

        return $this->render('preventivo/email.html.twig', [
            'preventivo' => $preventivo,
            'destinatario' => $destinatarioDefault,
            'oggetto' => 'Proposta di viaggio · ' . $preventivo->getTitolo(),
            'mailer_reale' => !str_starts_with($mailerDsn, 'null'),
        ]);
    }

    private function costruisciPdf(Preventivo $preventivo, PreventivoCalculator $calc, GeneratorePdf $pdf): string
    {
        $logo = $this->getParameter('kernel.project_dir') . '/public/images/passpartour-color.png';
        $logoData = is_file($logo) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($logo)) : null;

        $html = $this->renderView('preventivo/pdf.html.twig', [
            'preventivo' => $preventivo,
            'calc' => $calc,
            'logo' => $logoData,
        ]);

        return $pdf->daHtml($html);
    }

    #[Route('/preventivi/{id}/elimina', name: 'app_preventivo_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function elimina(Preventivo $preventivo, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('preventivo_elimina_' . $preventivo->getId(), (string) $request->request->get('_token'))) {
            $em->remove($preventivo);
            $em->flush();
            $this->addFlash('success', 'Preventivo eliminato.');
        }

        return $this->redirectToRoute('app_preventivo_index');
    }

    /** Imposta i lati inversi delle relazioni e l'ordinamento (necessario con by_reference). */
    private function collegaRelazioni(Preventivo $preventivo): void
    {
        $i = 0;
        foreach ($preventivo->getScenari() as $scenario) {
            $scenario->setPreventivo($preventivo);
            $scenario->setOrdinamento($i++);
            $j = 0;
            foreach ($scenario->getVoci() as $voce) {
                $voce->setScenario($scenario);
                $voce->setOrdinamento($j++);
            }
        }
        $t = 0;
        foreach ($preventivo->getTappe() as $tappa) {
            $tappa->setPreventivo($preventivo);
            $tappa->setOrdinamento($t++);
        }
    }

    private function generaNumero(EntityManagerInterface $em): string
    {
        $anno = date('Y');
        $progressivo = $em->getRepository(Preventivo::class)->count([]) + 1;

        return sprintf('PRV-%s-%04d', $anno, $progressivo);
    }
}
