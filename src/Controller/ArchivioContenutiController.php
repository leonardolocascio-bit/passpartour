<?php

namespace App\Controller;

use App\Entity\ContenutoDigitale;
use App\Entity\ContenutoPrint;
use App\Entity\Offerta;
use App\Enum\FormatoStampa;
use App\Enum\TipoContenutoDigitale;
use App\Service\UploaderCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Archivio contenuti del Creator Studio: digital (post/caroselli/video,
 * con stato inedito/programmato/pubblicato) e print (PDF).
 */
class ArchivioContenutiController extends AbstractController
{
    private const EXT_DIGITAL = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'webm'];

    // ---- Digital ----

    #[Route('/creator-studio/archivio/digital', name: 'app_creator_archivio_digital')]
    public function digital(EntityManagerInterface $em): Response
    {
        return $this->render('creator/archivio_digital.html.twig', [
            'contenuti' => $em->getRepository(ContenutoDigitale::class)->findBy([], ['createdAt' => 'DESC']),
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['titolo' => 'ASC']),
            'tipi' => TipoContenutoDigitale::cases(),
        ]);
    }

    #[Route('/creator-studio/archivio/digital/nuovo', name: 'app_creator_digital_nuovo', methods: ['POST'])]
    public function digitalNuovo(Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if (!$this->isCsrfTokenValid('digital_nuovo', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_archivio_digital');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        if ($file === null || !in_array($ext, self::EXT_DIGITAL, true)) {
            $this->addFlash('error', 'Carica un file immagine (JPG/PNG/WebP) o video (MP4/MOV/WebM).');

            return $this->redirectToRoute('app_creator_archivio_digital');
        }

        $titolo = trim((string) $request->request->get('titolo')) ?: 'Contenuto';
        $nomeFile = $uploader->salva($file, 'digital', $titolo);
        if ($nomeFile === null) {
            $this->addFlash('error', 'Salvataggio del file non riuscito.');

            return $this->redirectToRoute('app_creator_archivio_digital');
        }

        $contenuto = (new ContenutoDigitale())
            ->setTitolo($titolo)
            ->setTipo(TipoContenutoDigitale::tryFrom((string) $request->request->get('tipo')) ?? TipoContenutoDigitale::POST)
            ->setCaption(trim((string) $request->request->get('caption')) ?: null)
            ->setFile($nomeFile);

        if ($offertaId = $request->request->getInt('offerta_id')) {
            $contenuto->setOfferta($em->getRepository(Offerta::class)->find($offertaId));
        }
        if ($data = (string) $request->request->get('data_pubblicazione')) {
            try {
                $contenuto->setDataPubblicazione(new \DateTimeImmutable($data));
            } catch (\Exception) {
            }
        }

        $em->persist($contenuto);
        $em->flush();
        $this->addFlash('success', 'Contenuto aggiunto all\'archivio.');

        return $this->redirectToRoute('app_creator_archivio_digital');
    }

    #[Route('/creator-studio/archivio/digital/{id}', name: 'app_creator_digital_anteprima', requirements: ['id' => '\d+'])]
    public function digitalAnteprima(ContenutoDigitale $contenuto): Response
    {
        return $this->render('creator/digital_anteprima.html.twig', ['contenuto' => $contenuto]);
    }

    #[Route('/creator-studio/archivio/digital/{id}/pubblicato', name: 'app_creator_digital_pubblicato', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function digitalPubblicato(ContenutoDigitale $contenuto, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('digital_pubblicato_' . $contenuto->getId(), (string) $request->request->get('_token'))) {
            $contenuto->setPubblicato(!$contenuto->isPubblicato());
            $em->flush();
        }

        return $this->redirectToRoute('app_creator_digital_anteprima', ['id' => $contenuto->getId()]);
    }

    #[Route('/creator-studio/archivio/digital/{id}/elimina', name: 'app_creator_digital_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function digitalElimina(ContenutoDigitale $contenuto, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if ($this->isCsrfTokenValid('digital_elimina_' . $contenuto->getId(), (string) $request->request->get('_token'))) {
            $uploader->elimina('digital', $contenuto->getFile());
            $em->remove($contenuto);
            $em->flush();
            $this->addFlash('success', 'Contenuto eliminato.');
        }

        return $this->redirectToRoute('app_creator_archivio_digital');
    }

    // ---- Print ----

    #[Route('/creator-studio/archivio/print', name: 'app_creator_archivio_print')]
    public function print(EntityManagerInterface $em): Response
    {
        return $this->render('creator/archivio_print.html.twig', [
            'contenuti' => $em->getRepository(ContenutoPrint::class)->findBy([], ['createdAt' => 'DESC']),
            'offerte' => $em->getRepository(Offerta::class)->findBy([], ['titolo' => 'ASC']),
            'formati' => FormatoStampa::cases(),
        ]);
    }

    #[Route('/creator-studio/archivio/print/nuovo', name: 'app_creator_print_nuovo', methods: ['POST'])]
    public function printNuovo(Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if (!$this->isCsrfTokenValid('print_nuovo', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_archivio_print');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        if ($file === null || $ext !== 'pdf') {
            $this->addFlash('error', 'Carica un file PDF.');

            return $this->redirectToRoute('app_creator_archivio_print');
        }

        $offerta = null;
        if ($offertaId = $request->request->getInt('offerta_id')) {
            $offerta = $em->getRepository(Offerta::class)->find($offertaId);
        }
        $titolo = $offerta?->getTitolo() ?: (trim((string) $request->request->get('titolo')) ?: 'Offerta');
        $formato = FormatoStampa::tryFrom((string) $request->request->get('formato')) ?? FormatoStampa::A4V;

        // convenzione: [offerta]_[formato]_[orientamento]_[data generazione].pdf
        $nomeFile = $uploader->salva($file, 'print', $titolo, $uploader->nomeFilePrint($titolo, $formato->carta(), $formato->orientamento()));
        if ($nomeFile === null) {
            $this->addFlash('error', 'Salvataggio del file non riuscito.');

            return $this->redirectToRoute('app_creator_archivio_print');
        }

        $em->persist((new ContenutoPrint())
            ->setTitolo($titolo)->setOfferta($offerta)->setFormato($formato)->setFile($nomeFile));
        $em->flush();
        $this->addFlash('success', 'PDF aggiunto all\'archivio print.');

        return $this->redirectToRoute('app_creator_archivio_print');
    }

    #[Route('/creator-studio/archivio/print/{id}/elimina', name: 'app_creator_print_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function printElimina(ContenutoPrint $contenuto, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if ($this->isCsrfTokenValid('print_elimina_' . $contenuto->getId(), (string) $request->request->get('_token'))) {
            $uploader->elimina('print', $contenuto->getFile());
            $em->remove($contenuto);
            $em->flush();
            $this->addFlash('success', 'PDF eliminato.');
        }

        return $this->redirectToRoute('app_creator_archivio_print');
    }
}
