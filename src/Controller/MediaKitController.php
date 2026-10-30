<?php

namespace App\Controller;

use App\Entity\MediaKitAsset;
use App\Entity\MediaKitIcona;
use App\Enum\TipoAssetMediaKit;
use App\Service\UploaderCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Media Kit del Creator Studio, organizzato in SET: ogni set ha i suoi
 * slot (loghi, format), i FONT indicati per nome (li applicano gli editor)
 * e la libreria di ICONE per i dettagli del viaggio (usate nelle righe).
 * Il set 1 è il predefinito; la scelta del set negli editor arriverà poi.
 */
class MediaKitController extends AbstractController
{
    private const EXT_IMMAGINE = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    #[Route('/creator-studio/media-kit', name: 'app_creator_mediakit')]
    public function index(EntityManagerInterface $em): Response
    {
        // assets[gruppo][tipo] = asset
        $assets = [];
        foreach ($em->getRepository(MediaKitAsset::class)->findAll() as $asset) {
            $assets[$asset->getGruppo()][$asset->getTipo()->value] = $asset;
        }
        // icone[gruppo] = [icona, …]
        $icone = [];
        foreach ($em->getRepository(MediaKitIcona::class)->findBy([], ['nome' => 'ASC']) as $icona) {
            $icone[$icona->getGruppo()][] = $icona;
        }
        $gruppiTipi = [];
        foreach (TipoAssetMediaKit::cases() as $tipo) {
            $gruppiTipi[$tipo->gruppo()][] = $tipo;
        }

        // i set sono INDIPENDENTI per categoria: ogni categoria mostra i propri
        // set esistenti e il proprio «Aggiungi set N»
        $setPerCategoria = [];
        foreach ($gruppiTipi as $categoria => $tipi) {
            $usati = [];
            foreach ($tipi as $tipo) {
                foreach ($assets as $g => $perTipo) {
                    if (isset($perTipo[$tipo->value])) {
                        $usati[$g] = true;
                    }
                }
            }
            $lista = array_keys($usati) ?: [1];
            sort($lista);
            $setPerCategoria[$categoria] = ['set' => $lista, 'prossimo' => max($lista) + 1];
        }
        $setIcone = array_keys($icone) ?: [1];
        sort($setIcone);
        $setPerCategoria['Icone'] = ['set' => $setIcone, 'prossimo' => max($setIcone) + 1];

        return $this->render('creator/media_kit.html.twig', [
            'assets' => $assets,
            'icone' => $icone,
            'gruppi_tipi' => $gruppiTipi,
            'set_categoria' => $setPerCategoria,
        ]);
    }

    #[Route('/creator-studio/media-kit/{gruppo}/{tipo}/carica', name: 'app_creator_mediakit_carica', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function carica(int $gruppo, string $tipo, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        $tipoAsset = TipoAssetMediaKit::tryFrom($tipo) ?? throw $this->createNotFoundException();
        if ($gruppo < 1 || $gruppo > 50 || $tipoAsset->isTesto()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('mediakit_carica_' . $gruppo . '_' . $tipoAsset->value, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_mediakit');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        $estensioni = $tipoAsset->isVideo() ? ['mp4', 'webm', 'mov'] : self::EXT_IMMAGINE;
        if ($file === null || !in_array($ext, $estensioni, true)) {
            $this->addFlash('error', $tipoAsset->isVideo()
                ? 'Per gli sting carica un video (MP4/WebM/MOV).'
                : 'Carica un\'immagine (JPG/PNG/WebP/SVG).');

            return $this->redirectToRoute('app_creator_mediakit');
        }

        $asset = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => $tipoAsset, 'gruppo' => $gruppo])
            ?? new MediaKitAsset($tipoAsset, $gruppo);

        if ($asset->getFile() !== '') {
            $uploader->elimina('mediakit', $asset->getFile());
        }

        // nome stabile per slot+set (+ suffisso anti-cache del browser)
        $nomeFile = $uploader->salva($file, 'mediakit', $tipoAsset->value, 'set' . $gruppo . '-' . $tipoAsset->value . '-' . bin2hex(random_bytes(3)) . '.' . $ext);
        if ($nomeFile === null) {
            $this->addFlash('error', 'Salvataggio del file non riuscito.');

            return $this->redirectToRoute('app_creator_mediakit');
        }

        $asset->setFile($nomeFile)->setNomeOriginale($file->getClientOriginalName());
        $em->persist($asset);
        $em->flush();
        $this->addFlash('success', $tipoAsset->label() . ' aggiornato (set ' . $gruppo . ').');

        return $this->redirectToRoute('app_creator_mediakit');
    }

    /** Font indicati per nome (Google Fonts): titoli + testo, per set. */
    #[Route('/creator-studio/media-kit/{gruppo}/font', name: 'app_creator_mediakit_font', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function salvaFont(int $gruppo, Request $request, EntityManagerInterface $em): Response
    {
        if ($gruppo < 1 || $gruppo > 50
            || !$this->isCsrfTokenValid('mediakit_font_' . $gruppo, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_mediakit');
        }

        // solo il nome della famiglia: niente apici o caratteri da URL/CSS
        $pulisci = static fn (string $v): string => mb_substr(trim(preg_replace('/[^\p{L}\p{N} \-]/u', '', $v) ?? ''), 0, 60);
        $titoli = $pulisci((string) $request->request->get('font_titoli'));
        $testo = $pulisci((string) $request->request->get('font_testo'));

        $asset = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => TipoAssetMediaKit::FONT, 'gruppo' => $gruppo]);
        if ($titoli === '' && $testo === '') {
            if ($asset !== null) {
                $em->remove($asset);
                $em->flush();
            }
            $this->addFlash('success', 'Font rimossi (set ' . $gruppo . '): gli editor tornano al font di sistema.');

            return $this->redirectToRoute('app_creator_mediakit');
        }

        $asset ??= new MediaKitAsset(TipoAssetMediaKit::FONT, $gruppo);
        $asset->setValore(array_filter(['titoli' => $titoli, 'testo' => $testo]));
        $em->persist($asset);
        $em->flush();
        $this->addFlash('success', 'Font salvati (set ' . $gruppo . '): gli editor li applicano da Google Fonts.');

        return $this->redirectToRoute('app_creator_mediakit');
    }

    #[Route('/creator-studio/media-kit/{gruppo}/{tipo}/rimuovi', name: 'app_creator_mediakit_rimuovi', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function rimuovi(int $gruppo, string $tipo, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        $tipoAsset = TipoAssetMediaKit::tryFrom($tipo) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('mediakit_rimuovi_' . $gruppo . '_' . $tipoAsset->value, (string) $request->request->get('_token'))) {
            $asset = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => $tipoAsset, 'gruppo' => $gruppo]);
            if ($asset !== null) {
                if ($asset->getFile() !== '') {
                    $uploader->elimina('mediakit', $asset->getFile());
                }
                $em->remove($asset);
                $em->flush();
                $this->addFlash('success', $tipoAsset->label() . ' rimosso (set ' . $gruppo . ').');
            }
        }

        return $this->redirectToRoute('app_creator_mediakit');
    }

    // ---- Icone (libreria per i dettagli del viaggio) ----

    #[Route('/creator-studio/media-kit/icone/{gruppo}/carica', name: 'app_creator_mediakit_icona_carica', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function caricaIcona(int $gruppo, Request $request, EntityManagerInterface $em, UploaderCreator $uploader, SluggerInterface $slugger): Response
    {
        if ($gruppo < 1 || $gruppo > 50
            || !$this->isCsrfTokenValid('mediakit_icona_' . $gruppo, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_mediakit');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        if ($file === null || !in_array($ext, self::EXT_IMMAGINE, true)) {
            $this->addFlash('error', 'Carica un\'icona come immagine (meglio PNG/SVG con trasparenza).');

            return $this->redirectToRoute('app_creator_mediakit');
        }

        $nome = mb_substr(trim((string) $request->request->get('nome')), 0, 100)
            ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        $nomeFile = $uploader->salva($file, 'mediakit', $nome, 'icona-set' . $gruppo . '-' . $slugger->slug($nome)->lower() . '-' . bin2hex(random_bytes(3)) . '.' . $ext);
        if ($nomeFile === null) {
            $this->addFlash('error', 'Salvataggio dell\'icona non riuscito.');

            return $this->redirectToRoute('app_creator_mediakit');
        }

        // variante dark (blu, per fondi chiari): facoltativa
        $nomeFileDark = null;
        /** @var UploadedFile|null $fileDark */
        $fileDark = $request->files->get('file_dark');
        if ($fileDark !== null) {
            $extDark = strtolower($fileDark->getClientOriginalExtension() ?: (string) $fileDark->guessExtension());
            if (in_array($extDark, self::EXT_IMMAGINE, true)) {
                $nomeFileDark = $uploader->salva($fileDark, 'mediakit', $nome, 'icona-set' . $gruppo . '-' . $slugger->slug($nome)->lower() . '-dark-' . bin2hex(random_bytes(3)) . '.' . $extDark);
            }
        }

        $em->persist((new MediaKitIcona($gruppo))->setNome($nome)->setFile($nomeFile)->setFileDark($nomeFileDark));
        $em->flush();
        $this->addFlash('success', 'Icona «' . $nome . '» aggiunta (set ' . $gruppo . '): la trovi nel picker delle righe.');

        return $this->redirectToRoute('app_creator_mediakit');
    }

    #[Route('/creator-studio/media-kit/icone/{id}/elimina', name: 'app_creator_mediakit_icona_elimina', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function eliminaIcona(MediaKitIcona $icona, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        if ($this->isCsrfTokenValid('mediakit_icona_elimina_' . $icona->getId(), (string) $request->request->get('_token'))) {
            $uploader->elimina('mediakit', $icona->getFile());
            if ($icona->getFileDark() !== null) {
                $uploader->elimina('mediakit', $icona->getFileDark());
            }
            $em->remove($icona);
            $em->flush();
            $this->addFlash('success', 'Icona eliminata (le righe che la usavano tornano al segnaposto).');
        }

        return $this->redirectToRoute('app_creator_mediakit');
    }
}
