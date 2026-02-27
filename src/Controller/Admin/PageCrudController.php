<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('title', 'Titre');
        yield TextField::new('slug', 'Slug');
        yield TextEditorField::new('content', 'Contenu')
            ->setFormTypeOption('attr', [
                'rows' => 18,
                'placeholder' => "# Titre section\n\nParagraphe...\n\n## Sous-titre\n\n- Element 1\n- Element 2",
            ])
            ->setHelp('Mise en forme simple: ligne vide = nouveau paragraphe, "# " = titre, "## " = sous-titre, "- " = liste.');
        yield ChoiceField::new('status', 'Statut')->setChoices([
            'Publiee' => 'Publiee',
            'Brouillon' => 'Brouillon',
            'Archivee' => 'Archivee',
        ]);
        yield AssociationField::new('menu', 'Menu');
        yield AssociationField::new('author', 'Auteur');
        yield DateTimeField::new('createdAt', 'Cree le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Mis a jour le')->hideOnForm();
    }
}
