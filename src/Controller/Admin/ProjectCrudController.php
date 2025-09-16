<?php

namespace App\Controller\Admin;

use App\Entity\net\exelearning\Entity\OdeFiles;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProjectCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OdeFiles::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Project')
            ->setEntityLabelInPlural('Projects')
            ->setPageTitle(Crud::PAGE_INDEX, 'Projects')
            ->setDefaultSort(['updatedAt' => 'DESC'])
            ->setSearchFields(['odeId', 'title', 'versionName', 'fileName', 'user']);
    }

    public function configureActions(Actions $actions): Actions
    {
        // Read-only except delete: disable NEW and EDIT, allow DELETE
        return $actions
            ->disable(Action::NEW, Action::EDIT);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('odeId', 'Project ID');
        yield TextField::new('title', 'Title');
        yield TextField::new('versionName', 'Version')->onlyOnIndex();
        yield TextField::new('fileName', 'File')
            ->setTemplatePath('admin/fields/truncate_ellipsis.html.twig')
            ->onlyOnIndex();
        yield TextField::new('fileType', 'Type')->onlyOnIndex();
        yield IntegerField::new('size', 'Size (bytes)')->onlyOnIndex();
        yield BooleanField::new('isManualSave', 'Manual Save')->onlyOnIndex();
        yield TextField::new('user', 'Owner')->onlyOnIndex();
        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }
}
