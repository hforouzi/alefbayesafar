<?php

namespace App\Tests\Modules\Activity;

use App\Modules\Activity\Entity\Activity;
use App\Modules\Activity\Entity\ActivityImage;
use App\Modules\Activity\Entity\ActivityOffer;
use App\Modules\Activity\Enum\ActivityCategory;
use App\Modules\Activity\Enum\ActivityPricingMode;
use App\Modules\Destination\Entity\City;
use App\Modules\Destination\Entity\Country;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class ActivityAdminWorkflowTest extends WebTestCase
{
    public function testActivityCommerceRoutesExistAndAnonymousUserRedirects(): void
    {
        $client = self::createClient();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'activity_index',
            'activity_new',
            'activity_edit',
            'activity_toggle',
            'activity_image_new',
            'activity_image_edit',
            'activity_image_delete',
            'activity_offer_index',
            'activity_offer_new',
            'activity_offer_edit',
            'activity_offer_toggle',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }

        $client->request('GET', '/admin/activity-commerce/activities/');
        self::assertResponseRedirects('/login');
    }

    public function testAdminCanCreateEditToggleAndManagePricing(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $this->runBootstrap();

        $city = $this->city();
        $slug = 'istanbul-bosphorus-cruise-' . strtolower(self::uniqueSuffix());

        $crawler = $client->request('GET', '/admin/activity-commerce/activities/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/activity-commerce/activities/new', [
            'activity' => [
                '_token' => $this->formToken($crawler, 'activity'),
                'cityId' => (string) $city->getId(),
                'name' => 'Bosphorus Cruise',
                'nameFa' => '',
                'slug' => $slug,
                'category' => 'cruise',
                'durationMinutes' => '120',
                'meetingPointText' => 'Eminonu Pier',
                'latitude' => '',
                'longitude' => '',
                'shortDescription' => 'Evening cruise',
                'description' => '',
                'active' => '1',
                'featured' => '1',
                'publicVisible' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/activity-commerce/activities/');

        $activity = $this->em()->getRepository(Activity::class)->findOneBy(['slug' => $slug]);
        self::assertInstanceOf(Activity::class, $activity);
        self::assertSame($city->getId(), $activity->getCity()?->getId());
        self::assertSame(ActivityCategory::CRUISE, $activity->getCategory());

        $offerCrawler = $client->request('GET', '/admin/activity-commerce/activities/' . $activity->getId() . '/offers/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/activity-commerce/activities/' . $activity->getId() . '/offers/new', [
            'activity_offer' => [
                '_token' => $this->formToken($offerCrawler, 'activity_offer'),
                'validFrom' => '',
                'validTo' => '',
                'specificDate' => '',
                'currency' => 'EUR',
                'pricingMode' => 'per_person_type',
                'totalPrice' => '',
                'adultPrice' => '40',
                'childPrice' => '20',
                'infantPrice' => '',
                'minimumParticipants' => '',
                'maximumParticipants' => '',
                'availabilityStatus' => 'available',
                'priority' => '100',
                'active' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/activity-commerce/activities/' . $activity->getId() . '/offers/');

        $offer = $this->em()->getRepository(ActivityOffer::class)->findOneBy(['activity' => $activity]);
        self::assertInstanceOf(ActivityOffer::class, $offer);
        self::assertSame('40.00', $offer->getAdultPrice());
        self::assertSame('60.00', $offer->calculatePartyPrice(1, 1, 0));

        $crawler = $client->request('GET', '/admin/activity-commerce/activities/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bosphorus Cruise');

        $toggleToken = $crawler
            ->filter(sprintf('form[action="/admin/activity-commerce/activities/%d/toggle"] input[name="_token"]', $activity->getId()))
            ->attr('value');
        $client->request('POST', '/admin/activity-commerce/activities/' . $activity->getId() . '/toggle', ['_token' => $toggleToken]);
        self::assertResponseRedirects('/admin/activity-commerce/activities/');

        $this->em()->clear();
        $toggled = $this->em()->getRepository(Activity::class)->find($activity->getId());
        self::assertInstanceOf(Activity::class, $toggled);
        self::assertFalse($toggled->isActive());
    }

    public function testBootstrapCreatesActivityCommerceMenuAndPermissionsIdempotently(): void
    {
        self::createClient()->disableReboot();
        $beforeActivities = $this->em()->getRepository(Activity::class)->count([]);

        $first = $this->runBootstrap();
        $second = $this->runBootstrap();

        self::assertSame(0, $first->getStatusCode());
        self::assertSame(0, $second->getStatusCode());
        self::assertSame($beforeActivities, $this->em()->getRepository(Activity::class)->count([]));
    }

    public function testActivityOfferValidationRejectsInvalidPricingCombination(): void
    {
        self::createClient();
        $city = $this->city();
        $activity = (new Activity())
            ->setCity($city)
            ->setName('Test Activity ' . self::uniqueSuffix())
            ->setSlug('test-activity-' . strtolower(self::uniqueSuffix()))
            ->setCategory(ActivityCategory::OTHER);
        $this->em()->persist($activity);
        $this->em()->flush();

        $offer = (new ActivityOffer())
            ->setActivity($activity)
            ->setCurrency('EUR')
            ->setPricingMode(ActivityPricingMode::TOTAL_PARTY)
            ->setTotalPrice('100.00')
            ->setAdultPrice('10.00');

        self::bootKernel();
        $validator = self::getContainer()->get('validator');
        $violations = $validator->validate($offer);

        self::assertGreaterThan(0, $violations->count());
    }

    private function runBootstrap(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:bootstrap');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
    }

    private function city(): City
    {
        $suffix = self::uniqueSuffix();
        $country = (new Country())->setName('Activity Test Country ' . $suffix);
        $city = (new City())->setCountry($country)->setName('Activity Test City ' . $suffix)->setSlug('activity-test-city-' . strtolower($suffix));

        $this->em()->persist($country);
        $this->em()->persist($city);
        $this->em()->flush();

        return $city;
    }

    private function createSuperAdminUser(): UserEntity
    {
        $em = $this->em();
        $role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$role instanceof Role) {
            $role = (new Role())->setName('ROLE_SUPER_ADMIN');
            $em->persist($role);
        }

        $user = (new UserEntity())
            ->setEmail('activity-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function formToken(Crawler $crawler, string $formName): string
    {
        return $crawler->filter(sprintf('input[name="%s[_token]"]', $formName))->attr('value') ?? '';
    }

    private static function uniqueSuffix(): string
    {
        $letters = '';
        for ($index = 0; $index < 6; $index++) {
            $letters .= chr(random_int(65, 90));
        }

        return $letters;
    }
}
