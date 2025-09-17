<?php

namespace App\Command;

use App\Config\SystemPrefRegistry;
use App\Service\net\exelearning\Service\SystemPreferencesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:prefs:list', description: 'List system preferences (current values)')]
class PrefsListCommand extends Command
{
    public function __construct(
        private readonly SystemPrefRegistry $registry,
        private readonly SystemPreferencesService $prefs,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Filter by prefix (e.g. maintenance.)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prefix = (string) ($input->getOption('prefix') ?? '');
        $defs = $this->registry->all();
        ksort($defs);
        foreach ($defs as $key => $def) {
            if ($prefix && !str_starts_with($key, $prefix)) {
                continue;
            }
            $val = $this->prefs->get($key, $def['default'] ?? null);
            $dump = is_bool($val) ? ($val ? 'true' : 'false') : (is_scalar($val) || null === $val ? var_export($val, true) : json_encode($val));
            $output->writeln(sprintf('%s [%s]: %s', $key, $def['type'], (string) $dump));
        }

        return Command::SUCCESS;
    }
}
