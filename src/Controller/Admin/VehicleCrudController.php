<?php

namespace App\Controller\Admin;

use App\Entity\Vehicle;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class VehicleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Vehicle::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('owner', 'Propriétaire'),
            TextField::new('brand', 'Marque'),
            TextField::new('model', 'Modèle'),
            TextField::new('color', 'Couleur'),
            TextField::new('licensePlate', 'Immatriculation'),
            IntegerField::new('seats', 'Nombre de places'),
            BooleanField::new('airConditioned', 'Climatisation'),
        ];
    }
}
