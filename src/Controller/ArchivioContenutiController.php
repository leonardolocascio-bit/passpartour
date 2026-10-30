<?php

namespace App\Controller;

use App\Entity\ContenutoDigitale;
use App\Entity\ContenutoPrint;
use App\Entity\MediaKitAsset;
use App\Entity\Offerta;
use App\Enum\FormatoStampa;
use App\Enum\TipoAssetMediaKit;
use App\Enum\TipoContenutoDigitale;
use App\Service\GeneratorePdf;
use App\Service\UploaderCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Archivio contenuti del Creator Studio: digital (post/caroselli/video,
 * con stato inedito/programmato/pubblicato) e print (PDF).
 */
class ArchivioContenutiController extends AbstractController
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    // ---- Digital ----

    #[Route('/creator-studio/archivio/digital', name: 'app_creator_archivio_digital')]
    public function digital(EntityManagerInterface $em): Response
    {
        return $this->render('creator/archivio_digital.html.twig', [
            'contenuti' => $em->getRepository(ContenutoDigitale::class)->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    /** Riceve il video esportato dall'Editor Video e lo archivia in Digital. */
    #[Route('/creator-studio/editor/video/archivia', name: 'app_creator_video_archivia', methods: ['POST'])]
    public function videoArchivia(Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if (!$this->isCsrfTokenValid('video_archivia', (string) $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'Token non valido.'], 403);
        }

        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        if ($file === null || !in_array($ext, ['webm', 'mp4'], true)) {
            return $this->json(['error' => 'Formato video non valido (webm o mp4).'], 400);
        }

        $titolo = trim((string) $request->request->get('titolo')) ?: 'Video';
        // estensione esplicita: il blob del MediaRecorder non sempre si sniffa come video
        $nomeFile = $uploader->salva($file, 'digital', $titolo, 'video-' . bin2hex(random_bytes(5)) . '.' . $ext);
        if ($nomeFile === null) {
            return $this->json(['error' => 'Salvataggio non riuscito.'], 500);
        }

        $contenuto = (new ContenutoDigitale())
            ->setTitolo(mb_substr($titolo, 0, 150))
            ->setTipo(TipoContenutoDigitale::VIDEO)
            ->setFile($nomeFile);
        $em->persist($contenuto);
        $em->flush();

        return $this->json([
            'ok' => true,
            'url' => $this->generateUrl('app_creator_digital_anteprima', ['id' => $contenuto->getId()]),
        ]);
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
            foreach ($contenuto->getTuttiIFile() as $file) {
                $uploader->elimina('digital', $file);
            }
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
        ]);
    }

    /** Genera il PDF di stampa dell'offerta lato server (dompdf) e lo archivia. */
    #[Route('/creator-studio/editor/print/genera', name: 'app_creator_print_genera', methods: ['POST'])]
    public function printGenera(Request $request, EntityManagerInterface $em, UploaderCreator $uploader, GeneratorePdf $pdf): Response
    {
        if (!$this->isCsrfTokenValid('print_genera', (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_print');
        }

        $offerta = $em->getRepository(Offerta::class)->find($request->request->getInt('offerta_id'));
        if ($offerta === null) {
            $this->addFlash('error', 'Scegli un\'offerta.');

            return $this->redirectToRoute('app_creator_print');
        }
        $formato = FormatoStampa::tryFrom((string) $request->request->get('formato')) ?? FormatoStampa::A4V;

        // logo dal Media Kit (set 1) se caricato, altrimenti quello ufficiale
        $logoKit = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => TipoAssetMediaKit::LOGO_LIGHT, 'gruppo' => 1]);
        $logo = $logoKit !== null
            ? 'public/uploads/creator/mediakit/' . $logoKit->getFile()
            : 'public/images/passpartour-white.png';

        // base grafica del cartello dal Media Kit (SVG/immagine), per orientamento
        $tipoBase = $formato->orientamento() === 'verticale'
            ? TipoAssetMediaKit::FORMAT_PRINT_VERTICALE
            : TipoAssetMediaKit::FORMAT_PRINT_ORIZZONTALE;
        $base = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => $tipoBase, 'gruppo' => 1]);

        $html = $this->renderView('offerta/stampa_pdf.html.twig', [
            'base_uri' => $base !== null ? $this->dataUri('public/uploads/creator/mediakit/' . $base->getFile()) : null,
            'offerta' => $offerta,
            'carta' => $formato->carta(),
            'orientamento' => $formato->orientamento(),
            'foto_uri' => $offerta->getImmagineFile() ? $this->dataUri('public/uploads/destinazioni/' . $offerta->getImmagineFile()) : null,
            'logo_uri' => $this->dataUri($logo),
            'to_logo_uri' => $offerta->getTourOperator()?->getImmagine()
                ? $this->dataUri('public/uploads/destinazioni/' . $offerta->getTourOperator()->getImmagine())
                : null,
        ]);
        $binario = $pdf->daHtml($html, $formato->orientamento() === 'verticale' ? 'portrait' : 'landscape', $formato->carta());

        $nomeFile = $uploader->salvaBinario($binario, 'print', $uploader->nomeFilePrint($offerta->getTitolo(), $formato->carta(), $formato->orientamento()));
        if ($nomeFile === null) {
            $this->addFlash('error', 'Salvataggio del PDF non riuscito.');

            return $this->redirectToRoute('app_creator_print');
        }

        $em->persist((new ContenutoPrint())
            ->setTitolo($offerta->getTitolo())->setOfferta($offerta)->setFormato($formato)->setFile($nomeFile));
        $em->flush();
        $this->addFlash('success', 'PDF generato e archiviato (' . $nomeFile . ').');

        return $this->redirectToRoute('app_creator_archivio_print');
    }

    /** Immagine locale come data URI (dompdf gira con isRemoteEnabled=false). */
    private function dataUri(string $percorsoRelativo): ?string
    {
        $percorso = $this->projectDir . '/' . $percorsoRelativo;
        $contenuto = @file_get_contents($percorso);
        if ($contenuto === false) {
            return null;
        }
        $mime = match (strtolower(pathinfo($percorso, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($contenuto);
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
