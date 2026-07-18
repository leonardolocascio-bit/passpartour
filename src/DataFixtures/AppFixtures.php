<?php

namespace App\DataFixtures;

use App\Entity\Attivita;
use App\Entity\Campagna;
use App\Entity\Cliente;
use App\Entity\Lead;
use App\Entity\Preventivo;
use App\Entity\RegolaNurturing;
use App\Entity\ScenarioPreventivo;
use App\Entity\Utente;
use App\Entity\VoceCosto;
use App\Enum\CategoriaVoce;
use App\Enum\FonteLead;
use App\Enum\StatoLead;
use App\Enum\StatoPreventivo;
use App\Enum\TipoAttivita;
use App\Service\MotoreNurturing;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
        private MotoreNurturing $motore,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // ---- Utenti ----
        $master = $this->utente('master@passpartour.local', 'Sara', 'Conti', ['ROLE_ADMIN']);
        $giulia = $this->utente('giulia@passpartour.local', 'Giulia', 'Rossi', ['ROLE_AGENTE']);
        $marco = $this->utente('marco@passpartour.local', 'Marco', 'Bianchi', ['ROLE_AGENTE']);
        foreach ([$master, $giulia, $marco] as $u) {
            $manager->persist($u);
        }

        // ---- Campagne ----
        $maldive = $this->campagna('Maldive Inverno 2026', FonteLead::META, '3500.00');
        $nyc = $this->campagna('Capodanno a New York', FonteLead::GOOGLE, '2200.00');
        $grecia = $this->campagna('Estate Grecia Isole', FonteLead::META, '1800.00');
        $fiera = $this->campagna('Fiera del Turismo (banco)', FonteLead::MANUALE, null);
        foreach ([$maldive, $nyc, $grecia, $fiera] as $c) {
            $manager->persist($c);
        }

        // ---- Regole di nurturing (automazioni per stato) ----
        $regole = [
            [StatoLead::NUOVO, TipoAttivita::EMAIL, 'Invia email di benvenuto', 0, 0],
            [StatoLead::NUOVO, TipoAttivita::CHIAMATA, 'Prima chiamata di qualifica', 1, 1],
            [StatoLead::CONTATTATO, TipoAttivita::EMAIL, 'Prepara e invia il preventivo', 2, 0],
            [StatoLead::PREVENTIVO_INVIATO, TipoAttivita::CHIAMATA, 'Follow-up sul preventivo', 2, 0],
            [StatoLead::PREVENTIVO_INVIATO, TipoAttivita::WHATSAPP, 'Promemoria WhatsApp preventivo', 5, 1],
            [StatoLead::TRATTATIVA, TipoAttivita::APPUNTAMENTO, 'Incontro/call di chiusura', 1, 0],
        ];
        foreach ($regole as $r) {
            $manager->persist(
                (new RegolaNurturing())
                    ->setStato($r[0])->setTipo($r[1])->setTitolo($r[2])
                    ->setGiorniOffset($r[3])->setOrdinamento($r[4])
            );
        }

        // ---- Lead ----
        $datiLead = [
            ['Luca', 'Ferrari', 'luca.ferrari@example.com', '3391112233', $maldive, FonteLead::META, StatoLead::NUOVO, 'Maldive', 'Feb 2026', 2, '4200.00', $giulia],
            ['Anna', 'Greco', 'anna.greco@example.com', '3480099887', $maldive, FonteLead::META, StatoLead::CONTATTATO, 'Maldive', 'Gen 2026', 2, '5000.00', $giulia],
            ['Davide', 'Riva', 'davide.riva@example.com', '3396655443', $nyc, FonteLead::GOOGLE, StatoLead::PREVENTIVO_INVIATO, 'New York', 'Dic 2026', 2, '3800.00', $marco],
            ['Chiara', 'Marino', 'chiara.marino@example.com', '3475544332', $grecia, FonteLead::META, StatoLead::TRATTATIVA, 'Santorini', 'Lug 2026', 4, '6000.00', $giulia],
            ['Paolo', 'Bruno', 'paolo.bruno@example.com', '3311234567', $grecia, FonteLead::META, StatoLead::NUOVO, 'Mykonos', 'Ago 2026', 2, '3000.00', $marco],
            ['Elena', 'Costa', 'elena.costa@example.com', '3399988776', $nyc, FonteLead::GOOGLE, StatoLead::CONTATTATO, 'New York', 'Dic 2026', 3, '5500.00', $marco],
            ['Simone', 'Galli', 'simone.galli@example.com', '3487766554', $fiera, FonteLead::MANUALE, StatoLead::VINTO, 'Sharm el Sheikh', 'Nov 2026', 2, '2400.00', $giulia],
            ['Martina', 'Fontana', 'martina.fontana@example.com', '3391122334', $maldive, FonteLead::META, StatoLead::PERSO, 'Maldive', 'Mar 2026', 2, '4000.00', $marco],
            ['Roberto', 'Serra', 'roberto.serra@example.com', null, null, FonteLead::WEBHOOK, StatoLead::NUOVO, 'Caraibi', 'Feb 2026', 2, '5200.00', null],
            ['Federica', 'Villa', 'federica.villa@example.com', '3466677889', $grecia, FonteLead::CSV, StatoLead::CONTATTATO, 'Creta', 'Giu 2026', 2, '2800.00', $giulia],
        ];

        /** @var Lead[] $leadByNome */
        $leadByNome = [];
        foreach ($datiLead as $d) {
            $lead = (new Lead())
                ->setNome($d[0])->setCognome($d[1])->setEmail($d[2])->setTelefono($d[3])
                ->setCampagna($d[4])->setFonte($d[5])->setStato($d[6])
                ->setDestinazione($d[7])->setPeriodo($d[8])->setNumeroPasseggeri($d[9])
                ->setBudgetIndicativo($d[10])->setAssegnatario($d[11])->setConsensoMarketing(true);
            $manager->persist($lead);
            $leadByNome[$d[0]] = $lead;
        }

        // Un paio di attività sui lead in lavorazione
        $manager->persist($this->attivita($leadByNome['Anna'], TipoAttivita::CHIAMATA, 'Prima chiamata di qualifica', $giulia, true));
        $manager->persist($this->attivita($leadByNome['Chiara'], TipoAttivita::EMAIL, 'Inviato preventivo Santorini', $giulia, true));
        $manager->persist($this->attivita($leadByNome['Chiara'], TipoAttivita::APPUNTAMENTO, 'Videocall di chiusura', $giulia, false));

        // ---- Cliente convertito ----
        $cliente = (new Cliente())
            ->setNome('Simone')->setCognome('Galli')
            ->setEmail('simone.galli@example.com')->setTelefono('3487766554');
        $manager->persist($cliente);
        $leadByNome['Simone']->setCliente($cliente);

        // ---- Preventivo con 3 scenari (Economy / Comfort / Luxury) ----
        $prev = (new Preventivo())
            ->setNumero('PRV-2026-0001')
            ->setTitolo('Santorini · 7 notti · Luglio 2026')
            ->setLead($leadByNome['Chiara'])
            ->setStato(StatoPreventivo::INVIATO)
            ->setValidoFino(new \DateTimeImmutable('+20 days'))
            ->setCreatoDa($giulia)
            ->setNote('Coppia + 2 ragazzi. Preferenza vista caldera.');
        $manager->persist($prev);

        $this->scenario($manager, $prev, 'Economy', '12.00', 0, false, [
            [CategoriaVoce::VOLO, 'Voli A/R Milano–Santorini', 4, '180.00'],
            [CategoriaVoce::HOTEL, 'Hotel 3★ centro Fira, 7 notti', 2, '540.00'],
            [CategoriaVoce::TRANSFER, 'Transfer aeroporto', 2, '25.00'],
        ]);

        $this->scenario($manager, $prev, 'Comfort', '15.00', 1, true, [
            [CategoriaVoce::VOLO, 'Voli A/R Milano–Santorini', 4, '210.00'],
            [CategoriaVoce::HOTEL, 'Hotel 4★ vista caldera, 7 notti', 2, '890.00'],
            [CategoriaVoce::TRANSFER, 'Transfer privato', 1, '90.00'],
            [CategoriaVoce::ESCURSIONE, 'Tour in caicco al tramonto', 4, '75.00'],
            [CategoriaVoce::ASSICURAZIONE, 'Assicurazione medico-bagaglio', 4, '35.00'],
        ]);

        $this->scenario($manager, $prev, 'Luxury', '18.00', 2, false, [
            [CategoriaVoce::VOLO, 'Voli A/R Milano–Santorini business', 4, '480.00'],
            [CategoriaVoce::HOTEL, 'Suite 5★ con piscina privata, 7 notti', 2, '1900.00'],
            [CategoriaVoce::TRANSFER, 'Transfer privato con NCC', 1, '150.00'],
            [CategoriaVoce::ESCURSIONE, 'Tour privato in yacht', 4, '260.00'],
            [CategoriaVoce::ASSICURAZIONE, 'Assicurazione all-risk', 4, '60.00'],
        ]);

        $manager->flush();

        // genera le attività di nurturing per i lead demo in base al loro stato attuale
        foreach ($leadByNome as $lead) {
            $this->motore->applicaRegole($lead, $lead->getStato());
        }
    }

    private function utente(string $email, string $nome, string $cognome, array $ruoli): Utente
    {
        $u = (new Utente())->setEmail($email)->setNome($nome)->setCognome($cognome)->setRoles($ruoli);
        $u->setPassword($this->hasher->hashPassword($u, 'passpartour'));

        return $u;
    }

    private function campagna(string $nome, FonteLead $fonte, ?string $budget): Campagna
    {
        return (new Campagna())->setNome($nome)->setFonte($fonte)->setBudget($budget)->setAttiva(true);
    }

    private function attivita(Lead $lead, TipoAttivita $tipo, string $titolo, Utente $ass, bool $done): Attivita
    {
        return (new Attivita())
            ->setLead($lead)->setTipo($tipo)->setTitolo($titolo)
            ->setAssegnatario($ass)->setCompletata($done)
            ->setDataScadenza($done ? null : new \DateTimeImmutable('+3 days'));
    }

    private function scenario(ObjectManager $m, Preventivo $prev, string $nome, string $markup, int $ord, bool $consigliato, array $voci): void
    {
        $s = (new ScenarioPreventivo())
            ->setNome($nome)->setMarkupPercentuale($markup)
            ->setOrdinamento($ord)->setConsigliato($consigliato);
        $prev->addScenario($s);
        $m->persist($s);

        $i = 0;
        foreach ($voci as $v) {
            $voce = (new VoceCosto())
                ->setCategoria($v[0])->setDescrizione($v[1])
                ->setQuantita($v[2])->setCostoUnitario($v[3])->setOrdinamento($i++);
            $s->addVoce($voce);
            $m->persist($voce);
        }
    }
}
