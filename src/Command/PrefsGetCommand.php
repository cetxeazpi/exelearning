<?php

namespace App\Command;

use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:prefs:get', description: 'Get a system preference value')]
class PrefsGetCommand extends Command
{
    public function __construct(private readonly SystemPreferencesService $prefs)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('key', InputArgument::REQUIRED, 'Preference key (e.g. maintenance.enabled)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = (string) $input->getArgument('key');
        $val = $this->prefs->get($key);
        $out = is_bool($val) ? ($val ? 'true' : 'false') : (is_scalar($val) || null === $val ? var_export($val, true) : json_encode($val));
        $output->writeln((string) $out);

        return Command::SUCCESS;
    }
}
