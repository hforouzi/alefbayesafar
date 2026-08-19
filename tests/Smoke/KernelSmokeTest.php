<?php

namespace App\Tests\Smoke;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelSmokeTest extends KernelTestCase
{
    public function testApplicationKernelBoots(): void
    {
        self::bootKernel();

        self::assertSame('test', self::$kernel->getEnvironment());
    }
}
