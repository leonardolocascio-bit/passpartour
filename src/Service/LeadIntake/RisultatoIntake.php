<?php

namespace App\Service\LeadIntake;

use App\Entity\Lead;

/**
 * Esito di una singola operazione di ingestione lead.
 */
class RisultatoIntake
{
    public function __construct(
        public readonly EsitoIntake $esito,
        public readonly ?Lead $lead = null,
        public readonly ?string $messaggio = null,
    ) {
    }

    public static function creato(Lead $lead): self
    {
        return new self(EsitoIntake::CREATO, $lead);
    }

    public static function duplicato(Lead $esistente): self
    {
        return new self(EsitoIntake::DUPLICATO, $esistente, 'Contatto già presente in anagrafica lead');
    }

    public static function scartato(string $motivo): self
    {
        return new self(EsitoIntake::SCARTATO, null, $motivo);
    }
}
