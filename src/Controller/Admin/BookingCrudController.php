<?php

namespace App\Controller\Admin;

use App\Domain\Enum\BookingStatus;
use App\Entity\Booking;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class BookingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Booking::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('passenger', 'Passager'),
            AssociationField::new('trip', 'Trajet'),
            AssociationField::new('boardingStop', 'Arrêt de montée'),
            AssociationField::new('alightingStop', 'Arrêt de descente'),
            IntegerField::new('seatsBooked', 'Places réservées'),
            IntegerField::new('totalPrice', 'Prix payé (MAD)'),
            ChoiceField::new('status', 'Statut')->setChoices([
                'Confirmée' => BookingStatus::CONFIRMED,
                'Annulée par passager' => BookingStatus::CANCELLED_PASSENGER,
                'Annulée par conducteur' => BookingStatus::CANCELLED_DRIVER,
            ]),
            DateTimeField::new('createdAt', 'Réservé le')->hideOnForm(),
        ];
    }
}
