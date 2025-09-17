<?php

// src/Controller/Admin/SystemPreferencesCrudController.php

namespace App\Controller\Admin;

use App\Entity\net\exelearning\Entity\SystemPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

final class SystemPreferencesCrudController extends AbstractCrudController
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public static function getEntityFqcn(): string
    {
        return SystemPreferences::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $prefix = $this->requests->getCurrentRequest()?->query->get('prefix');

        return $crud
            ->setEntityLabelInSingular('Preference')
            ->setEntityLabelInPlural('Preferences')
            ->setPageTitle(Crud::PAGE_INDEX, $prefix ? sprintf('Preferences: %s*', $prefix) : 'Preferences')
            ->setDefaultSort(['key' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        // En estas secciones “core” normalmente no se crean ni borran claves
        return $actions->disable(Action::NEW, Action::DELETE);
    }

    // public function configureFields(string $pageName): iterable
    // {
    //     yield IdField::new('id')->onlyOnIndex();

    //     // En el formulario, la key y el type las dejamos de solo lectura
    //     yield TextField::new('key', 'Key')->onlyOnIndex();
    //     yield TextField::new('key', 'Key')->onlyOnForms()->setFormTypeOption('disabled', true);

    //     yield TextareaField::new('value', 'Value')
    //         ->setFormTypeOption('attr', ['rows' => 8]);

    //     yield TextField::new('type', 'Type')->hideOnIndex()->setFormTypeOption('disabled', true);

    //     yield DateTimeField::new('updatedAt')->onlyOnIndex();
    // }

    /**
     * Firma correcta en EA 4.x
     * Filtra por prefijo si viene ?prefix=additional_html. / theme. / maintenance.
     */
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $prefix = $this->requests->getCurrentRequest()?->query->get('prefix');
        if ($prefix) {
            // EasyAdmin usa alias "entity" por defecto
            $qb->andWhere('entity.key LIKE :pfx')->setParameter('pfx', $prefix.'%');
        }

        return $qb;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        // Key y type solo lectura
        yield TextField::new('key', 'Key')->onlyOnIndex();
        yield TextField::new('key', 'Key')->onlyOnForms()->setFormTypeOption('disabled', true);
        yield TextField::new('type', 'Type')->onlyOnForms()->setFormTypeOption('disabled', true);

        $instance = $this->getContext()?->getEntity()?->getInstance();
        $type = \is_object($instance) ? ($instance->getType() ?? 'string') : 'string';

        // Campo VALUE según "type"
        if (Crud::PAGE_INDEX === $pageName) {
            // Render all types via a single template that adapts per-row
            yield TextField::new('value', 'Value')
                ->setTemplatePath('admin/fields/pref_value_index.html.twig');
        } else {
            // En formularios, widgets adecuados
            yield from match ($type) {
                'bool' => [BooleanField::new('valueBool', 'Value')->renderAsSwitch(false)],
                'int' => [IntegerField::new('valueInt', 'Value')],
                'float' => [NumberField::new('valueFloat', 'Value')],
                'date' => [DateField::new('valueDate', 'Value')->setFormTypeOption('widget', 'single_text')],
                'datetime' => [DateTimeField::new('valueDateTime', 'Value')->setFormTypeOption('widget', 'single_text')],
                'html' => [TextareaField::new('value', 'Value')->setFormTypeOption('attr', ['rows' => 10, 'class' => 'ea-code-editor', 'data-language' => 'html'])],
                'file' => [
                    TextField::new('value', 'Current path')->setFormTypeOption('disabled', true)->onlyOnForms(),
                    TextField::new('upload', 'Upload file')
                        ->setFormType(FileType::class)
                        ->setFormTypeOptions(['mapped' => false, 'required' => false])
                        ->onlyOnForms(),
                    BooleanField::new('removeFile', 'Remove current')
                        ->renderAsSwitch(false)
                        ->setFormTypeOption('mapped', false)
                        ->onlyOnForms(),
                ],
                default => [TextField::new('value', 'Value')],
            };
        }

        yield DateTimeField::new('updatedAt')->onlyOnIndex();
    }

    // Guardamos fichero cuando type === 'file'
    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->handleFileUpload($entityInstance);
        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        $this->handleFileUpload($entityInstance);
        parent::updateEntity($em, $entityInstance);
    }

    private function handleFileUpload(object $pref): void
    {
        if (!$pref instanceof SystemPreferences) {
            return;
        }
        if ('file' !== $pref->getType()) {
            return;
        }

        $ctx = $this->getContext();
        if (!$ctx) {
            return;
        }

        $request = $ctx->getRequest();
        /** @var UploadedFile|null $file */
        $file = $this->findValueByKey($request->files->all(), 'upload');
        $remove = (bool) $this->findValueByKey($request->request->all(), 'removeFile');

        $projectDir = $this->getParameter('kernel.project_dir');
        $filesDir = rtrim((string) $this->getParameter('filesdir'), '/');
        $storageDir = $filesDir.'/system_preferences_files';
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        // Ensure public symlink exists so files are served without PHP
        $publicLink = $projectDir.'/public/system_prefs';
        if (!is_link($publicLink) && !is_dir($publicLink)) {
            @symlink($storageDir, $publicLink);
        }

        if ($remove) {
            $this->deleteIfLocal($pref->getValue(), $storageDir);
            $pref->setValue(null);
        }

        if ($file instanceof UploadedFile) {
            $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
            $slug = preg_replace('/[^a-z0-9]+/i', '-', $pref->getKey()) ?: 'file';
            try {
                $rand = substr(bin2hex(random_bytes(4)), 0, 8);
            } catch (\Throwable) {
                $rand = dechex(mt_rand());
            }
            $filename = sprintf('%s-%s-%d.%s', $slug, $rand, time(), $ext);
            $file->move($storageDir, $filename);
            $pref->setValue('/system_prefs/'.$filename); // stored relative web path via symlink
        }
    }

    private function deleteIfLocal(?string $path, string $storageDir): void
    {
        if (!$path || !str_starts_with($path, '/system_prefs/')) {
            return;
        }
        // prevent directory traversal; only allow flat filenames
        $base = basename($path);
        $abs = rtrim($storageDir, '/').'/'.$base;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    private function findValueByKey(array $data, string $key): mixed
    {
        foreach ($data as $k => $v) {
            if ($k === $key) {
                return $v;
            }
            if (\is_array($v)) {
                $found = $this->findValueByKey($v, $key);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
