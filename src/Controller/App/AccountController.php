<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccountController extends AbstractController
{
    /**
     * Screen S10: Mon compte & Paramètres (/account)
     */
    #[Route('/account', name: 'app_account_index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        VehicleRepository $vehicleRepository
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            $user = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        $vehicles = [];
        if ($user !== null) {
            $vehicles = $vehicleRepository->findBy(['owner' => $user]);
        }

        return $this->render('app/account/index.html.twig', [
            'user' => $user,
            'vehicles' => $vehicles,
        ]);
    }

    /**
     * Save user profile changes
     */
    #[Route('/account', name: 'app_account_update', methods: ['POST'])]
    public function update(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            $user = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        if ($user !== null) {
            $bio = trim((string) $request->request->get('bio', ''));
            $phone = trim((string) $request->request->get('phone', ''));
            if ($bio !== '') {
                $user->setBio($bio);
            }
            if ($phone !== '') {
                $user->setPhone($phone);
            }
            $em->flush();

            $this->addFlash('success', 'Vos coordonnées et préférences ont été mises à jour avec succès.');
        }

        return $this->redirectToRoute('app_account_index');
    }

    /**
     * Add new vehicle
     */
    #[Route('/account/vehicles/new', name: 'app_account_vehicle_new', methods: ['POST'])]
    public function addVehicle(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user === null) {
            $user = $userRepository->findOneBy(['email' => 'amina@covoit.ma']);
        }

        if ($user !== null) {
            $brand = trim((string) $request->request->get('brand', 'Dacia'));
            $model = trim((string) $request->request->get('model', 'Duster'));
            $color = trim((string) $request->request->get('color', 'Blanc Nacré'));
            $plate = trim((string) $request->request->get('license_plate', '45678-A-33'));
            $seats = max(2, min(7, (int) $request->request->get('seats', 4)));

            $vehicle = (new Vehicle())
                ->setOwner($user)
                ->setBrand($brand)
                ->setModel($model)
                ->setColor($color)
                ->setLicensePlate($plate)
                ->setSeats($seats)
                ->setAirConditioned(true);

            $em->persist($vehicle);
            $em->flush();

            $this->addFlash('success', sprintf('Véhicule %s %s homologué et ajouté à votre garage.', $brand, $model));
        }

        return $this->redirectToRoute('app_account_index');
    }
}
