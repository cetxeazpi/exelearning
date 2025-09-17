<?php
// src/Controller/Admin/SettingsRedirectController.php
namespace App\Controller\Admin;

use App\Entity\AdditionalHtml;
use App\Entity\ThemeSettings;
use App\Entity\Maintenance;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Small helper controller to ensure singleton rows exist and redirect to EA edit page.
 */
final class SettingsRedirectController extends AbstractController
{
    #[Route('/admin/settings/maintenance', name: 'admin_settings_maintenance')]
    public function maintenance(EntityManagerInterface $em, AdminUrlGenerator $urls): RedirectResponse
    {
        $repo = $em->getRepository(Maintenance::class);
        $entity = $repo->findOneBy([]) ?? new Maintenance();
        if (null === $entity->getId()) { $em->persist($entity); $em->flush(); }

        $url = $urls->setController(MaintenanceCrudController::class)
            ->setAction('edit')
            ->setEntityId($entity->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    #[Route('/admin/settings/additional-html', name: 'admin_settings_additional_html', methods: ['GET'])]
    public function additionalHtml(EntityManagerInterface $em, AdminUrlGenerator $urls): RedirectResponse
    {
        $entity = $em->getRepository(AdditionalHtml::class)->findOneBy([]) ?? new AdditionalHtml();
        if (null === $entity->getId()) {
            $em->persist($entity);
            $em->flush();
        }

        $url = $urls->setController(AdditionalHtmlCrudController::class)
            ->setAction('edit')
            ->setEntityId($entity->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    #[Route('/admin/settings/theme', name: 'admin_settings_theme', methods: ['GET'])]
    public function theme(EntityManagerInterface $em, AdminUrlGenerator $urls): RedirectResponse
    {
        $entity = $em->getRepository(ThemeSettings::class)->findOneBy([]) ?? new ThemeSettings();
        if (null === $entity->getId()) {
            $em->persist($entity);
            $em->flush();
        }

        $url = $urls->setController(ThemeSettingsCrudController::class)
            ->setAction('edit')
            ->setEntityId($entity->getId())
            ->generateUrl();

        return $this->redirect($url);
    }
}
