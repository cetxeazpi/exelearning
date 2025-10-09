<?php
namespace App\Tests\Command;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use App\Command\net\exelearning\Command\ElpExportH5pCommand;
use App\Entity\net\exelearning\Entity\User;
use App\Repository\net\exelearning\Repository\UserRepository;
use App\Tests\Utils\H5PFrameworkStub;
use H5PCore;
use H5PValidator;
use PHPUnit\Framework\Attributes\DataProvider;

class ElpExportH5pCommandTest extends KernelTestCase
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

    #[DataProvider('elpFileProvider')]
    public function testExportElpToCoursePresentation(string $fixture): void
    {
        $fixturesDir = __DIR__.'/../Fixtures';
        if (!is_dir($fixturesDir)) {
            mkdir($fixturesDir, 0777, true);
        }
        $inputFile = $fixturesDir.'/'.$fixture;
        if (!file_exists($inputFile)) {
            $this->markTestSkipped('Fixture not present: '.$fixture);
            return;
        }
        $inputFile = realpath($inputFile);
        $outputDir = sys_get_temp_dir().'/elp_export_h5p_'.uniqid();
        mkdir($outputDir, 0755, true);
        $this->tempPaths[] = $outputDir;
        $this->assertFileExists($inputFile);

        // Use default Column layout

        $this->commandTester->execute([
            'command' => 'elp:export-h5p',
            'input' => $inputFile,
            'output' => $outputDir,
            '--debug' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
        $files = glob($outputDir.'/*.h5p');
        $this->assertNotEmpty($files, 'No h5p file generated');
        $h5p = $files[0];

        // Validate Column structure
        $zip = new \ZipArchive();
        $this->assertTrue(true === $zip->open($h5p));
        $this->assertNotFalse($zip->locateName('h5p.json'), 'Missing h5p.json');
        $this->assertNotFalse($zip->locateName('content/content.json'), 'Missing content/content.json');
        $h5pJson = json_decode($zip->getFromName('h5p.json'), true);
        $this->assertIsArray($h5pJson);
        $this->assertSame('H5P.Column', $h5pJson['mainLibrary'] ?? null, 'mainLibrary must be H5P.Column');
        $this->assertSame('U', $h5pJson['license'] ?? null);
        $this->assertContains('iframe', $h5pJson['embedTypes'] ?? []);
        $contentJson = json_decode($zip->getFromName('content/content.json'), true);
        $this->assertIsArray($contentJson);
        $this->assertArrayHasKey('content', $contentJson);
        $items = $contentJson['content'];
        $this->assertNotEmpty($items);
        $foundNode1 = false; $foundNode2 = false; $foundLorem = false;
        foreach ($items as $entry) {
            $lib = $entry['content']['library'] ?? '';
            $this->assertSame('H5P.AdvancedText 1.1', $lib);
            $text = $entry['content']['params']['text'] ?? '';
            $this->assertIsString($text);
            if (stripos($text, 'node 1') !== false) { $foundNode1 = true; }
            if (stripos($text, 'node 2') !== false) { $foundNode2 = true; }
            if (stripos($text, 'lorem ipsum') !== false) { $foundLorem = true; }
        }
        $this->assertTrue($foundNode1, 'Expected Column text to contain "Node 1"');
        $this->assertTrue($foundNode2, 'Expected Column text to contain "Node 2"');
        $this->assertTrue($foundLorem, 'Expected Column text to contain "Lorem Ipsum"');
        $zip->close();
    }

    public static function elpFileProvider(): array
    {
        return [
            ['basic-example.elp'],
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->tempPaths as $path) {
            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }
        }
    }

    // No auto-generation here; basic-example.elp must exist
}
