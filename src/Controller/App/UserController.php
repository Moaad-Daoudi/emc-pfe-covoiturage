<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Entity\User;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    /**
     * Screen S08: Profil Public & Avis Vérifiés (/users/{id})
     */
    #[Route('/users/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(
        int $id,
        UserRepository $userRepository,
        ReviewRepository $reviewRepository,
        VehicleRepository $vehicleRepository
    ): Response {
        $user = $userRepository->find($id);
        if ($user === null) {
            throw new NotFoundHttpException(sprintf('Membre #%d introuvable.', $id));
        }

        // Ratings & Reviews
        $stats = $reviewRepository->getRatingStatsForUser($user);
        $reviews = $reviewRepository->findBy(['target' => $user], ['createdAt' => 'DESC']);
        $vehicles = $vehicleRepository->findBy(['owner' => $user]);

        return $this->render('app/user/show.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'reviews' => $reviews,
            'vehicles' => $vehicles,
        ]);
    }
}
