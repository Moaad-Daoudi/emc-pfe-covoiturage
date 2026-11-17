<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Entity\City;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {
    }

    public function index(): Response
    {
        // By default redirect to Trips list
        $url = $this->adminUrlGenerator
            ->setController(TripCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('🚗 SafarCar — Back-Office')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><text y=%221.2em%22 font-size=%2296%22>🚗</text></svg>');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-tachometer-alt');

        yield MenuItem::section('Gestion Covoiturage');
        yield MenuItem::linkToCrud('Trajets', 'fa fa-route', Trip::class);
        yield MenuItem::linkToCrud('Réservations', 'fa fa-ticket', Booking::class);
        yield MenuItem::linkToCrud('Avis & Évaluations', 'fa fa-star', Review::class);

        yield MenuItem::section('Utilisateurs & Flotte');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Véhicules', 'fa fa-car', Vehicle::class);

        yield MenuItem::section('Territoire');
        yield MenuItem::linkToCrud('Villes & Gares', 'fa fa-city', City::class);

        yield MenuItem::section('Navigation');
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');
    }
}
