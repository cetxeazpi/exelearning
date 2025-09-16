<?php

namespace App\Command;

use App\Entity\Maintenance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:maintenance',
    description: 'Toggle or show maintenance mode status',
)]
class MaintenanceToggleCommand extends Command
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('on', null, InputOption::VALUE_NONE, 'Enable maintenance mode')
            ->addOption('off', null, InputOption::VALUE_NONE, 'Disable maintenance mode')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Optional public message')
            ->addOption('until', 'u', InputOption::VALUE_REQUIRED, 'Optional scheduled end time (Y-m-d H:i)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Maintenance|null $maintenance */
        $maintenance = $this->em->getRepository(Maintenance::class)->findOneBy([]);
        if (!$maintenance instanceof Maintenance) {
            $maintenance = new Maintenance();
            $this->em->persist($maintenance);
        }

        $enabled = $maintenance->isEnabled();

        if ($input->getOption('on')) {
            $enabled = true;
        } elseif ($input->getOption('off')) {
            $enabled = false;
        }

        $maintenance->setEnabled($enabled);

        if (null !== ($message = $input->getOption('message'))) {
            $maintenance->setMessage($message);
        }

        if (null !== ($until = $input->getOption('until'))) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i', (string) $until) ?: null;
            $maintenance->setScheduledEndAt($dt);
        }

        $this->em->flush();

        $status = $maintenance->isEnabled() ? 'ON' : 'OFF';
        $output->writeln(sprintf('Maintenance: %s', $status));
        if ($maintenance->getMessage()) {
            $output->writeln(sprintf('Message: %s', $maintenance->getMessage()));
        }
        if ($maintenance->getScheduledEndAt()) {
            $output->writeln(sprintf('Scheduled end: %s', $maintenance->getScheduledEndAt()->format('Y-m-d H:i')));
        }

        return Command::SUCCESS;
    }
}
