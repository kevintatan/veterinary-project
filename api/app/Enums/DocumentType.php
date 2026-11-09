<?php

namespace App\Enums;

enum DocumentType: string
{
    case CC = 'CC';
    case CE = 'CE';
    case NIT = 'NIT';
    case PAS = 'PAS';
    case TI = 'TI';
    case PPT = 'PPT';
    case NUIP = 'NUIP';
    case DIE = 'DIE';

    public function label(): string
    {
        return match ($this) {
            self::CC => 'Cédula de ciudadanía',
            self::CE => 'Cédula de extranjería',
            self::NIT => 'NIT',
            self::PAS => 'Pasaporte',
            self::TI => 'Tarjeta de identidad',
            self::PPT => 'Permiso por protección temporal',
            self::NUIP => 'Registro civil (NUIP)',
            self::DIE => 'Documento de identificación extranjero',
        };
    }
}
