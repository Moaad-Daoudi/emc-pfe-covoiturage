<?php

namespace App\Controller\Admin;

use App\Domain\Enum\TripStatus;
use App\Entity\Trip;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;

class TripCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Trip::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('driver', 'Conducteur'),
            AssociationField::new('vehicle', 'Véhicule'),
            AssociationField::new('departureCity', 'Ville de départ'),
            AssociationField::new('arrivalCity', 'Ville d’arrivée'),
            DateTimeField::new('departureAt', 'Date & heure départ'),
            ChoiceField::new('status', 'Statut')->setChoices([
                'Programmé' => TripStatus::SCHEDULED,
                'En cours' => TripStatus::ACTIVE,
                'Terminé' => TripStatus::COMPLETED,
                'Annulé' => TripStatus::CANCELLED,
            ]),
            IntegerField::new('totalSeats', 'Places totales'),
            IntegerField::new('priceTotal', 'Prix total (MAD)'),
            NumberField::new('distanceKm', 'Distance (km)')->setNumDecimals(1),
            DateTimeField::new('createdAt', 'Créé le')->hideOnForm(),
        ];
    }
}
