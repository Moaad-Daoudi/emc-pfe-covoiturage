<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class ReviewCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Review::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('author', 'Auteur'),
            AssociationField::new('target', 'Destinataire'),
            AssociationField::new('trip', 'Trajet concerné'),
            IntegerField::new('rating', 'Note (sur 5)'),
            TextareaField::new('comment', 'Commentaire'),
            DateTimeField::new('createdAt', 'Publié le')->hideOnForm(),
        ];
    }
}
