<?php

namespace Magium\Configuration\Tests\Factory;

use Magium\Configuration\Config\Builder;
use Magium\Configuration\Config\InvalidConfigurationLocationException;
use Magium\Configuration\Config\MissingConfigurationException;
use Magium\Configuration\File\Context\AbstractContextConfigurationFile;
use Magium\Configuration\InvalidConfigurationException;
use Magium\Configuration\InvalidConfigurationFileException;
use Magium\Configuration\MagiumConfigurationFactory;
use Magium\Configuration\Manager\Manager;
use PHPUnit\Framework\TestCase;
use Zend\Cache\Storage\StorageInterface;

class FactoryTest extends TestCase
{

    const CONFIG = 'magium-configuration.xml';

    protected $configFile = [];

    protected function setFile($contents = '<config />', $filename = self::CONFIG)
    {
        $this->configFile[$filename] = __DIR__ . '/../../' . $filename;
        file_put_contents($this->configFile[$filename], $contents);
        parent::setUp();
    }

    protected function tearDown()
    {
        foreach ($this->configFile as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    public function testExistingConfigFile()
    {
        $this->setFile();
        $path = realpath($this->configFile[self::CONFIG]);
        new MagiumConfigurationFactory($path);
    }

    public function testValidateDocumentSucceeds()
    {
        $this->setValidFile();
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = new MagiumConfigurationFactory($path);
        $result = $factory->validateConfigurationFile();
        self::assertTrue($result);
    }

    public function testLocalCacheIsCreatedIfItIsConfigured()
    {
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
    <cache>
        <adapter>filesystem</adapter>
    </cache>
    <localCache>
        <adapter>filesystem</adapter>
    </localCache>
</magium>

XML
        );
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = $this->getMockBuilder(
            MagiumConfigurationFactory::class
        )->setMethods(['getCache', 'getBuilder'])
            ->setConstructorArgs([$path])
            ->getMock();
        $factory->expects(self::exactly(2))->method('getCache')->willReturn(
            $this->getMockBuilder(StorageInterface::class)->disableOriginalConstructor()->getMock()
        );
        $factory->expects(self::exactly(1))->method('getBuilder')->willReturn(
            $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->getMock()
        );
        /* @var $factory MagiumConfigurationFactory */
        $manager = $factory->getManager();
        self::assertInstanceOf(Manager::class, $manager);
    }

    public function testLocalCacheIsNotCreatedIfItIsNotConfigured()
    {
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
    <cache>
        <adapter>filesystem</adapter>
    </cache>
</magium>

XML
        );
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = $this->getMockBuilder(
            MagiumConfigurationFactory::class
        )->setMethods(['getCache', 'getBuilder'])
            ->setConstructorArgs([$path])
            ->getMock();
        $factory->expects(self::exactly(1))->method('getCache')->willReturn(
            $this->getMockBuilder(StorageInterface::class)->disableOriginalConstructor()->getMock()
        );
        $factory->expects(self::exactly(1))->method('getBuilder')->willReturn(
            $this->getMockBuilder(Builder::class)->disableOriginalConstructor()->getMock()
        );
        /* @var $factory MagiumConfigurationFactory */
        $manager = $factory->getManager();
        self::assertInstanceOf(Manager::class, $manager);
    }

    protected function setContextFileConfiguration()
    {
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
    <contextConfigurationFile file="contexts.xml" type="xml"/>
</magium>

XML
        );
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<defaultContext xmlns="http://www.magiumlib.com/ConfigurationContext">
    <context id="production" title="Production">
        <context id="store1" title="Store 1" />
    </context>
    <context id="development" title="Development" />
</defaultContext>

XML
            ,'contexts.xml');
    }

    public function testFactoryParsesContextFileProperly()
    {
        $this->setContextFileConfiguration();
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = new MagiumConfigurationFactory($path);
        $context = $factory->getContextFile();
        self::assertInstanceOf(AbstractContextConfigurationFile::class, $context);
    }

    public function testMissingContextFileThrowsException()
    {
        $this->expectException(MissingConfigurationException::class);
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
    <contextConfigurationFile file="contexts.xml" type="xml"/>
</magium>

XML
        );
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = new MagiumConfigurationFactory($path);
        $factory->getContextFile();
    }

    public function testInvalidContextFileThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium >
    <contextConfigurationFile file="contexts.xml" type="AbstractContextConfiguration"/>
</magium>

XML
        );
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<defaultContext>
    
</defaultContext>
XML
            ,'contexts.xml');
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = new MagiumConfigurationFactory($path);
        $factory->getContextFile();
    }

    public function testValidateDocumentFailsWithImproperConfigFile()
    {
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<configuration>
    <contextConfigurationFile file="contexts.xml" />
    <cached />
</configuration>

XML
);
        $path = realpath($this->configFile[self::CONFIG]);
        $factory = new MagiumConfigurationFactory($path);
        $result = $factory->validateConfigurationFile();
        self::assertFalse($result);
    }

    public function testFindExistingConfigFile()
    {
        $this->setFile();
        new MagiumConfigurationFactory();
    }
    public function testInvalidConfigFileThrowsException()
    {
        $this->expectException(InvalidConfigurationFileException::class);
        new MagiumConfigurationFactory();
    }

    public function testInvalidBuilderFactoryTypeThrowsException()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
    <builderFactory class="ArrayObject" />
</magium>

XML
        );
        $factory = new MagiumConfigurationFactory();
        $factory->getBuilderFactory();
    }

    public function testGetManager()
    {
        $this->setValidFile();
        $factory = new MagiumConfigurationFactory();
        $manager = $factory->getManager();
        self::assertInstanceOf(Manager::class, $manager);
    }

    public function testInvalidBaseDirectoryThrowsException()
    {
        $base = $tmp = sys_get_temp_dir();
        $base .= DIRECTORY_SEPARATOR . 'remove-me'; // Won't exist
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
      <persistenceConfiguration>
        <driver>pdo_sqlite</driver>
        <database>:memory:</database>
    </persistenceConfiguration>
    <contextConfigurationFile file="contexts.xml" type="xml" />
    <configurationDirectories><directory>$base</directory></configurationDirectories>
    <cache>
        <adapter>filesystem</adapter>
        <options>
            <cache_dir>$tmp</cache_dir>
        </options>
    </cache>
</magium>

XML
        );
        $factory = new MagiumConfigurationFactory();
        $this->expectException(InvalidConfigurationLocationException::class);
        $factory->getManager();
    }

    protected function setValidFile()
    {
        $tmp = sys_get_temp_dir();
        $this->setFile(<<<XML
<?xml version="1.0" encoding="utf-8"?>
<magium xmlns="http://www.magiumlib.com/BaseConfiguration"
          xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:schemaLocation="http://www.magiumlib.com/BaseConfiguration">
      <persistenceConfiguration>
        <driver>pdo_sqlite</driver>
        <database>:memory:</database>
    </persistenceConfiguration>
    <contextConfigurationFile file="contexts.xml" type="xml"/>
    <cache>
        <adapter>filesystem</adapter>
        <options>
            <cache_dir>$tmp</cache_dir>
        </options>
    </cache>
</magium>

XML
        );
    }

}
