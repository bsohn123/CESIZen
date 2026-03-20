<?php

namespace App\Controller\Admin;

use App\Entity\BreathingExercise;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class BreathingExerciseCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BreathingExercise::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name', 'Nom'),
            IntegerField::new('inhaleDuration', 'Inspiration (s)')
                ->setHelp('Duree de la phase d\'inspiration en secondes'),
            IntegerField::new('holdDuration', 'Apnee (s)')
                ->setHelp('Duree de l\'apnee en secondes (0 pour aucune apnee)'),
            IntegerField::new('exhaleDuration', 'Expiration (s)')
                ->setHelp('Duree de la phase d\'expiration en secondes'),
            BooleanField::new('active', 'Actif'),
        ];
    }
}
