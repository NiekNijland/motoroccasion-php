<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Data;

enum Province: string
{
    case Drenthe = 'Drenthe';
    case Flevoland = 'Flevoland';
    case Friesland = 'Friesland';
    case Gelderland = 'Gelderland';
    case Groningen = 'Groningen';
    case Limburg = 'Limburg';
    case NoordBrabant = 'Noord-Brabant';
    case NoordHolland = 'Noord-Holland';
    case Overijssel = 'Overijssel';
    case Utrecht = 'Utrecht';
    case Zeeland = 'Zeeland';
    case ZuidHolland = 'Zuid-Holland';

    public static function tryFromAbbreviation(string $abbreviation): ?self
    {
        return match (strtoupper(trim($abbreviation))) {
            'DR', 'DRE' => self::Drenthe,
            'FL', 'FLE' => self::Flevoland,
            'FR', 'FRI' => self::Friesland,
            'GLD' => self::Gelderland,
            'GR', 'GRO' => self::Groningen,
            'LIM' => self::Limburg,
            'BRA', 'NB', 'N-B' => self::NoordBrabant,
            'NH', 'N-H' => self::NoordHolland,
            'OV', 'OVE' => self::Overijssel,
            'UTR', 'UT' => self::Utrecht,
            'Z', 'ZE', 'ZEE' => self::Zeeland,
            'ZH', 'Z-H' => self::ZuidHolland,
            default => null,
        };
    }
}
