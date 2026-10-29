<?php

namespace App\Enum;

enum TipoContenutoDigitale: string
{
    case POST = 'post';
    case CAROSELLO = 'carosello';
    case VIDEO = 'video';

    public function label(): string
    {
        return match ($this) {
            self::POST => 'Post singolo',
            self::CAROSELLO => 'Carosello',
            self::VIDEO => 'Video',
        };
    }
}
