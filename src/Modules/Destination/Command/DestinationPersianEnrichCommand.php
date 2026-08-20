<?php

namespace App\Modules\Destination\Command;

use App\Modules\Destination\Service\PersianDestinationNameEnrichmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:destination:enrich-persian-names',
    description: 'Fill missing Persian destination names from official GeoNames alternate-name files.'
)]
class DestinationPersianEnrichCommand extends Command
{
    public function __construct(
        private readonly PersianDestinationNameEnrichmentService $enrichmentService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Optional ISO2 country code. Without it, cached/available GeoNames alternate-name files are considered.')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Download/refresh the GeoNames alternate-name file before enrichment.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $country = $input->getOption('country');
        $summary = $this->enrichmentService->enrich(\is_string($country) && trim($country) !== '' ? $country : null, (bool) $input->getOption('refresh'));

        $io->table(
            ['Countries', 'States', 'Cities', 'Airports', 'Curated skipped', 'Missing source skipped'],
            [[$summary['countries'], $summary['states'], $summary['cities'], $summary['airports'], $summary['skippedCurated'], $summary['skippedMissingSource']]]
        );

        if ($summary['files'] !== []) {
            $io->section('GeoNames alternate-name sources');
            foreach (array_unique($summary['files']) as $file) {
                $io->writeln($file);
            }
        }

        return Command::SUCCESS;
    }
}
