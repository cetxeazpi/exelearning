<?php

namespace App\Controller\Admin;

use App\Entity\AdditionalHtml;
use App\Entity\Maintenance;
use App\Entity\net\exelearning\Entity\User;
use App\Entity\ThemeSettings;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractDashboardController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RequestStack $requestStack)
    {
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // If EasyAdmin query params are present, delegate to EA to render CRUD pages
        $request = $this->requestStack->getCurrentRequest();
        if ($request && ($request->query->has('crudControllerFqcn') || $request->query->has('routeName'))) {
            return parent::index();
        }

        // Otherwise, render our custom dashboard summary
        $totalUsers = (int) $this->em->createQuery(
            'SELECT COUNT(u.id) FROM App\\Entity\\net\\exelearning\\Entity\\User u'
        )->getSingleScalarResult();

        $totalProjects = (int) $this->em->createQuery(
            'SELECT COUNT(DISTINCT o.odeId) FROM App\\Entity\\net\\exelearning\\Entity\\OdeFiles o'
        )->getSingleScalarResult();

        return $this->render('admin/dashboard/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalProjects' => $totalProjects,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('admin.menu.dashboard');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('admin.menu.dashboard', 'fa fa-home');
        yield MenuItem::section('admin.menu.users');
        // Handled by EasyAdmin: URLs with query string
        yield MenuItem::linkToCrud('admin.menu.users', 'fa fa-users', User::class);

        yield MenuItem::section('Projects');
        yield MenuItem::linkToCrud('Projects', 'fa fa-folder-open', \App\Entity\net\exelearning\Entity\OdeFiles::class)
            ->setController(ProjectCrudController::class);

        yield MenuItem::section('Preferences');

        yield MenuItem::linkToCrud('Additional HTML', 'fa fa-code', \App\Entity\net\exelearning\Entity\SystemPreferences::class)
            ->setController(SystemPreferencesCrudController::class)
            ->setAction('index')
            ->setQueryParameter('prefix', 'additional_html.');

        yield MenuItem::linkToCrud('Theme', 'fa fa-image', \App\Entity\net\exelearning\Entity\SystemPreferences::class)
            ->setController(SystemPreferencesCrudController::class)
            ->setAction('index')
            ->setQueryParameter('prefix', 'theme.');

        yield MenuItem::linkToCrud('Maintenance', 'fa fa-tools', \App\Entity\net\exelearning\Entity\SystemPreferences::class)
            ->setController(SystemPreferencesCrudController::class)
            ->setAction('index')
            ->setQueryParameter('prefix', 'maintenance.');
    }

    // #[Route('/admin/additional-html', name: 'admin_additional_html', methods: ['GET', 'POST'])]
    // public function additionalHtml(Request $request): Response
    // {
    //     $settings = $this->em->getRepository(AdditionalHtml::class)->findOneBy([]) ?? new AdditionalHtml();
    //     if (null === $settings->getId()) {
    //         $this->em->persist($settings);
    //         $this->em->flush();
    //     }

    //     $form = $this->createFormBuilder($settings)
    //         ->add('headHtml', TextareaType::class, [
    //             'required' => false,
    //             'label' => 'Within HEAD',
    //             'attr' => ['rows' => 8],
    //         ])
    //         ->add('topOfBodyHtml', TextareaType::class, [
    //             'required' => false,
    //             'label' => 'When BODY is opened',
    //             'attr' => ['rows' => 8],
    //         ])
    //         ->add('footerHtml', TextareaType::class, [
    //             'required' => false,
    //             'label' => 'Before BODY is closed',
    //             'attr' => ['rows' => 8],
    //         ])
    //         ->getForm();

    //     $form->handleRequest($request);
    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $settings->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
    //         $this->em->flush();
    //         $this->addFlash('success', 'Additional HTML saved');

    //         return $this->redirectToRoute('admin_additional_html');
    //     }

    //     return $this->render('admin/additional_html/index.html.twig', [
    //         'form' => $form->createView(),
    //     ]);
    // }

    // #[Route('/admin/maintenance', name: 'admin_maintenance', methods: ['GET', 'POST'])]
    // public function maintenance(Request $request, EntityManagerInterface $em): Response
    // {
    //     $maintenance = $em->getRepository(Maintenance::class)->findOneBy([]) ?? new Maintenance();
    //     if (null === $maintenance->getId()) {
    //         $em->persist($maintenance);
    //         $em->flush();
    //     }

    //     $form = $this->createFormBuilder($maintenance)
    //         ->add('enabled', CheckboxType::class, [
    //             'required' => false,
    //             'label' => 'admin.maintenance.toggle_on',
    //         ])
    //         ->add('message', TextType::class, [
    //             'required' => false,
    //             'label' => 'admin.maintenance.message_label',
    //         ])
    //         ->add('scheduledEndAt', DateTimeType::class, [
    //             'required' => false,
    //             'label' => 'admin.maintenance.scheduled_end',
    //             'widget' => 'single_text',
    //         ])
    //         ->getForm();

    //     $form->handleRequest($request);
    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $maintenance->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
    //         $em->flush();
    //         $this->addFlash('success', 'admin.maintenance.saved');

    //         return $this->redirectToRoute('admin_maintenance');
    //     }

    //     return $this->render('admin/maintenance/index.html.twig', [
    //         'maintenance' => $maintenance,
    //         'form' => $form->createView(),
    //     ]);
    // }

    // #[Route('/admin/theme', name: 'admin_theme', methods: ['GET', 'POST'])]
    // public function theme(Request $request): Response
    // {
    //     $settings = $this->em->getRepository(ThemeSettings::class)->findOneBy([]) ?? new ThemeSettings();
    //     if (null === $settings->getId()) {
    //         $this->em->persist($settings);
    //         $this->em->flush();
    //     }

    //     $formBuilder = $this->createFormBuilder()
    //         ->add('loginImage', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
    //             'required' => false,
    //             'mapped' => false,
    //             'label' => 'Login image',
    //             'help' => 'Recommended size: 1280x1920',
    //         ])
    //         ->add('removeLoginImage', CheckboxType::class, [
    //             'required' => false,
    //             'mapped' => false,
    //             'label' => 'Remove current login image',
    //         ])
    //         ->add('loginLogo', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
    //             'required' => false,
    //             'mapped' => false,
    //             'label' => 'Login logo',
    //             'help' => 'Recommended size: 621x562',
    //         ])
    //         ->add('removeLoginLogo', CheckboxType::class, [
    //             'required' => false,
    //             'mapped' => false,
    //             'label' => 'Remove current login logo',
    //         ])
    //         ->add('favicon', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
    //             'required' => false,
    //             'mapped' => false,
    //             'label' => 'Favicon (.ico or .png)',
    //             'help' => 'Recommended size: 48x48',
    //         ]);
    //     $formBuilder->add('removeFavicon', CheckboxType::class, [
    //         'required' => false,
    //         'mapped' => false,
    //         'label' => 'Remove current favicon',
    //     ]);

    //     $form = $formBuilder->getForm();
    //     $form->handleRequest($request);

    //     if ($form->isSubmitted() && $form->isValid()) {
    //         $uploadDir = $this->getParameter('kernel.project_dir').'/public/assets/custom';
    //         if (!is_dir($uploadDir)) {
    //             @mkdir($uploadDir, 0775, true);
    //         }

    //         $map = [
    //             'loginImage' => ['prop' => 'setLoginImagePath', 'name' => 'login-image'],
    //             'loginLogo' => ['prop' => 'setLoginLogoPath', 'name' => 'login-logo'],
    //             'favicon' => ['prop' => 'setFaviconPath', 'name' => 'favicon'],
    //         ];

    //         foreach ($map as $key => $cfg) {
    //             $file = $form->get($key)->getData();
    //             if ($file) {
    //                 $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'png');
    //                 $filename = $cfg['name'].'.'.$ext;
    //                 $file->move($uploadDir, $filename);
    //                 $publicPath = '/assets/custom/'.$filename;
    //                 $settings->{$cfg['prop']}($publicPath);
    //             }
    //         }

    //         // Handle removals (only delete files saved under /assets/custom for safety)
    //         $removals = [
    //             'removeLoginImage' => ['getter' => 'getLoginImagePath', 'setter' => 'setLoginImagePath'],
    //             'removeLoginLogo' => ['getter' => 'getLoginLogoPath', 'setter' => 'setLoginLogoPath'],
    //             'removeFavicon' => ['getter' => 'getFaviconPath', 'setter' => 'setFaviconPath'],
    //         ];
    //         foreach ($removals as $fieldName => $accessors) {
    //             if ($form->get($fieldName)->getData()) {
    //                 $current = $settings->{$accessors['getter']}();
    //                 if ($current && str_starts_with($current, '/assets/custom/')) {
    //                     $abs = $this->getParameter('kernel.project_dir').'/public'.$current;
    //                     if (is_file($abs)) {
    //                         @unlink($abs);
    //                     }
    //                 }
    //                 $settings->{$accessors['setter']}(null);
    //             }
    //         }

    //         $settings->setUpdatedBy($this->getUser()?->getUserIdentifier() ?? null);
    //         $settings->setUpdatedAt(new \DateTimeImmutable());
    //         $this->em->flush();

    //         $this->addFlash('success', 'Theme updated');

    //         return $this->redirectToRoute('admin_theme');
    //     }

    //     return $this->render('admin/theme/index.html.twig', [
    //         'settings' => $settings,
    //         'form' => $form->createView(),
    //     ]);
    // }
}
