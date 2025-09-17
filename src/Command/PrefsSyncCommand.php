<?php

namespace App\Command;

use App\Config\SystemPrefRegistry;
use App\Entity\net\exelearning\Entity\SystemPreferences;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create missing keys and fill with default values without overwriting existing ones.
 */
#[AsCommand(name: 'app:prefs:sync', description: 'Sync system preferences definitions into database')]
class PrefsSyncCommand extends Command
{
    public function __construct(
        private readonly SystemPrefRegistry $registry,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repo = $this->em->getRepository(SystemPreferences::class);
        $defs = $this->registry->all();

        foreach ($defs as $key => $def) {
            $row = $repo->findOneBy(['key' => $key]);
            if (!$row) {
                $row = (new SystemPreferences())
                    ->setKey($key)
                    ->setType($def['type'])
                    ->setValue(null === $def['default'] ? null : (string) $def['default']);
                $this->em->persist($row);
                $output->writeln("Created: {$key}");
            } elseif ($row->getType() !== $def['type']) {
                $row->setType($def['type']); // mantenemos el value
                $output->writeln("Updated type: {$key} -> {$def['type']}");
            }
        }
        $this->em->flush();

        return Command::SUCCESS;
    }
}
