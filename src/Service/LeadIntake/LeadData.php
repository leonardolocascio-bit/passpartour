<?php

namespace App\Service\LeadIntake;

use App\Enum\FonteLead;

/**
 * Dati grezzi in ingresso di un lead, normalizzati e indipendenti dalla fonte
 * (form manuale, riga CSV, payload webhook). Il mapping da array è tollerante:
 * accetta gli alias di chiave più comuni delle piattaforme di lead gen.
 */
class LeadData
{
    public ?string $nome = null;
    public ?string $cognome = null;
    public ?string $email = null;
    public ?string $telefono = null;
    public FonteLead $fonte = FonteLead::WEBHOOK;
    public ?string $campagnaNome = null;
    public ?string $destinazione = null;
    public ?string $periodo = null;
    public ?int $numeroPasseggeri = null;
    public ?string $budgetIndicativo = null;
    public ?string $note = null;
    public bool $consensoMarketing = false;

    /** Mappa un array grezzo (chiavi in vari formati) su LeadData. */
    public static function fromArray(array $raw, FonteLead $fonte): self
    {
        // normalizza le chiavi: minuscolo, senza spazi/trattini
        $d = [];
        foreach ($raw as $k => $v) {
            $key = strtolower(str_replace([' ', '-'], '_', trim((string) $k)));
            $d[$key] = is_string($v) ? trim($v) : $v;
        }
        $get = static function (array $aliases) use ($d) {
            foreach ($aliases as $a) {
                if (isset($d[$a]) && $d[$a] !== '') {
                    return $d[$a];
                }
            }
            return null;
        };

        $data = new self();
        $data->fonte = $fonte;
        $data->nome = $get(['nome', 'name', 'first_name', 'firstname', 'nome_completo', 'full_name']);
        $data->cognome = $get(['cognome', 'surname', 'last_name', 'lastname']);

        // se arriva solo il nome completo, spezzalo
        if ($data->cognome === null && $data->nome !== null && str_contains($data->nome, ' ')
            && $get(['first_name', 'firstname']) === null) {
            $parti = preg_split('/\s+/', $data->nome, 2);
            $data->nome = $parti[0];
            $data->cognome = $parti[1] ?? null;
        }

        $data->email = $get(['email', 'e_mail', 'mail', 'indirizzo_email']);
        $data->telefono = $get(['telefono', 'phone', 'phone_number', 'tel', 'cellulare', 'mobile', 'numero']);
        $data->destinazione = $get(['destinazione', 'destination', 'meta', 'dove']);
        $data->periodo = $get(['periodo', 'period', 'quando', 'data_partenza', 'date']);

        $pax = $get(['numero_passeggeri', 'passeggeri', 'pax', 'travelers', 'num_persone', 'persone']);
        $data->numeroPasseggeri = $pax !== null && is_numeric($pax) ? (int) $pax : null;

        $budget = $get(['budget_indicativo', 'budget', 'importo', 'spesa']);
        if ($budget !== null) {
            $budget = str_replace([',', '€', ' '], ['.', '', ''], (string) $budget);
            $data->budgetIndicativo = is_numeric($budget) ? number_format((float) $budget, 2, '.', '') : null;
        }

        $data->note = $get(['note', 'notes', 'message', 'messaggio', 'richiesta']);
        $data->campagnaNome = $get(['campagna', 'campaign', 'campaign_name', 'adset', 'adset_name', 'form_name']);

        $consenso = $get(['consenso', 'consenso_marketing', 'consent', 'marketing_consent', 'privacy']);
        $data->consensoMarketing = in_array(strtolower((string) $consenso), ['1', 'true', 'si', 'sì', 'yes', 'on', 'y'], true);

        return $data;
    }

    /** Nome minimo mostrabile: nome fornito, o prefisso email, o segnaposto. */
    public function nomeEffettivo(): string
    {
        if ($this->nome !== null && $this->nome !== '') {
            return $this->nome;
        }
        if ($this->email) {
            return ucfirst(explode('@', $this->email)[0]);
        }
        return 'Lead';
    }

    /** Ci sono abbastanza dati per identificare il contatto? */
    public function haIdentificativo(): bool
    {
        return ($this->email !== null && $this->email !== '')
            || ($this->telefono !== null && $this->telefono !== '')
            || ($this->nome !== null && $this->nome !== '');
    }
}
