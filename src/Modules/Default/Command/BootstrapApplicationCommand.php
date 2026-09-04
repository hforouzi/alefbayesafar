<?php

namespace App\Modules\Default\Command;

use App\Modules\Default\Service\ApplicationBootstrapService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:bootstrap', description: 'Bootstrap required application system data.')]
final class BootstrapApplicationCommand extends Command
{
    public function __construct(private readonly ApplicationBootstrapService $bootstrapService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview bootstrap changes without writing to the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Application Bootstrap');

        try {
            $result = $this->bootstrapService->bootstrap($dryRun);
        } catch (\Throwable $exception) {
            $io->error(sprintf('Bootstrap failed: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $rows = [];
        foreach ($result->groups() as $group => $counts) {
            $rows[] = [$group, $counts['created'], $counts['updated'], $counts['existing'], $counts['skipped']];
        }

        $io->table(['Group', 'Created', 'Updated', 'Existing', 'Skipped'], $rows);
        foreach ($result->warnings() as $warning) {
            $io->warning($warning);
        }

        $io->success(sprintf(
            '%s completed successfully. Created: %d, Updated: %d, Existing: %d, Skipped: %d.',
            $dryRun ? 'Dry-run' : 'Bootstrap',
            $result->total('created'),
            $result->total('updated'),
            $result->total('existing'),
            $result->total('skipped'),
        ));

        return Command::SUCCESS;
    }
}
