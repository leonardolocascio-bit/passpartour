<?php

namespace App\Service;

use App\Entity\Attivita;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Costruisce le griglie del calendario (giorno/settimana/mese/anno)
 * e vi distribuisce le attività per data di scadenza.
 */
class CalendarioService
{
    private const VISTE = ['giorno', 'settimana', 'mese', 'anno'];
    private const MESI = ['', 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno', 'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre'];
    private const GIORNI = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function calcola(string $vista, \DateTimeImmutable $rif): array
    {
        $vista = in_array($vista, self::VISTE, true) ? $vista : 'mese';
        $oggi = new \DateTimeImmutable('today');

        $base = [
            'vista' => $vista,
            'rif' => $rif,
            'oggi' => $oggi,
            'giorni_settimana' => self::GIORNI,
        ];

        return match ($vista) {
            'giorno' => $base + $this->giorno($rif, $oggi),
            'settimana' => $base + $this->settimana($rif, $oggi),
            'anno' => $base + $this->anno($rif, $oggi),
            default => $base + $this->mese($rif, $oggi),
        };
    }

    private function giorno(\DateTimeImmutable $rif, \DateTimeImmutable $oggi): array
    {
        $inizio = $rif->setTime(0, 0);
        $attivita = $this->attivitaTra($inizio, $inizio->modify('+1 day'));

        return [
            'titolo' => $this->formattaData($rif),
            'prev' => $rif->modify('-1 day'),
            'next' => $rif->modify('+1 day'),
            'attivita' => $attivita[$rif->format('Y-m-d')] ?? [],
        ];
    }

    private function settimana(\DateTimeImmutable $rif, \DateTimeImmutable $oggi): array
    {
        $lunedi = $rif->modify(('1' === $rif->format('N')) ? 'today' : 'monday this week')->setTime(0, 0);
        $fine = $lunedi->modify('+7 days');
        $perGiorno = $this->attivitaTra($lunedi, $fine);

        $giorni = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $lunedi->modify("+$i days");
            $giorni[] = [
                'data' => $d,
                'oggi' => $d->format('Y-m-d') === $oggi->format('Y-m-d'),
                'attivita' => $perGiorno[$d->format('Y-m-d')] ?? [],
            ];
        }

        return [
            'titolo' => $lunedi->format('d') . '–' . $lunedi->modify('+6 days')->format('d') . ' ' . self::MESI[(int) $lunedi->format('n')] . ' ' . $lunedi->format('Y'),
            'prev' => $lunedi->modify('-7 days'),
            'next' => $lunedi->modify('+7 days'),
            'giorni' => $giorni,
        ];
    }

    private function mese(\DateTimeImmutable $rif, \DateTimeImmutable $oggi): array
    {
        $primo = $rif->modify('first day of this month')->setTime(0, 0);
        $inizioGriglia = $primo->modify(('1' === $primo->format('N')) ? 'today' : 'monday this week');
        $fineGriglia = $inizioGriglia->modify('+42 days'); // 6 settimane
        $perGiorno = $this->attivitaTra($inizioGriglia, $fineGriglia);

        $settimane = [];
        $cursore = $inizioGriglia;
        for ($w = 0; $w < 6; $w++) {
            $riga = [];
            for ($d = 0; $d < 7; $d++) {
                $riga[] = [
                    'data' => $cursore,
                    'in_mese' => $cursore->format('n') === $primo->format('n'),
                    'oggi' => $cursore->format('Y-m-d') === $oggi->format('Y-m-d'),
                    'attivita' => $perGiorno[$cursore->format('Y-m-d')] ?? [],
                ];
                $cursore = $cursore->modify('+1 day');
            }
            $settimane[] = $riga;
        }

        return [
            'titolo' => self::MESI[(int) $primo->format('n')] . ' ' . $primo->format('Y'),
            'prev' => $primo->modify('-1 month'),
            'next' => $primo->modify('+1 month'),
            'settimane' => $settimane,
        ];
    }

    private function anno(\DateTimeImmutable $rif, \DateTimeImmutable $oggi): array
    {
        $primoAnno = $rif->modify('first day of January this year')->setTime(0, 0);
        $perGiorno = $this->attivitaTra($primoAnno, $primoAnno->modify('+1 year'));

        $anno = (int) $primoAnno->format('Y');
        $mesi = [];
        for ($m = 1; $m <= 12; $m++) {
            $primoMese = $primoAnno->setDate($anno, $m, 1);
            $count = 0;
            $giorniConAttivita = [];
            $giorniNelMese = (int) $primoMese->format('t');
            for ($g = 1; $g <= $giorniNelMese; $g++) {
                $chiave = $primoMese->format('Y-m-') . str_pad((string) $g, 2, '0', STR_PAD_LEFT);
                $n = count($perGiorno[$chiave] ?? []);
                if ($n > 0) {
                    $count += $n;
                    $giorniConAttivita[$g] = $n;
                }
            }
            $mesi[] = [
                'data' => $primoMese,
                'nome' => self::MESI[$m],
                'count' => $count,
                'giorni_con_attivita' => $giorniConAttivita,
                'giorni_totali' => $giorniNelMese,
                'offset' => (int) $primoMese->format('N') - 1,
            ];
        }

        return [
            'titolo' => $primoAnno->format('Y'),
            'prev' => $primoAnno->modify('-1 year'),
            'next' => $primoAnno->modify('+1 year'),
            'mesi' => $mesi,
        ];
    }

    /**
     * @return array<string, Attivita[]> attività raggruppate per Y-m-d
     */
    private function attivitaTra(\DateTimeImmutable $inizio, \DateTimeImmutable $fine): array
    {
        /** @var Attivita[] $lista */
        $lista = $this->em->getRepository(Attivita::class)->createQueryBuilder('a')
            ->andWhere('a.dataScadenza >= :i AND a.dataScadenza < :f')
            ->setParameter('i', $inizio)
            ->setParameter('f', $fine)
            ->orderBy('a.dataScadenza', 'ASC')
            ->getQuery()
            ->getResult();

        $perGiorno = [];
        foreach ($lista as $a) {
            $perGiorno[$a->getDataScadenza()->format('Y-m-d')][] = $a;
        }

        return $perGiorno;
    }

    private function formattaData(\DateTimeImmutable $d): string
    {
        return self::GIORNI[(int) $d->format('N') - 1] . ' ' . $d->format('d') . ' ' . self::MESI[(int) $d->format('n')] . ' ' . $d->format('Y');
    }
}
