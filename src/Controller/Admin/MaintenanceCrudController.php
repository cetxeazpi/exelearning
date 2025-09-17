<?php 

// namespace App\Controller\Admin;

// use App\Entity\net\exelearning\Entity\OdeFiles;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
// use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
// use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
// use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
// use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

// class MaintenanceCrudController extends AbstractCrudController
// {
//     // ... getEntityFqcn() apunta a Maintenance::class

//     public function __construct(private readonly EntityManagerInterface $em, private readonly RequestStack $requestStack)
//     {
//     }

//     public function configureActions(Actions $actions): Actions
//     {
//         // Solo dejamos la acción de editar y la configuramos
//         $editAction = Action::new('edit', 'Settings', 'fa fa-cogs')
//             ->linkToCrudAction(Action::EDIT);

//         return $actions
//             ->remove(Crud::PAGE_INDEX, Action::NEW)
//             ->remove(Crud::PAGE_INDEX, Action::DELETE)
//             ->remove(Crud::PAGE_INDEX, Action::EDIT)
//             ->add(Crud::PAGE_INDEX, $editAction);
//     }

//     public function configureCrud(Crud $crud): Crud
//     {
//         // Redirigimos la página principal (index) a la de edición del único registro
//         $singleEntityId = 1; // Suponiendo que el ID del registro de settings es siempre 1
//         $url = $this->container->get(AdminUrlGenerator::class)
//             ->setController(self::class)
//             ->setAction(Action::EDIT)
//             ->setEntityId($singleEntityId)
//             ->generateUrl();

//         return $crud->setRedirectUrls([Action::INDEX => $url]);
//     }
    
//     public function configureFields(string $pageName): iterable
//     {
//         yield BooleanField::new('enabled', 'Enable maintenance');
//         yield TextField::new('message', 'Public message');
//         yield DateTimeField::new('scheduledEndAt', 'Scheduled end time');
//     }
// }

// src/Controller/Admin/MaintenanceCrudController.php
namespace App\Controller\Admin;

use App\Entity\Maintenance;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;

final class MaintenanceCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return Maintenance::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Maintenance')
            ->setEntityLabelInPlural('Maintenance')
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $a) => $a->setLabel('Edit'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield BooleanField::new('enabled');
        yield TextField::new('message')->setRequired(false);
        yield DateTimeField::new('scheduledEndAt')->setRequired(false)->setFormTypeOption('widget', 'single_text');
    }
}
