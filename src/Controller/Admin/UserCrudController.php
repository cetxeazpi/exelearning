<?php

namespace App\Controller\Admin;

use App\Entity\net\exelearning\Entity\User;
use App\Service\UserManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface as SymfonyUserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserManager $userManager,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('admin.users.title')
            ->setEntityLabelInPlural('admin.users.title')
            ->setPageTitle(Crud::PAGE_INDEX, 'admin.users.title')
            ->setSearchFields(['email', 'userId'])
            // Workaround: override index template to avoid EA calling setController(null) in vendor template on PHP 8.4
            // We provide a minimal index that renders without 500s while we investigate upstream.
            ->overrideTemplates([
                'crud/index' => 'admin/easyadmin/users_index_safe.html.twig',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield EmailField::new('email', 'admin.users.email');
        yield TextField::new('userId', 'admin.users.username');
        yield TextField::new('plainPassword', 'admin.users.new')
            ->setFormType(PasswordType::class)
            ->onlyOnForms()
            ->setRequired(false)
            ->setFormTypeOption('mapped', false)
            ->setHelp('admin.users.password_help');

        yield ChoiceField::new('roles', 'admin.users.roles')
            ->hideOnIndex()
            ->setChoices([
                'Admin' => 'ROLE_ADMIN',
                'User' => 'ROLE_USER',
                'Guest' => 'ROLE_GUEST',
            ])
            ->allowMultipleChoices(true)
            ->renderExpanded(false);

        yield ArrayField::new('roles', 'admin.users.roles')->onlyOnIndex();

        yield BooleanField::new('isActive', 'admin.users.enabled');

        yield DateTimeField::new('createdAt')->onlyOnIndex();
        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions; // default actions are fine (new, edit, delete)
    }

    /**
     * Hook executed on persist to hash password and normalize roles.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $this->normalizeRoles($entityInstance);
        $this->applyPasswordFromRequest($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Hook executed on update to hash password and normalize roles.
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        $this->assertNotRemovingOwnAdmin($entityInstance);
        $this->normalizeRoles($entityInstance);
        $this->applyPasswordFromRequest($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function applyPasswordFromRequest(User $user): void
    {
        $context = $this->getContext();
        if (null === $context) {
            return;
        }

        $data = $context->getRequest()->request->all();
        $plainPassword = $this->findValueByKey($data, 'plainPassword');
        $this->userManager->applyPlainPassword($user, is_string($plainPassword) ? $plainPassword : null);
    }

    /**
     * Recursively search an array for a given key and return its value.
     * Returns null if not found.
     */
    private function findValueByKey(array $data, string $key): mixed
    {
        foreach ($data as $k => $v) {
            if ($k === $key) {
                return $v;
            }
            if (is_array($v)) {
                $found = $this->findValueByKey($v, $key);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function normalizeRoles(User $user): void
    {
        $roles = array_values(array_unique(array_map('strval', $user->getRoles())));
        // Only allow known roles
        $allowed = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_GUEST'];
        $roles = array_values(array_intersect($roles, $allowed));
        sort($roles);
        $user->setRoles($roles);
    }

    private function assertNotRemovingOwnAdmin(User $user): void
    {
        $token = $this->tokenStorage->getToken();
        $currentUser = $token?->getUser();
        if (!$currentUser instanceof SymfonyUserInterface) {
            return;
        }

        // Compare by identifier (email)
        if ($currentUser->getUserIdentifier() !== $user->getUserIdentifier()) {
            return;
        }

        // If the edited user is myself, ensure ROLE_ADMIN is present
        if (!\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw new \RuntimeException($this->translator->trans('admin.users.cannot_remove_last_admin'));
        }
    }
}
