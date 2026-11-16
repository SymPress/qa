<?php

declare(strict_types=1);

namespace SymPress\Qa\Tests\Unit\Console;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use SymPress\Qa\Console\ApplicationFactory;

final class ApplicationFactoryTest extends TestCase
{
    public function testVersionComesFromInstalledPackage(): void
    {
        self::assertSame(InstalledVersions::getPrettyVersion('sympress/qa'), ApplicationFactory::create()->getVersion());
    }
}
