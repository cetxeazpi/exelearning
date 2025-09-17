<?php
// src/Controller/Admin/ThemeSettingsCrudController.php

namespace App\Controller\Admin;

use App\Entity\ThemeSettings;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;

final class ThemeSettingsCrudController extends AbstractCrudController
{
    /**
     * Public base path where the files will be exposed.
     */
    private const BASE_PATH = '/assets/custom';

    /**
     * Physical dir under public/ where files are stored.
     */
    private const UPLOAD_DIR = 'public/assets/custom';

    public static function getEntityFqcn(): string
    {
        return ThemeSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInPlural('Theme')
            ->setEntityLabelInSingular('Theme')
            ->setPageTitle(Crud::PAGE_EDIT, 'Theme')
            ->setPageTitle(Crud::PAGE_INDEX, 'Theme')
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
        // ImageField will write the uploaded file name into the entity property.
        // We will normalize to "/assets/custom/<filename>" in persist/update.

        $loginImage = ImageField::new('loginImagePath', 'Login image')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('login-image-[timestamp].[extension]')
            ->setRequired(false);

        $loginLogo = ImageField::new('loginLogoPath', 'Login logo')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('login-logo-[timestamp].[extension]')
            ->setRequired(false);

        $favicon = ImageField::new('faviconPath', 'Favicon (.ico or .png)')
            ->setBasePath(self::BASE_PATH)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setUploadedFileNamePattern('favicon-[timestamp].[extension]')
            ->setRequired(false);

        // Unmapped checkboxes to allow clearing current images safely
        $removeLoginImage = BooleanField::new('removeLoginImage', 'Remove current login image')
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms();

        $removeLoginLogo = BooleanField::new('removeLoginLogo', 'Remove current login logo')
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms();

        $removeFavicon = BooleanField::new('removeFavicon', 'Remove current favicon')
            ->setFormTypeOption('mapped', false)
            ->onlyOnForms();

        $updatedAt = DateTimeField::new('updatedAt')->onlyOnIndex();

        return [
            $loginImage,
            $removeLoginImage,
            $loginLogo,
            $removeLoginLogo,
            $favicon,
            $removeFavicon,
            $updatedAt,
        ];
    }

    /**
     * Normalize stored values to "/assets/custom/<file>" so existing templates keep working.
     */
    private function normalizePath(?string $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        // If EA gave us only a filename (common case), prefix base path.
        if (!str_starts_with($value, self::BASE_PATH . '/')) {
            return rtrim(self::BASE_PATH, '/') . '/' . ltrim($value, '/');
        }

        return $value;
    }

    /**
     * Remove a file from "public/assets/custom" if it belongs there.
     */
    private function safeUnlink(?string $publicPath): void
    {
        if (!$publicPath || !str_starts_with($publicPath, self::BASE_PATH . '/')) {
            return;
        }

        $absolute = $this->getParameter('kernel.project_dir') . '/public' . $publicPath;
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof ThemeSettings) {
            // Normalize paths after EA saved file names
            $entityInstance->setLoginImagePath($this->normalizePath($entityInstance->getLoginImagePath()));
            $entityInstance->setLoginLogoPath($this->normalizePath($entityInstance->getLoginLogoPath()));
            $entityInstance->setFaviconPath($this->normalizePath($entityInstance->getFaviconPath()));

            $entityInstance->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
            $entityInstance->setUpdatedAt(new \DateTimeImmutable());

            // Handle removals on create (rare, but safe)
            $this->handleRemovals($entityInstance);
        }

        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof ThemeSettings) {
            // Normalize paths after EA saved file names
            $entityInstance->setLoginImagePath($this->normalizePath($entityInstance->getLoginImagePath()));
            $entityInstance->setLoginLogoPath($this->normalizePath($entityInstance->getLoginLogoPath()));
            $entityInstance->setFaviconPath($this->normalizePath($entityInstance->getFaviconPath()));

            $entityInstance->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
            $entityInstance->setUpdatedAt(new \DateTimeImmutable());

            // Handle removals toggled in form
            $this->handleRemovals($entityInstance);
        }

        parent::updateEntity($em, $entityInstance);
    }

    /**
     * Read unmapped boolean checkboxes from the form and remove files if requested.
     */
    private function handleRemovals(ThemeSettings $settings): void
    {
        $form = $this->getContext()?->getCrud()?->getForm();
        if (null === $form) {
            return;
        }

        if (true === (bool) $form->get('removeLoginImage')->getData()) {
            $this->safeUnlink($settings->getLoginImagePath());
            $settings->setLoginImagePath(null);
        }

        if (true === (bool) $form->get('removeLoginLogo')->getData()) {
            $this->safeUnlink($settings->getLoginLogoPath());
            $settings->setLoginLogoPath(null);
        }

        if (true === (bool) $form->get('removeFavicon')->getData()) {
            $this->safeUnlink($settings->getFaviconPath());
            $settings->setFaviconPath(null);
        }
    }
}
