<?php
// src/Controller/Admin/AdditionalHtmlCrudController.php

namespace App\Controller\Admin;

use App\Entity\AdditionalHtml;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Doctrine\ORM\EntityManagerInterface;

final class AdditionalHtmlCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AdditionalHtml::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Additional HTML')
            ->setEntityLabelInSingular('Additional HTML')
            ->setPageTitle(Crud::PAGE_EDIT, 'Additional HTML')
            ->setPageTitle(Crud::PAGE_INDEX, 'Additional HTML')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Singleton: disable NEW and DELETE
        return $actions
            ->disable(Action::NEW, Action::DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextareaField::new('headHtml', 'Within HEAD')
            ->setRequired(false)
            ->setFormTypeOption('attr', ['rows' => 8]);

        yield TextareaField::new('topOfBodyHtml', 'When BODY is opened')
            ->setRequired(false)
            ->setFormTypeOption('attr', ['rows' => 8]);

        yield TextareaField::new('footerHtml', 'Before BODY is closed')
            ->setRequired(false)
            ->setFormTypeOption('attr', ['rows' => 8]);

        yield TextField::new('updatedBy')
            ->onlyOnIndex();

        yield DateTimeField::new('updatedAt')
            ->onlyOnIndex();
    }

    /**
     * Ensure updatedBy is tracked when editing.
     */
    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof AdditionalHtml) {
            $entityInstance->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
        }

        parent::updateEntity($em, $entityInstance);
    }
}
