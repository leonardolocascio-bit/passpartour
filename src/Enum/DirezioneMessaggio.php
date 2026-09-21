<?php

namespace App\Enum;

enum DirezioneMessaggio: string
{
    case ENTRATA = 'entrata';   // dal contatto verso l'agenzia
    case USCITA = 'uscita';     // dall'agenzia verso il contatto
}
