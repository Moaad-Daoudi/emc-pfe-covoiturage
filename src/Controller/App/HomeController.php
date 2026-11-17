<?php

namespace App\Controller\App;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('app/home/index.html.twig', [
            'popularRoutes' => [
                ['from' => 'Agadir', 'to' => 'Marrakech', 'duration' => '2h 45m', 'price' => 90],
                ['from' => 'Marrakech', 'to' => 'Casablanca', 'duration' => '3h 15m', 'price' => 110],
                ['from' => 'Agadir', 'to' => 'Essaouira', 'duration' => '2h 30m', 'price' => 70],
                ['from' => 'Casablanca', 'to' => 'Rabat', 'duration' => '1h 00m', 'price' => 40],
            ],
        ]);
    }
}
