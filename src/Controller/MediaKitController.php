<?php

namespace App\Controller;

use App\Entity\MediaKitAsset;
use App\Enum\TipoAssetMediaKit;
use App\Service\UploaderCreator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Media Kit del Creator Studio, organizzato in SET: ogni set ha i suoi
 * slot (loghi quadricromia/light/dark, format, font). Il set 1 è quello
 * predefinito che gli editor usano; la scelta del set negli editor
 * arriverà in seguito. Carica il grafico; gli editor pescano da qui.
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
        ksort($assets);
        $setEsistenti = array_keys($assets) ?: [1];
        $prossimoSet = max($setEsistenti) + 1;

        $gruppiTipi = [];
        foreach (TipoAssetMediaKit::cases() as $tipo) {
            $gruppiTipi[$tipo->gruppo()][] = $tipo;
        }

        return $this->render('creator/media_kit.html.twig', [
            'assets' => $assets,
            'set_esistenti' => $setEsistenti,
            'prossimo_set' => $prossimoSet,
            'gruppi_tipi' => $gruppiTipi,
        ]);
    }

    #[Route('/creator-studio/media-kit/{gruppo}/{tipo}/carica', name: 'app_creator_mediakit_carica', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function carica(int $gruppo, string $tipo, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        $tipoAsset = TipoAssetMediaKit::tryFrom($tipo) ?? throw $this->createNotFoundException();
        if ($gruppo < 1 || $gruppo > 50) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('mediakit_carica_' . $gruppo . '_' . $tipoAsset->value, (string) $request->request->get('_token'))) {
            return $this->redirectToRoute('app_creator_mediakit');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        $ext = $file ? strtolower($file->getClientOriginalExtension() ?: (string) $file->guessExtension()) : '';
        $estensioniValide = $tipoAsset->isArchivio() ? ['zip'] : self::EXT_IMMAGINE;
        if ($file === null || !in_array($ext, $estensioniValide, true)) {
            $this->addFlash('error', $tipoAsset->isArchivio()
                ? 'Per i font carica un archivio ZIP.'
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

    #[Route('/creator-studio/media-kit/{gruppo}/{tipo}/rimuovi', name: 'app_creator_mediakit_rimuovi', requirements: ['gruppo' => '\d+'], methods: ['POST'])]
    public function rimuovi(int $gruppo, string $tipo, Request $request, EntityManagerInterface $em, UploaderCreator $uploader): Response
    {
        $tipoAsset = TipoAssetMediaKit::tryFrom($tipo) ?? throw $this->createNotFoundException();
        if ($this->isCsrfTokenValid('mediakit_rimuovi_' . $gruppo . '_' . $tipoAsset->value, (string) $request->request->get('_token'))) {
            $asset = $em->getRepository(MediaKitAsset::class)->findOneBy(['tipo' => $tipoAsset, 'gruppo' => $gruppo]);
            if ($asset !== null) {
                $uploader->elimina('mediakit', $asset->getFile());
                $em->remove($asset);
                $em->flush();
                $this->addFlash('success', $tipoAsset->label() . ' rimosso (set ' . $gruppo . ').');
            }
        }

        return $this->redirectToRoute('app_creator_mediakit');
    }
}
