<?php

namespace App\DataFixtures;

use App\Entity\City;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class CityFixtures extends Fixture
{
    public const CITY_AGADIR = 'city_agadir';
    public const CITY_MARRAKECH = 'city_marrakech';
    public const CITY_ESSAOUIRA = 'city_essaouira';
    public const CITY_CASABLANCA = 'city_casablanca';
    public const CITY_RABAT = 'city_rabat';
    public const CITY_TANGER = 'city_tanger';
    public const CITY_FES = 'city_fes';
    public const CITY_MEKNES = 'city_meknes';
    public const CITY_OUARZAZATE = 'city_ouarzazate';
    public const CITY_TAROUDANT = 'city_taroudant';
    public const CITY_TIZNIT = 'city_tiznit';
    public const CITY_SAFI = 'city_safi';
    public const CITY_EL_JADIDA = 'city_el_jadida';
    public const CITY_BENI_MELLAL = 'city_beni_mellal';
    public const CITY_CHICHAOUA = 'city_chichaoua';

    public function load(ObjectManager $manager): void
    {
        $citiesData = [
            self::CITY_AGADIR => ['name' => 'Agadir', 'lat' => 30.4278, 'lng' => -9.5981, 'region' => 'Souss-Massa'],
            self::CITY_MARRAKECH => ['name' => 'Marrakech', 'lat' => 31.6295, 'lng' => -7.9811, 'region' => 'Marrakech-Safi'],
            self::CITY_ESSAOUIRA => ['name' => 'Essaouira', 'lat' => 31.5085, 'lng' => -9.7595, 'region' => 'Marrakech-Safi'],
            self::CITY_CASABLANCA => ['name' => 'Casablanca', 'lat' => 33.5731, 'lng' => -7.5898, 'region' => 'Casablanca-Settat'],
            self::CITY_RABAT => ['name' => 'Rabat', 'lat' => 34.0209, 'lng' => -6.8416, 'region' => 'Rabat-Salé-Kénitra'],
            self::CITY_TANGER => ['name' => 'Tanger', 'lat' => 35.7595, 'lng' => -5.8340, 'region' => 'Tanger-Tétouan-Al Hoceïma'],
            self::CITY_FES => ['name' => 'Fès', 'lat' => 34.0181, 'lng' => -5.0078, 'region' => 'Fès-Meknès'],
            self::CITY_MEKNES => ['name' => 'Meknès', 'lat' => 33.8935, 'lng' => -5.5473, 'region' => 'Fès-Meknès'],
            self::CITY_OUARZAZATE => ['name' => 'Ouarzazate', 'lat' => 30.9189, 'lng' => -6.8934, 'region' => 'Drâa-Tafilalet'],
            self::CITY_TAROUDANT => ['name' => 'Taroudant', 'lat' => 30.4703, 'lng' => -8.8770, 'region' => 'Souss-Massa'],
            self::CITY_TIZNIT => ['name' => 'Tiznit', 'lat' => 29.6974, 'lng' => -9.7316, 'region' => 'Souss-Massa'],
            self::CITY_SAFI => ['name' => 'Safi', 'lat' => 32.2994, 'lng' => -9.2372, 'region' => 'Marrakech-Safi'],
            self::CITY_EL_JADIDA => ['name' => 'El Jadida', 'lat' => 33.2316, 'lng' => -8.5007, 'region' => 'Casablanca-Settat'],
            self::CITY_BENI_MELLAL => ['name' => 'Beni Mellal', 'lat' => 32.3373, 'lng' => -6.3498, 'region' => 'Béni Mellal-Khénifra'],
            self::CITY_CHICHAOUA => ['name' => 'Chichaoua', 'lat' => 31.5440, 'lng' => -8.7654, 'region' => 'Marrakech-Safi'],
        ];

        foreach ($citiesData as $reference => $data) {
            $city = new City();
            $city->setName($data['name']);
            $city->setLatitude($data['lat']);
            $city->setLongitude($data['lng']);
            $city->setRegion($data['region']);

            $manager->persist($city);
            $this->addReference($reference, $city);
        }

        $manager->flush();
    }
}
