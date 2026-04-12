<?php

namespace App\Enums;

enum Currency: string
{
    case AED = 'AED';
    case USD = 'USD';
    case EUR = 'EUR';
    case GBP = 'GBP';
    case SAR = 'SAR';
    case INR = 'INR';
    case PKR = 'PKR';
    case EGP = 'EGP';
    case QAR = 'QAR';
    case KWD = 'KWD';
    case BHD = 'BHD';
    case OMR = 'OMR';
    case JOD = 'JOD';
    case TRY = 'TRY';
    case CAD = 'CAD';
    case AUD = 'AUD';
    case JPY = 'JPY';
    case CNY = 'CNY';
    case CHF = 'CHF';
    case SGD = 'SGD';
    case MYR = 'MYR';
    case PHP = 'PHP';
    case THB = 'THB';
    case IDR = 'IDR';
    case BRL = 'BRL';
    case ZAR = 'ZAR';
    case NGN = 'NGN';
    case KES = 'KES';
    case GHS = 'GHS';
    case MAD = 'MAD';

    public static function default(): self
    {
        return self::EGP;
    }

    public static function values(): array
    {
        return array_map(
            static fn (self $currency): string => $currency->value,
            self::cases()
        );
    }

    public static function options(): array
    {
        return array_map(
            static fn (self $currency): array => [
                'value' => $currency->value,
                'label' => $currency->value,
            ],
            self::cases()
        );
    }
}