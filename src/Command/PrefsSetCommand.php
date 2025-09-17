<?php

namespace App\Command;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:prefs:set', description: 'Set a system preference value')]
class PrefsSetCommand extends Command
{
    public function __construct(private readonly SystemPreferencesService $prefs)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('key', InputArgument::REQUIRED, 'Preference key (e.g. maintenance.enabled)')
            ->addArgument('value', InputArgument::REQUIRED, 'Value to set')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Type: bool,int,float,string,json,datetime,html,file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = (string) $input->getArgument('key');
        $raw = (string) $input->getArgument('value');
        $type = $input->getOption('type');

        $val = $raw;
        if ('bool' === $type) {
            $val = in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true);
        } elseif ('int' === $type) {
            $val = (int) $raw;
        } elseif ('float' === $type) {
            $val = (float) $raw;
        } elseif ('json' === $type) {
            $decoded = json_decode($raw, true);
            $val = null === $decoded && 'null' !== strtolower(trim($raw)) ? $raw : $decoded;
        } elseif ('datetime' === $type) {
            // Allow natural strings; service will serialize accordingly
            $val = new \DateTimeImmutable($raw);
        }

        $this->prefs->set($key, $val, $type ?: null, 'cli');
        $output->writeln("Updated {$key}");

        return Command::SUCCESS;
    }
}
