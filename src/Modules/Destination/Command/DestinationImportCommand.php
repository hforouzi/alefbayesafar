<?php

namespace App\Modules\Destination\Command;

use App\Modules\Destination\Service\DestinationImportService;
use App\Modules\Destination\ValueObject\DestinationEntityType;
use App\Modules\Destination\ValueObject\DestinationImportRequest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:destination:import',
    description: 'Import or refresh destination catalog records from configured travel sources.'
)]
class DestinationImportCommand extends Command
{
    public function __construct(
        private readonly DestinationImportService $importService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'Target type: country, state, city, or airport.', DestinationEntityType::CITY)
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country name or ISO2. Use GLOBAL for unrestricted GeoNames/OurAirports bootstrap.', 'GLOBAL')
            ->addOption('city', null, InputOption::VALUE_REQUIRED, 'City name for targeted city/airport imports.')
            ->addOption('provider', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Provider code. Repeat for multiple providers.')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Refresh existing source references and fill safe missing fields.')
            ->addOption('show-items', null, InputOption::VALUE_NONE, 'Show every imported item. Large imports print a summary by default.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $providers = $input->getOption('provider');
        $providerCodes = \is_array($providers) && $providers !== [] ? array_values(array_map('strval', $providers)) : ['booking', 'tripadvisor', 'wikivoyage'];
        $target = (string) $input->getOption('target');
        $cityOption = $input->getOption('city');
        $cityName = \is_string($cityOption) && trim($cityOption) !== '' ? trim($cityOption) : null;

        if (!\in_array($target, [DestinationEntityType::COUNTRY, DestinationEntityType::STATE, DestinationEntityType::CITY, DestinationEntityType::AIRPORT], true)) {
            $io->error('Target must be "country", "state", "city", or "airport".');

            return Command::INVALID;
        }

        $summary = $this->importService->import(new DestinationImportRequest(
            targetType: $target,
            countryName: (string) $input->getOption('country'),
            cityName: $this->shouldUseCityOption($target, $providerCodes, $cityName) ? $cityName : null,
            providerCodes: $providerCodes,
            refresh: (bool) $input->getOption('refresh'),
        ));

        $io->table(
            ['Found', 'Created', 'Updated', 'Unchanged', 'Skipped', 'Failed'],
            [[$summary->found, $summary->created, $summary->updated, $summary->unchanged, $summary->skipped, $summary->failed]]
        );

        if ($summary->errors !== []) {
            $io->section('Errors');
            foreach ($summary->errors as $error) {
                $io->writeln($error);
            }
        }

        $detailedItemCount = \count($summary->items) + $summary->itemsSuppressed;
        if ((bool) $input->getOption('show-items') || $detailedItemCount <= 100) {
            $io->table(['Provider', 'Type', 'Name', 'Status', 'Canonical ID', 'Note'], array_map(
                static fn (array $item): array => [
                    $item['provider'] ?? '',
                    $item['type'] ?? '',
                    $item['name'] ?? '',
                    $item['status'] ?? '',
                    $item['canonicalId'] ?? '',
                    $item['note'] ?? '',
                ],
                $summary->items
            ));
        } else {
            $io->note(sprintf(
                'Per-item output suppressed for %d of %d items. Re-run with --show-items to print stored details.',
                $summary->itemsSuppressed,
                $detailedItemCount
            ));
        }

        return $summary->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param string[] $providerCodes
     */
    private function shouldUseCityOption(string $target, array $providerCodes, ?string $cityName): bool
    {
        if ($target === DestinationEntityType::AIRPORT) {
            return $cityName !== null;
        }

        return $target === DestinationEntityType::CITY && $providerCodes !== ['geonames'];
    }
}
