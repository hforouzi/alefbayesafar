<?php

namespace App\Tests\Modules\SearchSource;

use App\Modules\Destination\Entity\Country;
use App\Modules\SearchSource\Entity\SearchSource;
use App\Modules\User\Entity\Role;
use App\Modules\User\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Routing\RouterInterface;

class SearchSourceAdminCrudTest extends WebTestCase
{
    public function testSearchSourceRoutesExist(): void
    {
        self::bootKernel();
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        foreach ([
            'search_source_index',
            'search_source_new',
            'search_source_show',
            'search_source_edit',
            'search_source_delete',
        ] as $routeName) {
            self::assertNotNull($router->getRouteCollection()->get($routeName), $routeName);
        }
    }

    public function testAnonymousSearchSourceAdminAccessRedirectsToLogin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/data-sources/sources/');

        self::assertResponseRedirects('/login');
    }

    public function testPersianSearchSourceAdminLabelsAreTranslated(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $application = new Application(self::$kernel);
        $command = $application->find('app:search-source:seed-admin');
        self::assertSame(0, (new CommandTester($command))->execute([]));

        $client->request('GET', '/admin/data-sources/sources/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'منابع جستجو');
        self::assertSelectorTextContains('body', 'مدیریت منابع داده بیرونی');
        self::assertSelectorTextContains('body', 'افزودن منبع جستجو');
        self::assertSelectorTextContains('body', 'جستجو');
        self::assertSelectorTextContains('body', 'نوع ارائه‌دهنده');
        self::assertSelectorTextContains('body', 'منابع داده');
        self::assertSelectorTextContains('body', 'منابع');
        self::assertSelectorTextNotContains('body', 'Search Sources');
        self::assertSelectorTextNotContains('body', 'Add Search Source');
        self::assertSelectorTextNotContains('body', 'Rows per page');
    }

    public function testSearchSourceCrudAndFilters(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());
        $suffix = self::uniqueSuffix();
        $country = $this->persistCountry($suffix);
        $em = $this->entityManager();

        $crawler = $client->request('GET', '/admin/data-sources/sources/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/data-sources/sources/new', [
            'search_source' => [
                '_token' => $this->formToken($crawler),
                'countryId' => (string) $country->getId(),
                'name' => 'Firecrawl Source ' . $suffix,
                'domain' => 'https://Example-' . strtolower($suffix) . '.test/',
                'provider' => 'firecrawl',
                'providerType' => 'FIRECRAWL',
                'language' => 'fa',
                'priority' => '10',
                'capabilities' => [SearchSource::CAPABILITY_HOTEL, SearchSource::CAPABILITY_REVIEW],
                'configJson' => '{"maxDepth":2}',
                'enabled' => '1',
            ],
        ]);

        $source = $em->getRepository(SearchSource::class)->findOneBy(['name' => 'Firecrawl Source ' . $suffix]);
        self::assertInstanceOf(SearchSource::class, $source);
        self::assertResponseRedirects('/admin/data-sources/sources/' . $source->getId());
        self::assertSame('example-' . strtolower($suffix) . '.test', $source->getDomain());
        self::assertSame($country->getId(), $source->getCountry()?->getId());
        self::assertSame(['hotel', 'review'], $source->getCapabilities());
        self::assertSame(['maxDepth' => 2], $source->getConfig());

        $client->request('GET', '/admin/data-sources/sources/', [
            'q' => $suffix,
            'providerType' => 'FIRECRAWL',
            'country' => $country->getId(),
            'enabled' => '1',
            'pageSize' => 25,
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="q"]');
        self::assertSelectorExists('select[name="providerType"]');
        self::assertSelectorExists('input[name="country"]');
        self::assertSelectorExists('select[name="enabled"]');
        self::assertSelectorTextContains('body', 'Firecrawl Source ' . $suffix);

        $crawler = $client->request('GET', '/admin/data-sources/sources/' . $source->getId() . '/edit');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/data-sources/sources/' . $source->getId() . '/edit', [
            'search_source' => [
                '_token' => $this->formToken($crawler),
                'countryId' => '',
                'name' => 'Updated Source ' . $suffix,
                'domain' => 'updated-' . strtolower($suffix) . '.test',
                'provider' => 'firecrawl',
                'providerType' => 'FIRECRAWL',
                'language' => 'en',
                'priority' => '1',
                'capabilities' => [SearchSource::CAPABILITY_HOTEL],
                'configJson' => '',
                'enabled' => '1',
            ],
        ]);
        self::assertResponseRedirects('/admin/data-sources/sources/' . $source->getId());

        $updated = $em->getRepository(SearchSource::class)->find($source->getId());
        self::assertInstanceOf(SearchSource::class, $updated);
        self::assertSame('Updated Source ' . $suffix, $updated->getName());
        self::assertNull($updated->getCountry());

        $crawler = $client->request('GET', '/admin/data-sources/sources/', ['q' => 'Updated Source ' . $suffix]);
        $client->request('POST', '/admin/data-sources/sources/' . $updated->getId() . '/delete', [
            '_token' => $crawler->filter(sprintf('form[action$="/%d/delete"] input[name="_token"]', $updated->getId()))->attr('value') ?? '',
        ]);
        self::assertResponseRedirects('/admin/data-sources/sources/');

        $disabled = $em->getRepository(SearchSource::class)->find($updated->getId());
        self::assertInstanceOf(SearchSource::class, $disabled);
        self::assertFalse($disabled->isEnabled());

        $crawler = $client->request('GET', '/admin/data-sources/sources/', ['q' => 'Updated Source ' . $suffix, 'enabled' => '0']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('form[action$="/%d/delete"] .btn-outline-success', $disabled->getId()));
        $client->request('POST', '/admin/data-sources/sources/' . $disabled->getId() . '/delete', [
            '_token' => $crawler->filter(sprintf('form[action$="/%d/delete"] input[name="_token"]', $disabled->getId()))->attr('value') ?? '',
        ]);
        self::assertResponseRedirects('/admin/data-sources/sources/');

        $enabled = $em->getRepository(SearchSource::class)->find($disabled->getId());
        self::assertInstanceOf(SearchSource::class, $enabled);
        self::assertTrue($enabled->isEnabled());

        $client->request('GET', '/admin/data-sources/sources/', ['q' => 'Updated Source ' . $suffix, 'enabled' => '1']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('form[action$="/%d/delete"] .btn-outline-danger', $enabled->getId()));
    }

    public function testSecretConfigIsRejectedByForm(): void
    {
        $client = self::createClient();
        $client->disableReboot();
        $client->loginUser($this->createSuperAdminUser());

        $crawler = $client->request('GET', '/admin/data-sources/sources/new');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/admin/data-sources/sources/new', [
            'search_source' => [
                '_token' => $this->formToken($crawler),
                'name' => 'Unsafe Source ' . self::uniqueSuffix(),
                'domain' => 'unsafe.example.test',
                'provider' => 'firecrawl',
                'providerType' => 'FIRECRAWL',
                'priority' => '0',
                'capabilities' => [SearchSource::CAPABILITY_HOTEL],
                'configJson' => '{"api_key":"secret"}',
                'enabled' => '1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'api_key');
        self::assertNull($this->entityManager()->getRepository(SearchSource::class)->findOneBy(['domain' => 'unsafe.example.test']));
    }

    private function createSuperAdminUser(): UserEntity
    {
        $em = $this->entityManager();
        $role = $em->getRepository(Role::class)->findOneBy(['name' => 'ROLE_SUPER_ADMIN']);
        if (!$role instanceof Role) {
            $role = (new Role())->setName('ROLE_SUPER_ADMIN');
            $em->persist($role);
        }

        $user = (new UserEntity())
            ->setEmail('phase4-admin-' . strtolower(self::uniqueSuffix()) . '@example.test')
            ->setPassword('not-used')
            ->addUserRole($role);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function persistCountry(string $suffix): Country
    {
        $country = (new Country())
            ->setName('Search Source Country ' . $suffix);

        $em = $this->entityManager();
        $em->persist($country);
        $em->flush();

        return $country;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function formToken(Crawler $crawler): string
    {
        return $crawler->filter('input[name="search_source[_token]"]')->attr('value') ?? '';
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
