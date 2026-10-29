<?php

namespace App\Service;

use App\Entity\Lead;
use App\Enum\StatoLead;
use App\Enum\TipoAttivita;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Automatismi della pipeline lead: gli eventi fanno avanzare lo stato
 * (mai regredire, mai toccare i lead chiusi), applicando le regole di
 * nurturing dello stato raggiunto.
 * - attività di contatto (chiamata/mail/WhatsApp/SMS/appuntamento) → contattato
 * - preventivo creato → preventivo inviato
 * - preventivo inviato via email → trattativa
 * - preventivo accettato → vinto (gestito dalla trasformazione in vendita)
 */
class PipelineLead
{
    private const ORDINE = [
        'nuovo' => 0,
        'contattato' => 1,
        'preventivo_inviato' => 2,
        'trattativa' => 3,
        'vinto' => 4,
        'perso' => 4,
    ];

    private const TIPI_CONTATTO = [
        TipoAttivita::CHIAMATA,
        TipoAttivita::EMAIL,
        TipoAttivita::SMS,
        TipoAttivita::WHATSAPP,
        TipoAttivita::APPUNTAMENTO,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MotoreNurturing $motore,
    ) {
    }

    public function eventoContatto(Lead $lead, TipoAttivita $tipo): bool
    {
        if (!in_array($tipo, self::TIPI_CONTATTO, true)) {
            return false;
        }

        return $this->avanza($lead, StatoLead::CONTATTATO);
    }

    public function eventoPreventivoCreato(?Lead $lead): bool
    {
        return $lead !== null && $this->avanza($lead, StatoLead::PREVENTIVO_INVIATO);
    }

    public function eventoPreventivoInviato(?Lead $lead): bool
    {
        return $lead !== null && $this->avanza($lead, StatoLead::TRATTATIVA);
    }

    /** Avanza solo in avanti; i lead chiusi (vinto/perso) non si toccano. */
    private function avanza(Lead $lead, StatoLead $nuovo): bool
    {
        $da = self::ORDINE[$lead->getStato()->value] ?? 0;
        $a = self::ORDINE[$nuovo->value] ?? 0;
        if ($a <= $da || $da >= self::ORDINE[StatoLead::VINTO->value]) {
            return false;
        }

        $lead->setStato($nuovo);
        $this->em->flush();
        $this->motore->applicaRegole($lead, $nuovo);

        return true;
    }
}
