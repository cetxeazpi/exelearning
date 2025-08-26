<?php

namespace App\Command\net\exelearning\Command;

use App\Settings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:translations:cleanup',
    description: 'Regenerate messages.en.xlf (optional), remove obsolete trans-units, fix trailing spaces, and reorder XLFs to match English.')]
class CleanupTranslationsCommand extends Command
{
    protected static string $defaultName = 'app:translations:cleanup';

    private $projectDir;

    public function __construct(ParameterBagInterface $params)
    {
        $this->projectDir = $params->get('kernel.project_dir');
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Extract translations and post-process XLF files by cleaning up obsolete strings.')
            ->setHelp('This command extracts translations for locale "en" and cleans the specified XLF file removing obsolete strings and reorder XLFs to match English.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->note('Tip: for development, keep a single locale in Settings::LOCALES to speed up extraction/cleanup.');
        $io->section('Processing translation of reference file: en');
        $referenceFile = $this->projectDir.'/translations/messages.en.xlf';
        $totalRemoved = 0;
        $totalKept = 0;
        $changedFiles = 0;

        // Run translation:extract command
        $process = new Process(['php', 'bin/console', 'translation:extract', '--domain=messages', '--force', 'en']);
        // Increasing the process time to 200 seconds for slow machines
        $process->setTimeout(200);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->error('Error extracting translations for reference file: en');

            return Command::FAILURE;
        }

        $io->success('Translations extracted successfully for reference file: en.');

        // Handle potential renaming of +intl-icu files
        $this->handleIntlIcuFile('en', 'messages', $io);

        // Clean the specific .xlf file after extraction
        $this->cleanXlfFile('en', 'messages', $io);

        // Build valid keys and order from English
        [$validKeys, $referenceOrder] = $this->collectValidKeys($referenceFile, $io);

        // Clean each locale
        foreach (Settings::LOCALES as $locale => $language) {
            if ('en' !== $locale) {
                $file = $this->projectDir.'/translations/messages.'.$locale.'.xlf';
                if (!file_exists($file)) {
                    $io->writeln('   (skip) File not found: '.$file);
                    continue;
                }

                $io->writeln('   -> '.$locale.' ('.$file.')');
                [$removed, $kept, $changed] = $this->cleanupObsoleteMessagesXlfFile($locale, 'messages', $validKeys, $referenceOrder);

                $totalRemoved += $removed;
                $totalKept += $kept;
                $changedFiles += $changed ? 1 : 0;
                $label = $changed ? 'changed' : 'no changes';
                $io->writeln(sprintf('     %s | kept: %d, removed: %d', $label, $kept, $removed));
            }
        }

        $io->success(sprintf('Done. Files changed: %d | total kept: %d | obsolete removed: %d', $changedFiles, $totalKept, $totalRemoved));

        return Command::SUCCESS;
    }

    private function handleIntlIcuFile(string $locale, string $domain, SymfonyStyle $io): void
    {
        // Define the potential +intl-icu file
        $intlIcuFile = $this->projectDir.'/translations/'.$domain.'+intl-icu.'.$locale.'.xlf';
        $standardFile = $this->projectDir.'/translations/'.$domain.'.'.$locale.'.xlf';

        // Check if the intl-icu file exists
        if (file_exists($intlIcuFile)) {
            // Rename the +intl-icu file to the standard format
            rename($intlIcuFile, $standardFile);
            $io->success("Renamed file: $domain+intl-icu.$locale.xlf to $domain.$locale.xlf");
        }
    }

    private function cleanXlfFile(string $locale, string $domain, SymfonyStyle $io): void
    {
        // Define the specific file to clean (messages.{locale}.xlf)
        $file = $this->projectDir.'/translations/'.$domain.'.'.$locale.'.xlf';

        if (!file_exists($file)) {
            $io->warning("File not found: translations/$domain.$locale.xlf");

            return;
        }

        $content = file_get_contents($file);

        // Replace <target>__...</target> with <target></target>
        $updatedContent = preg_replace('/<target>__([^<]*)<\/target>/', '<target></target>', $content);

        // Save the updated content back to the file
        file_put_contents($file, $updatedContent);

        // Show relative path
        $relativePath = 'translations/'.$domain.'.'.$locale.'.xlf';
        $io->success("Cleaned file: $relativePath");
    }

    private function collectValidKeys(string $referenceFile, SymfonyStyle $io): array
    {
        [$dom, $units] = $this->loadXlfFile($referenceFile);

        $valid = [];
        $order = [];
        foreach ($units as $unit) {
            $resname = $unit->getAttribute('resname');
            $id = $unit->getAttribute('id');
            $sourceNode = $unit->getElementsByTagName('source')->item(0);

            $rawSource = $sourceNode ? ($sourceNode->nodeValue ?? '') : '';
            // Normalize with sanitizeTarget, but without target, rtrim is enough for us
            $sourceText = rtrim($rawSource);

            $key = '' !== $resname ? $resname : ('' !== $id ? $id : $sourceText);
            if ('' !== $key && !isset($valid[$key])) {
                $valid[$key] = true;
                $order[] = $key;
            }
        }

        $unitsCount = $units->length;

        if (0 === $unitsCount) {
            $io->warning('No <trans-unit> found in the reference. Is it a valid XLIFF 1.2 file?');
        } else {
            $io->success(sprintf(
                'Reference trans-units: %d | Distinct keys: %d',
                $unitsCount,
                count($valid)
            ));
        }

        return [$valid, $order];
    }

    private function cleanupObsoleteMessagesXlfFile(string $locale, string $domain, array $validKeys, array $referenceOrder): array
    {
        // Define the specific file to clean (messages.{locale}.xlf)
        $file = $this->projectDir.'/translations/'.$domain.'.'.$locale.'.xlf';

        [$dom, $units] = $this->loadXlfFile($file);

        $removed = 0;
        $kept = 0;
        $changed = false;
        $validUnits = [];

        foreach ($units as $unit) {
            $resname = $unit->getAttribute('resname');
            $id = $unit->getAttribute('id');
            $sourceNode = $unit->getElementsByTagName('source')->item(0);
            $targetNode = $unit->getElementsByTagName('target')->item(0);
            $rawSource = $sourceNode ? ($sourceNode->nodeValue ?? '') : '';
            $sourceText = rtrim($rawSource);
            $key = '' !== $resname ? $resname : ('' !== $id ? $id : $sourceText);

            if ('' !== $key && isset($validKeys[$key])) {
                ++$kept;

                if ($targetNode) {
                    $t = trim($targetNode->nodeValue ?? '');
                    if (str_starts_with($t, '__')) {
                        $targetNode->nodeValue = '';
                        $changed = true;
                    }

                    $sanitized = $this->sanitizeTarget($sourceNode->nodeValue ?? '', $targetNode->nodeValue ?? '');
                    if ($sanitized !== ($targetNode->nodeValue ?? '')) {
                        $targetNode->nodeValue = $sanitized;
                        $changed = true;
                    }

                    // To write the <target></target> instead of <target/> if it is empty
                    if ('' === $targetNode->nodeValue) {
                        $targetNode->appendChild($dom->createTextNode(''));
                    }
                }

                $validUnits[$key] = $unit;
            } else {
                $unit->parentNode?->removeChild($unit);
                ++$removed;
                $changed = true;
            }
        }

        // Reorder according to English reference
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            while ($body->firstChild) {
                $body->removeChild($body->firstChild);
            }
            foreach ($referenceOrder as $key) {
                if (isset($validUnits[$key])) {
                    $imported = $dom->importNode($validUnits[$key], true);
                    $body->appendChild($imported);
                }
            }
            $changed = true;
        }

        if ($changed) {
            $dom->save($file);
        }

        return [$removed, $kept, $changed];
    }

    private function loadXlfFile(string $file): array
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->load($file);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');
        $units = $xpath->query('//x:trans-unit');

        // Fallback
        if (0 === $units->length) {
            $units = $dom->getElementsByTagName('trans-unit');
        }

        return [$dom, $units];
    }

    private function sanitizeTarget(string $source, string $target): string
    {
        $srcEndsSpace = str_ends_with($source, ' ');

        if ($srcEndsSpace) {
            // The source ends in space, the target must end the same
            return rtrim($target).' ';
        }

        // The source does NOT end in a space, we remove excess spaces in the target
        return rtrim($target);
    }
}
