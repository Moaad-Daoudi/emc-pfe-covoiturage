<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    /**
     * Plain-text password for all demo accounts:
     * ------------------------------------------
     * Password: Demo1234!
     * ------------------------------------------
     * Hash algorithm: Sodium / Argon2id via UserPasswordHasherInterface
     */
    public const DEMO_PASSWORD = 'Demo1234!';

    public const USER_ADMIN = 'user_admin';
    public const USER_AMINA = 'user_amina';
    public const USER_KARIM = 'user_karim';
    public const USER_YOUSSEF = 'user_youssef';
    public const USER_FATIMA = 'user_fatima';
    public const USER_OMAR = 'user_omar';
    public const USER_SALMA = 'user_salma';
    public const USER_MEHDI = 'user_mehdi';
    public const USER_KHALID = 'user_khalid';
    public const USER_LEILA = 'user_leila';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // =========================================================================
        // 1. Mandatory Demo Accounts (Password for all: Demo1234!)
        // =========================================================================

        // 1.1 Administrator Account (admin@covoit.ma / Demo1234!)
        $admin = new User();
        $admin->setEmail('admin@covoit.ma');
        $admin->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $admin->setFirstname('Admin');
        $admin->setLastName('SafarCar');
        $admin->setPhone('+212600000001');
        $admin->setBio('Administrateur principal de la plateforme SafarCar.');
        $admin->setCreatedAt(new \DateTimeImmutable('-6 months'));
        // Plain password: Demo1234!
        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::DEMO_PASSWORD));
        $manager->persist($admin);
        $this->addReference(self::USER_ADMIN, $admin);

        // 1.2 Amina Benali (amina@covoit.ma / Demo1234!)
        // Dual profile: Driver (Peugeot 208) + Passenger
        $amina = new User();
        $amina->setEmail('amina@covoit.ma');
        $amina->setRoles(['ROLE_USER']);
        $amina->setFirstname('Amina');
        $amina->setLastName('Benali');
        $amina->setPhone('+212661234567');
        $amina->setBio('Conductrice prudente, trajets fréquents entre le Souss, Marrakech et Essaouira.');
        $amina->setCreatedAt(new \DateTimeImmutable('-4 months'));
        // Plain password: Demo1234!
        $amina->setPassword($this->passwordHasher->hashPassword($amina, self::DEMO_PASSWORD));
        $manager->persist($amina);
        $this->addReference(self::USER_AMINA, $amina);

        $aminaVehicle = new Vehicle();
        $aminaVehicle->setOwner($amina);
        $aminaVehicle->setBrand('Peugeot');
        $aminaVehicle->setModel('208');
        $aminaVehicle->setColor('Gris Métallisé');
        $aminaVehicle->setLicensePlate('12345-A-1');
        $aminaVehicle->setSeats(4);
        $aminaVehicle->setAirConditioned(true);
        $manager->persist($aminaVehicle);
        $this->addReference('vehicle_amina', $aminaVehicle);

        // 1.3 Karim Idrissi (karim@covoit.ma / Demo1234!)
        // Active driver with multiple COMPLETED + SCHEDULED trips
        $karim = new User();
        $karim->setEmail('karim@covoit.ma');
        $karim->setRoles(['ROLE_USER']);
        $karim->setFirstname('Karim');
        $karim->setLastName('Idrissi');
        $karim->setPhone('+212662345678');
        $karim->setBio('Ingénieur consultant basé à Casablanca, trajets réguliers vers Rabat, Fès et le Sud.');
        $karim->setCreatedAt(new \DateTimeImmutable('-5 months'));
        // Plain password: Demo1234!
        $karim->setPassword($this->passwordHasher->hashPassword($karim, self::DEMO_PASSWORD));
        $manager->persist($karim);
        $this->addReference(self::USER_KARIM, $karim);

        $karimVehicle = new Vehicle();
        $karimVehicle->setOwner($karim);
        $karimVehicle->setBrand('Dacia');
        $karimVehicle->setModel('Duster');
        $karimVehicle->setColor('Noir Nacré');
        $karimVehicle->setLicensePlate('67890-B-26');
        $karimVehicle->setSeats(4);
        $karimVehicle->setAirConditioned(true);
        $manager->persist($karimVehicle);
        $this->addReference('vehicle_karim', $karimVehicle);

        // 1.4 Youssef Amrani (youssef@covoit.ma / Demo1234!)
        // Passenger only (no vehicle)
        $youssef = new User();
        $youssef->setEmail('youssef@covoit.ma');
        $youssef->setRoles(['ROLE_USER']);
        $youssef->setFirstname('Youssef');
        $youssef->setLastName('Amrani');
        $youssef->setPhone('+212663456789');
        $youssef->setBio('Étudiant à Marrakech, adepte du covoiturage pour mes déplacements régionaux.');
        $youssef->setCreatedAt(new \DateTimeImmutable('-3 months'));
        // Plain password: Demo1234!
        $youssef->setPassword($this->passwordHasher->hashPassword($youssef, self::DEMO_PASSWORD));
        $manager->persist($youssef);
        $this->addReference(self::USER_YOUSSEF, $youssef);

        // =========================================================================
        // 2. Additional Realistic Moroccan Users for Rich Data Set
        // =========================================================================

        // Fatima Zahra (Driver - Renault Clio 5)
        $fatima = new User();
        $fatima->setEmail('fatima@covoit.ma');
        $fatima->setRoles(['ROLE_USER']);
        $fatima->setFirstname('Fatima Zahra');
        $fatima->setLastName('El Mansouri');
        $fatima->setPhone('+212664567890');
        $fatima->setBio('Enseignante à Agadir, trajets réguliers vers Taroudant et Marrakech.');
        $fatima->setCreatedAt(new \DateTimeImmutable('-3 months'));
        // Plain password: Demo1234!
        $fatima->setPassword($this->passwordHasher->hashPassword($fatima, self::DEMO_PASSWORD));
        $manager->persist($fatima);
        $this->addReference(self::USER_FATIMA, $fatima);

        $fatimaVehicle = new Vehicle();
        $fatimaVehicle->setOwner($fatima);
        $fatimaVehicle->setBrand('Renault');
        $fatimaVehicle->setModel('Clio 5');
        $fatimaVehicle->setColor('Blanc Glacier');
        $fatimaVehicle->setLicensePlate('34567-D-1');
        $fatimaVehicle->setSeats(4);
        $fatimaVehicle->setAirConditioned(true);
        $manager->persist($fatimaVehicle);
        $this->addReference('vehicle_fatima', $fatimaVehicle);

        // Omar Tazi (Driver - Volkswagen Golf 8)
        $omar = new User();
        $omar->setEmail('omar@covoit.ma');
        $omar->setRoles(['ROLE_USER']);
        $omar->setFirstname('Omar');
        $omar->setLastName('Tazi');
        $omar->setPhone('+212665678901');
        $omar->setBio('Commercial dans l’agroalimentaire, axes autoroutiers Casa - Rabat - Tanger.');
        $omar->setCreatedAt(new \DateTimeImmutable('-4 months'));
        // Plain password: Demo1234!
        $omar->setPassword($this->passwordHasher->hashPassword($omar, self::DEMO_PASSWORD));
        $manager->persist($omar);
        $this->addReference(self::USER_OMAR, $omar);

        $omarVehicle = new Vehicle();
        $omarVehicle->setOwner($omar);
        $omarVehicle->setBrand('Volkswagen');
        $omarVehicle->setModel('Golf 8');
        $omarVehicle->setColor('Bleu Atlantique');
        $omarVehicle->setLicensePlate('45678-A-6');
        $omarVehicle->setSeats(4);
        $omarVehicle->setAirConditioned(true);
        $manager->persist($omarVehicle);
        $this->addReference('vehicle_omar', $omarVehicle);

        // Salma Kabbaj (Driver - Hyundai Tucson)
        $salma = new User();
        $salma->setEmail('salma@covoit.ma');
        $salma->setRoles(['ROLE_USER']);
        $salma->setFirstname('Salma');
        $salma->setLastName('Kabbaj');
        $salma->setPhone('+212666789012');
        $salma->setBio('Architecte, voyages fréquents entre Fès, Meknès et Casablanca.');
        $salma->setCreatedAt(new \DateTimeImmutable('-2 months'));
        // Plain password: Demo1234!
        $salma->setPassword($this->passwordHasher->hashPassword($salma, self::DEMO_PASSWORD));
        $manager->persist($salma);
        $this->addReference(self::USER_SALMA, $salma);

        $salmaVehicle = new Vehicle();
        $salmaVehicle->setOwner($salma);
        $salmaVehicle->setBrand('Hyundai');
        $salmaVehicle->setModel('Tucson');
        $salmaVehicle->setColor('Gris Foncé');
        $salmaVehicle->setLicensePlate('56789-A-15');
        $salmaVehicle->setSeats(4);
        $salmaVehicle->setAirConditioned(true);
        $manager->persist($salmaVehicle);
        $this->addReference('vehicle_salma', $salmaVehicle);

        // Mehdi Alami (Passenger)
        $mehdi = new User();
        $mehdi->setEmail('mehdi@covoit.ma');
        $mehdi->setRoles(['ROLE_USER']);
        $mehdi->setFirstname('Mehdi');
        $mehdi->setLastName('Alami');
        $mehdi->setPhone('+212667890123');
        $mehdi->setBio('Développeur freelance, adepte de mobilité partagée.');
        $mehdi->setCreatedAt(new \DateTimeImmutable('-2 months'));
        // Plain password: Demo1234!
        $mehdi->setPassword($this->passwordHasher->hashPassword($mehdi, self::DEMO_PASSWORD));
        $manager->persist($mehdi);
        $this->addReference(self::USER_MEHDI, $mehdi);

        // Khalid Berrada (Passenger)
        $khalid = new User();
        $khalid->setEmail('khalid@covoit.ma');
        $khalid->setRoles(['ROLE_USER']);
        $khalid->setFirstname('Khalid');
        $khalid->setLastName('Berrada');
        $khalid->setPhone('+212668901234');
        $khalid->setBio('Étudiant à Casablanca.');
        $khalid->setCreatedAt(new \DateTimeImmutable('-1 month'));
        // Plain password: Demo1234!
        $khalid->setPassword($this->passwordHasher->hashPassword($khalid, self::DEMO_PASSWORD));
        $manager->persist($khalid);
        $this->addReference(self::USER_KHALID, $khalid);

        // Leila Chraibi (Passenger)
        $leila = new User();
        $leila->setEmail('leila@covoit.ma');
        $leila->setRoles(['ROLE_USER']);
        $leila->setFirstname('Leila');
        $leila->setLastName('Chraibi');
        $leila->setPhone('+212669012345');
        $leila->setBio('Voyageuse passionnée par la côte atlantique marocaine.');
        $leila->setCreatedAt(new \DateTimeImmutable('-2 months'));
        // Plain password: Demo1234!
        $leila->setPassword($this->passwordHasher->hashPassword($leila, self::DEMO_PASSWORD));
        $manager->persist($leila);
        $this->addReference(self::USER_LEILA, $leila);

        $manager->flush();
    }
}
