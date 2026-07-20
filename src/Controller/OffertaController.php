<?php

namespace App\Controller;

use App\Entity\Lead;
use App\Entity\Offerta;
use App\Form\OffertaType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class OffertaController extends AbstractController
{
    #[Route('/offerte', name: 'app_offerta_index')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('offerta/index.html.twig', [
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/offerte/nuova', name: 'app_offerta_nuovo')]
    public function nuovo(Request $request, EntityManagerInterface $em): Response
    {
        return $this->salva(new Offerta(), $request, $em, true);
    }

    #[Route('/offerte/{id}/modifica', name: 'app_offerta_modifica', requirements: ['id' => '\d+'])]
    public function modifica(Offerta $offerta, Request $request, EntityManagerInterface $em): Response
    {
        return $this->salva($offerta, $request, $em, false);
    }

    private function salva(Offerta $offerta, Request $request, EntityManagerInterface $em, bool $isNuovo): Response
    {
        $form = $this->createForm(OffertaType::class, $offerta);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            'titolo' => $isNuovo ? 'Nuova offerta' : 'Modifica offerta',
        ]);
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
            if ($offerta->getDestinazione() && $offerta->getDestinazione()->getImmagine()) {
                $file = $this->getParameter('kernel.project_dir') . '/public/uploads/destinazioni/' . $offerta->getDestinazione()->getImmagine();
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
