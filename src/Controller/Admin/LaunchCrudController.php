<?php

namespace App\Controller\Admin;

use App\Entity\Launch;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class LaunchCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Launch::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            AssociationField::new('user', 'Utilisateur'),
            AssociationField::new('breathingExercise', 'Exercice'),
            DateTimeField::new('launchDate', 'Date de session'),
            IntegerField::new('cycleCount', 'Cycles'),
            TimeField::new('totalDuration', 'Duree totale'),
        ];
    }
}
