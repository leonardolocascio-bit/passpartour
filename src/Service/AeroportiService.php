<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ricerca aeroporti per città / nome / codice IATA (dataset locale).
 */
class AeroportiService
{
    /** @var list<array{citta:string,nome:string,iata:string}>|null */
    private ?array $dati = null;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /**
     * @return list<array{citta:string,nome:string,iata:string,label:string}>
     */
    public function cerca(string $query, int $limit = 12): array
    {
        $q = $this->norm($query);
        if ($q === '') {
            return [];
        }

        $risultati = [];
        foreach ($this->dataset() as $a) {
            $citta = $this->norm($a['citta']);
            $nome = $this->norm($a['nome']);
            $iata = strtolower($a['iata']);

            $score = match (true) {
                str_starts_with($citta, $q) => 0,
                $iata === $q => 1,
                str_contains($citta, $q) => 2,
                str_contains($nome, $q) => 3,
                str_contains($iata, $q) => 4,
                default => null,
            };
            if ($score === null) {
                continue;
            }

            $risultati[] = [
                'score' => $score,
                'citta' => $a['citta'],
                'nome' => $a['nome'],
                'iata' => $a['iata'],
                'label' => $a['citta'] . ' ' . $a['nome'] . ' (' . $a['iata'] . ')',
            ];
        }

        usort($risultati, static fn ($x, $y) => [$x['score'], $x['citta'], $x['nome']] <=> [$y['score'], $y['citta'], $y['nome']]);

        return array_map(
            static fn ($r) => ['citta' => $r['citta'], 'nome' => $r['nome'], 'iata' => $r['iata'], 'label' => $r['label']],
            array_slice($risultati, 0, $limit)
        );
    }

    /** @return list<array{citta:string,nome:string,iata:string}> */
    private function dataset(): array
    {
        if ($this->dati === null) {
            $json = @file_get_contents($this->projectDir . '/data/aeroporti.json');
            $this->dati = $json ? (json_decode($json, true) ?: []) : [];
        }

        return $this->dati;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));

        return strtr($s, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
