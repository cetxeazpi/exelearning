<?php
namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use App\Command\net\exelearning\Command\ElpExportH5pCommand;
use App\Entity\net\exelearning\Entity\User;
use App\Repository\net\exelearning\Repository\UserRepository;

class ElpExportH5pMediaTest extends KernelTestCase
{
    private CommandTester $commandTester;
    private Filesystem $filesystem;
    private array $tempPaths = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->filesystem = new Filesystem();

        // Ensure test user exists
        $repo = $container->get(UserRepository::class);
        $em = $container->get('doctrine.orm.entity_manager');
        if (!$repo->find(1)) {
            $u = new User();
            $u->setUserId(1);
            $u->setEmail('tests@example.com');
            $u->setPassword('pass');
            $u->setIsLopdAccepted(true);
            $meta = $em->getClassMetadata(User::class);
            $meta->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_NONE);
            $em->persist($u);
            $em->flush();
        }

        $application = new Application();
        $command = $container->get(ElpExportH5pCommand::class);
        $application->add($command);
        $this->commandTester = new CommandTester($command);
    }

    public function testExportOldElpModeloCreaImages(): void
    {
        // Use provided ELP fixture (do not create from scratch)
        $elp = __DIR__.'/../Fixtures/old_elp_modelocrea.elp';
        self::assertFileExists($elp, 'Fixture ELP not found');

        $out = sys_get_temp_dir().'/elp_export_h5p_media_'.uniqid();
        mkdir($out, 0777, true);
        $this->tempPaths[] = $out;

        $this->commandTester->execute([
            'command' => 'elp:export-h5p',
            'input' => $elp,
            'output' => $out,
            '--debug' => true,
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());
        $files = glob($out.'/*.h5p');
        self::assertNotEmpty($files, 'No h5p file generated');
        $h5p = $files[0];

        $zip = new \ZipArchive();
        self::assertTrue(true === $zip->open($h5p));
        // Basic structure
        self::assertNotFalse($zip->locateName('h5p.json'));
        self::assertNotFalse($zip->locateName('content/content.json'));

        // Required images expected to be packaged under content/images/
        $expectedImages = [
            'objetivoDUA.png',
            'bibliocarrito.png',
            'chess-1215079_640.jpg',
            'domino-5895698_640.jpg',
            'esqueleto-leyendo.png',
            'esqueleto-radio.png',
            'fantasmaLeyendo-2.png',
            'fondo_colabora_con_nosotros.jpg',
            'game-3061506_640.jpg',
            'icono_play_video_CREA.png',
            'pexels-ann-h-6795596.jpg',
            'piezaRompecabezas.jpg',
            'PortadaPlantilla-ModeloCREA.jpg',
            'redesAfectivas.png',
            'redesConocimiento.png',
            'redesEstrategicas.png',
        ];

        foreach ($expectedImages as $img) {
            self::assertNotFalse(
                $zip->locateName('content/images/'.$img),
                'Missing expected image in H5P: '.$img
            );
        }

        // Optionally, validate that at least one H5P.Image item exists in content.json
        $contentJson = json_decode($zip->getFromName('content/content.json'), true);
        self::assertIsArray($contentJson);
        $items = $contentJson['content'] ?? [];
        $hasImage = false;
        foreach ($items as $entry) {
            $lib = $entry['content']['library'] ?? '';
            if ($lib === 'H5P.Image 1.1') {
                $hasImage = true;
                break;
            }
        }
        self::assertTrue($hasImage, 'Expected H5P.Image items in content');

        $zip->close();
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        }
    }
}
