<?php

namespace App\Modules\Hotel\Command;

use App\Modules\Hotel\Entity\Hotel;
use App\Modules\Hotel\Entity\HotelSourceReference;
use App\Modules\Hotel\Repository\HotelRepository;
use App\Modules\Hotel\Service\HotelRoomTypeImporter;
use App\Modules\Hotel\ValueObject\HotelCandidate;
use App\Modules\Hotel\ValueObject\HotelRoomTypeCandidate;
use App\Modules\SearchSource\Provider\FirecrawlProvider;
use App\Modules\SearchSource\Provider\ProviderConfigurationException;
use App\Modules\SearchSource\Provider\ProviderRequestException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'hotel:enrich', description: 'Refresh factual hotel catalogue data such as room types from source references.')]
final class EnrichHotelsCommand extends Command
{
    public function __construct(
        private readonly HotelRepository $hotelRepository,
        private readonly HotelRoomTypeImporter $roomTypeImporter,
        private readonly FirecrawlProvider $firecrawlProvider,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hotel-id', null, InputOption::VALUE_REQUIRED, 'Restrict enrichment to one hotel ID.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum hotels to process.', '25');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hotelId = $input->getOption('hotel-id') !== null ? (int) $input->getOption('hotel-id') : null;
        $limit = max(1, min(100, (int) $input->getOption('limit')));
        $hotels = $this->hotels($hotelId, $limit);

        if ($hotels === []) {
            $io->warning('No eligible hotels with source references were found.');

            return Command::SUCCESS;
        }

        foreach ($hotels as $hotel) {
            $io->section($hotel->getName());
            foreach ($hotel->getSourceReferences() as $reference) {
                if ($reference->getSourceUrl() === null) {
                    continue;
                }

                try {
                    $result = $this->enrichReference($hotel, $reference);
                } catch (ProviderConfigurationException|ProviderRequestException $exception) {
                    $reference
                        ->setSyncStatus(HotelSourceReference::STATUS_FAILED)
                        ->setLastError($exception->getMessage())
                        ->setMetadata(array_merge($reference->getMetadata(), [
                            'enrichment' => [
                                'status' => 'REQUEST_FAILED',
                                'endpoint' => '/v2/scrape',
                                'method' => 'POST',
                                'url' => $reference->getSourceUrl(),
                                'httpStatus' => $exception->getCode() > 0 ? $exception->getCode() : null,
                                'rawRoomCount' => 0,
                                'roomCount' => 0,
                                'lastError' => $exception->getMessage(),
                            ],
                        ]));
                    $this->entityManager->flush();
                    $io->error(sprintf('%s: %s', $reference->getSource(), $exception->getMessage()));
                    continue;
                }

                $io->writeln(sprintf(
                    '%s: %s; %d raw rooms; %d created, %d updated, %d skipped',
                    $reference->getSource(),
                    $result['status'],
                    $result['rawRoomCount'],
                    $result['created'],
                    $result['updated'],
                    $result['skipped'],
                ));
            }
        }

        return Command::SUCCESS;
    }

    /**
     * @return Hotel[]
     */
    private function hotels(?int $hotelId, int $limit): array
    {
        if ($hotelId !== null) {
            $hotel = $this->hotelRepository->findWithDetails($hotelId);

            return $hotel instanceof Hotel ? [$hotel] : [];
        }

        return array_slice($this->hotelRepository->findActiveWithSourceReferences(), 0, $limit);
    }

    /**
     * @return array{status: string, rawRoomCount: int, created: int, updated: int, skipped: int}
     */
    private function enrichReference(Hotel $hotel, HotelSourceReference $reference): array
    {
        $payload = [
            'url' => $reference->getSourceUrl(),
            'formats' => [[
                'type' => 'json',
                'schema' => $this->roomSchema(),
                'prompt' => 'Extract the distinct room types shown for this hotel. For each room type return only factual information explicitly available on the page: name, external room id if available, maximum adults, maximum children, maximum occupancy, bed configuration, room size in square meters, and room description. Do not estimate missing values. Do not invent room types. Do not return prices.',
            ]],
        ];
        $response = $this->firecrawlProvider->request('POST', '/v2/scrape', $payload);
        $rooms = $this->roomTypes($response);
        $rawRoomCount = $this->rawRoomCount($response);

        if (($response['success'] ?? true) === false) {
            throw new ProviderRequestException(ProviderRequestException::TYPE_REQUEST, 'Firecrawl returned an unsuccessful response.', 200);
        }

        $candidate = new HotelCandidate(
            sourceIdentifier: $reference->getSource(),
            sourceName: $reference->getSource(),
            providerCode: 'firecrawl',
            externalId: $reference->getExternalId(),
            sourceUrl: $reference->getSourceUrl(),
            sourceTitle: $reference->getSourceTitle(),
            name: $hotel->getName(),
            nameFa: $hotel->getNameFa(),
            address: $hotel->getAddress(),
            countryName: $hotel->getCity()?->getCountry()?->getName(),
            cityName: $hotel->getCity()?->getName(),
            districtName: $hotel->getDistrict()?->getName(),
            stars: $hotel->getStars(),
            latitude: $hotel->getLatitude(),
            longitude: $hotel->getLongitude(),
            website: $hotel->getWebsite(),
            phone: $hotel->getPhone(),
            descriptionOriginal: $hotel->getDescriptionOriginal(),
            descriptionFa: $hotel->getDescriptionFa(),
            rawData: ['enrichmentResponseKeys' => array_keys($response)],
            roomTypes: $rooms,
        );

        $result = $this->roomTypeImporter->apply($hotel, $candidate);
        $reference
            ->setSyncStatus(HotelSourceReference::STATUS_SYNCED)
            ->setLastError(null)
            ->setMetadata(array_merge($reference->getMetadata(), [
                'enrichment' => [
                    'status' => $rooms !== [] ? 'ROOMS_FOUND' : 'NO_ROOM_DATA',
                    'endpoint' => '/v2/scrape',
                    'method' => 'POST',
                    'url' => $reference->getSourceUrl(),
                    'rawRoomCount' => $rawRoomCount,
                    'roomCount' => count($rooms),
                    'created' => $result['created'],
                    'updated' => $result['updated'],
                    'skipped' => $result['skipped'],
                    'topLevelKeys' => array_keys($response),
                    'dataKeys' => is_array($response['data'] ?? null) ? array_keys($response['data']) : [],
                ],
            ]))
            ->markSeen();
        $this->entityManager->flush();

        return [
            'status' => $rooms !== [] ? 'ROOMS_FOUND' : 'NO_ROOM_DATA',
            'rawRoomCount' => $rawRoomCount,
            ...$result,
        ];
    }

    /** @return array<string, mixed> */
    private function roomSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'rooms' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'externalId' => ['type' => ['string', 'null']],
                            'name' => ['type' => 'string'],
                            'maxAdults' => ['type' => ['integer', 'null']],
                            'maxChildren' => ['type' => ['integer', 'null']],
                            'maxOccupancy' => ['type' => ['integer', 'null']],
                            'beds' => ['type' => ['string', 'null']],
                            'sizeSqm' => ['type' => ['number', 'null']],
                            'description' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            'required' => ['rooms'],
        ];
    }

    /** @param array<string, mixed> $response */
    private function rawRoomCount(array $response): int
    {
        foreach ([
            $response['data']['json']['rooms'] ?? null,
            $response['json']['rooms'] ?? null,
            $response['data']['rooms'] ?? null,
        ] as $rooms) {
            if (is_array($rooms)) {
                return count($rooms);
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return HotelRoomTypeCandidate[]
     */
    private function roomTypes(array $response): array
    {
        $rawRooms = [];
        foreach ([
            $response['rooms'] ?? null,
            \is_array($response['data'] ?? null) ? ($response['data']['rooms'] ?? null) : null,
            \is_array($response['data']['json'] ?? null) ? ($response['data']['json']['rooms'] ?? null) : null,
            \is_array($response['json'] ?? null) ? ($response['json']['rooms'] ?? null) : null,
        ] as $rooms) {
            if (\is_array($rooms)) {
                $rawRooms = $rooms;
                break;
            }
        }

        $roomTypes = [];
        foreach ($rawRooms as $rawRoom) {
            if (!\is_array($rawRoom)) {
                continue;
            }

            $roomType = HotelRoomTypeCandidate::fromArray($rawRoom);
            if ($roomType instanceof HotelRoomTypeCandidate) {
                $roomTypes[] = $roomType;
            }
        }

        return $roomTypes;
    }
}
