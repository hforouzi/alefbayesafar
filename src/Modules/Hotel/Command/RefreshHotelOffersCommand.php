<?php

namespace App\Modules\Hotel\Command;

use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelOfferSearchService;
use App\Modules\Hotel\Service\HotelOfferStoreService;
use App\Modules\Hotel\ValueObject\HotelOfferSearchRequest;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hotel:offers:refresh', description: 'Refresh external hotel offer snapshots for active hotels and common stay windows.')]
class RefreshHotelOffersCommand extends Command
{
    public function __construct(
        private readonly HotelRepository $hotelRepository,
        private readonly HotelOfferSearchService $searchService,
        private readonly HotelOfferStoreService $storeService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hotel-id', null, InputOption::VALUE_OPTIONAL, 'Only refresh one hotel ID.')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'First check-in date, Y-m-d.')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Last check-in date, Y-m-d.')
            ->addOption('nights', null, InputOption::VALUE_REQUIRED, 'Stay length in nights.', '3')
            ->addOption('adults', null, InputOption::VALUE_REQUIRED, 'Adult count.', '2')
            ->addOption('children', null, InputOption::VALUE_REQUIRED, 'Child count.', '0')
            ->addOption('children-ages', null, InputOption::VALUE_OPTIONAL, 'Comma-separated child ages.', '');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $from = $this->dateOption($input, 'from');
        $to = $this->dateOption($input, 'to');
        $nights = max(1, (int) $input->getOption('nights'));
        $adults = max(1, (int) $input->getOption('adults'));
        $children = max(0, (int) $input->getOption('children'));
        $childrenAges = $this->childrenAges((string) $input->getOption('children-ages'));
        $hotelId = $input->getOption('hotel-id') !== null ? (int) $input->getOption('hotel-id') : null;

        if (!$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable || $to < $from) {
            $io->error('Provide a valid --from and --to date range in Y-m-d format.');

            return Command::INVALID;
        }

        if (\count($childrenAges) !== $children) {
            $io->error('Provide one --children-ages value for each child.');

            return Command::INVALID;
        }

        $hotels = $this->hotelRepository->findActiveWithSourceReferences($hotelId);
        $lookups = 0;
        $stored = 0;

        foreach ($hotels as $hotel) {
            for ($checkIn = $from; $checkIn <= $to; $checkIn = $checkIn->modify('+1 day')) {
                $checkOut = $checkIn->modify(sprintf('+%d days', $nights));
                if ($checkOut > $to->modify(sprintf('+%d days', $nights))) {
                    break;
                }

                $request = new HotelOfferSearchRequest($checkIn, $checkOut, $adults, $children, $childrenAges);
                $summary = $this->searchService->search($hotel, $request);
                $stored += $this->storeService->storeSummary($hotel, $request, $summary);
                ++$lookups;
            }
        }

        $io->success(sprintf('Processed %d hotel/date lookup(s), stored %d external offer snapshot(s).', $lookups, $stored));

        return Command::SUCCESS;
    }

    private function dateOption(InputInterface $input, string $name): ?\DateTimeImmutable
    {
        $value = $input->getOption($name);
        if (!\is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date instanceof \DateTimeImmutable ? $date : null;
    }

    /**
     * @return int[]
     */
    private function childrenAges(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        return array_map('intval', array_filter(array_map('trim', explode(',', $value)), static fn (string $age): bool => $age !== ''));
    }
}
