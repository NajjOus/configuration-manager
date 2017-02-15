<?php

namespace Magium\Configuration\Tests\Manager;

use Magium\Configuration\Config\Builder;
use Magium\Configuration\Config\Config;
use Magium\Configuration\Manager\Manager;
use PHPUnit\Framework\TestCase;
use Zend\Cache\Storage\StorageInterface;

class ManagerTest extends TestCase
{

    public function testBuilderIsCalledIfCacheIsEmpty()
    {
        // self::exactly() is set in getBuilderMock()
        $manager = $this->getMockBuilder(Manager::class)->setMethods(['storeConfigurationObject'])->setConstructorArgs(
            [$this->getCacheMock(), $this->getBuilderMock()]
        )->getMock();
        $manager->getConfiguration('test');

    }

    public function testBuilderIsCalledOnlyOnceOnSubsequentCalls()
    {
        // self::exactly() is set in getBuilderMock()
        // Not testing cache storage of the resulting configuration object here.  So, mocking.
        $manager = $this->getMockBuilder(Manager::class)->setMethods(['storeConfigurationObject'])->setConstructorArgs(
            [$this->getCacheMock(), $this->getBuilderMock()]
        )->getMock();
        $manager->expects(self::any())->method('storeConfigurationObject');
        $manager->getConfiguration('test');
        $manager->getConfiguration('test');
    }

    public function testCacheIsCalledFirstForCacheLocationSecondForData()
    {
        $cache = $this->getCacheMock();
        $cache->expects(self::exactly(2))->method('getItem')->willReturnCallback(
            function($param) {
                static $first = false;
                if (!$first) {
                    $first = true;
                    return 'boogers';
                } else {
                    TestCase::assertEquals('boogers', $param);
                }
            }
        );

        // Not testing cache storage of the resulting configuration object here.  So, mocking.
        $manager = $this->getMockBuilder(Manager::class)->setMethods(['storeConfigurationObject'])->setConstructorArgs(
            [$cache, $this->getBuilderMock()]
        )->getMock();
        $manager->expects(self::any())->method('storeConfigurationObject');

        $manager->getConfiguration('test');
    }

    public function testContextIsPassed()
    {
        $cache = $this->getCacheMock();
        $cache->expects(self::exactly(4))->method('getItem')->willReturnCallback(function($param) {
            static $call = 0;
            // strtoupper is there to normalize the values
            switch ($call) {
                case 0:
                    TestCase::assertContains(strtoupper(Config::CONTEXT_DEFAULT), strtoupper($param));
                    $call++;
                    return 'test1';
                case 1:
                    self::assertEquals('test1', $param);
                    $call++;
                    break;
                case 2:
                    TestCase::assertContains(strtoupper('second'), strtoupper($param));
                    $call++;
                    return 'test2';
                case 3:
                    self::assertEquals('test2', $param);
                    $call++;
                    break;
            }
        });

        // Not testing cache storage of the resulting configuration object here.  So, mocking.
        $manager = $this->getMockBuilder(Manager::class)->setMethods(['storeConfigurationObject'])->setConstructorArgs(
            [$cache, $this->getBuilderMock(2)]
        )->getMock();
        $manager->expects(self::any())->method('storeConfigurationObject');

        $manager->getConfiguration();
        $manager->getConfiguration('second');
    }

    public function testConfigurationRetrievedFromCacheCanBeQueried()
    {
        $builder = $this->getBuilderMock(0);
        $cache = $this->getCacheMock();
        $cache->expects(self::exactly(2))->method('getItem')->willReturn(<<<XML
<?xml version="1.0" encoding="utf-8" ?>
<configuration>
<section><group><element>test</element></group></section>
</configuration>
XML
);
        $manager = new Manager($cache, $builder);
        $config = $manager->getConfiguration();
        self::assertInstanceOf(Config::class, $config);
        $value = $config->getValue('section/group/element');
        self::assertEquals('test', $value);
    }

    public function testScopeStoredLocallyIsSet()
    {
        $builder = $this->getBuilderMock(0);
        $remoteCache = $this->getCacheMock();
        $localCache = $this->getCacheMock();
        $remoteCache->expects(self::never())->method('getItem');
        $localCache->expects(self::exactly(2))->method('getItem')->willReturnOnConsecutiveCalls('testme', '<a />');
        $localCache->expects(self::never())->method('setItem');
        $manager = new Manager($remoteCache, $builder, $localCache);
        $manager->getConfiguration(Config::CONTEXT_DEFAULT, true);
    }

    public function testScopeStoredLocallyButRetrievedRemotelyIsCalledLocallyTheSecondTime()
    {
        $builder = $this->getBuilderMock(0);
        $remoteCache = $this->getCacheMock();
        $localCache = $this->getCacheMock();
        $remoteCache->expects(self::exactly(2))->method('getItem')->willReturnOnConsecutiveCalls('test', '<a />');
        $localCache->expects(self::exactly(2))->method('getItem')->willReturnOnConsecutiveCalls(null, null);
        $localCache->expects(self::exactly(2))->method('setItem');
        $manager = new Manager($remoteCache, $builder, $localCache);
        $manager->getConfiguration(Config::CONTEXT_DEFAULT, true);
        $manager->getConfiguration(Config::CONTEXT_DEFAULT, true);
    }

    public function testScopeNotStoredLocally()
    {
        $builder = $this->getBuilderMock(0);
        $remoteCache = $this->getCacheMock();
        $localCache = $this->getCacheMock();
        $remoteCache->expects(self::exactly(2))->method('getItem')->willReturn('<configuration></configuration>');
        $localCache->expects(self::exactly(1))->method('getItem')->willReturn(null);
        $localCache->expects(self::exactly(1))->method('setItem');
        $manager = new Manager($remoteCache, $builder, $localCache);
        $manager->getConfiguration(Config::CONTEXT_DEFAULT);
    }
    public function testDataRetrievedRemotelyStoredLocally()
    {
        $builder = $this->getBuilderMock(0);
        $remoteCache = $this->getCacheMock();
        $localCache = $this->getCacheMock();
        $remoteCache->expects(self::exactly(2))->method('getItem')->willReturn('<configuration></configuration>');
        $localCache->expects(self::exactly(1))->method('getItem')->willReturn(null);
        $localCache->expects(self::exactly(1))->method('setItem');
        $manager = new Manager($remoteCache, $builder, $localCache);
        $manager->getConfiguration(Config::CONTEXT_DEFAULT);
    }

    protected function getBuilderMock($buildCalls = 1)
    {
        $builder = $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->setMethods(['build'])->getMock();
        $builder->expects(self::exactly($buildCalls))->method('build')->willReturn(new Config('<config />'));
        /* @var $builder Builder */
        return $builder;
    }

    protected function getCacheMock()
    {
        $storage = $this->getMockBuilder(StorageInterface::class)->getMock();
        /* @var $storage StorageInterface */
        return $storage;
    }

}
