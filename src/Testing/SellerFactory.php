<?php

declare(strict_types=1);

namespace NiekNijland\MotorOccasion\Testing;

use NiekNijland\MotorOccasion\Data\Province;
use NiekNijland\MotorOccasion\Data\Seller;

class SellerFactory
{
    public static function make(
        string $name = 'De Motor Shop',
        ?Province $province = Province::NoordHolland,
        string $website = 'https://www.example.nl',
        ?string $address = null,
        ?string $city = null,
        ?string $phone = null,
        ?string $postalCode = null,
    ): Seller {
        return new Seller(
            name: $name,
            province: $province,
            website: $website,
            address: $address,
            city: $city,
            phone: $phone,
            postalCode: $postalCode,
        );
    }

    public static function makeDealer(
        string $name = 'MotoPort Goes',
        ?Province $province = null,
        string $website = 'https://www.motoport-goes.nl',
        string $address = 'Nobelweg 4',
        string $city = 'Goes',
        string $phone = '0113-231640',
        ?string $postalCode = '4461 ZM',
    ): Seller {
        return new Seller(
            name: $name,
            province: $province,
            website: $website,
            address: $address,
            city: $city,
            phone: $phone,
            postalCode: $postalCode,
        );
    }

    public static function makePrivate(
        string $name = 'Particulier',
        ?Province $province = Province::ZuidHolland,
    ): Seller {
        return new Seller(
            name: $name,
            province: $province,
            website: '',
        );
    }
}
