<?php

namespace Kaliop\Migration\Tests\Core;


use Doctrine\DBAL\Driver\Connection;
use Kaliop\Migration\Core\Configuration;
use Kaliop\Migration\Tests\BundleMigrationDBTestCase;
use MyProject\Proxies\__CG__\OtherProject\Proxies\__CG__\stdClass;
use org\bovigo\vfs\vfsStream;
use Symfony\Component\Console\Output\ConsoleOutput;

class ConfigurationTest extends BundleMigrationDBTestCase {
    /**
     * The BundleMigration Configuration instance.
     *
     * @var \Kaliop\Migration\Core\Configuration
     */
    protected $configuration;

    /**
     * The root VfsStreamDirectory
     *
     * @var \org\bovigo\vfs\vfsStream
     */
    private $root;

    public function setUp() {
        parent::setUp();

        $this->configuration = new Configuration( $this->connection, new ConsoleOutput() );

    }

    public function testGetConnection() {
        $conn = $this->configuration->getConnection();

        $this->assertTrue( $conn instanceof Connection );
    }

    public function testGetVersions() {
        $version = $this->configuration->getVersions();

        $this->assertTrue( is_array($version ) );
    }

    public function testCreateVersionTable()
    {
        $result = $this->configuration->createVersionTable();

        $this->assertTrue( $result );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getConnection()
     */
    public function testGetMigratedVersionsByBundle() {
        $this->configuration->createVersionTable();

        $conn = $this->configuration->getConnection();


        $time = date( 'YmdHis', time() );
        $bundle = 'testBundle';
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle, $time );

        $conn->executeQuery( $sql );

        /** @var $versions array */
        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 1, count( $versions ) );

        $this->assertEquals( $time, $versions[0] );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getConnection()
     * @covers \Kaliop\Migration\Core\Configuration::setVersions()
     */
    public function testGetCurrentVersionByBundle() {
        $this->configuration->createVersionTable();

        $conn = $this->configuration->getConnection();

        $time = date( 'YmdHis', time() - 3600 );
        $bundle = 'testBundle';

        $this->configuration->setVersions( array( $bundle => array() ) );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle, $time );

        $conn->executeQuery( $sql );

        $time = date( 'YmdHis', time() );
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle, $time );

        $conn->executeQuery( $sql );

        $version = $this->configuration->getCurrentVersionByBundle( $bundle );

        $this->assertEquals( (int)$time, $version );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getConnection()
     * @covers \Kaliop\Migration\Core\Configuration::setVersions()
     * @covers \Kaliop\Migration\Core\Configuration::getCurrentVersionByBundle()
     */
    public function testGetCurrentBundleVersions() {
        $this->configuration->createVersionTable();

        $conn = $this->configuration->getConnection();

        $time = date( 'YmdHis', time() - 3600 );
        $bundle1 = 'testBundle';
        $bundle2 = 'testBundle2';

        $this->configuration->setVersions( array( $bundle1 => array(), $bundle2 => array() ) );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time );

        $conn->executeQuery( $sql );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time );

        $conn->executeQuery( $sql );

        $time = date( 'YmdHis', time() );
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time );

        $conn->executeQuery( $sql );

        $time2 = date( 'YmdHis', time() );
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time2 );

        $conn->executeQuery( $sql );

        $versions = $this->configuration->getCurrentBundleVersions();

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 2, count( $versions ) );

        $this->assertEquals( (int)$time, $versions[$bundle1] );
        $this->assertEquals( (int)$time2, $versions[$bundle2] );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getConnection()
     */
    public function testGetMigratedVersions() {
        $this->configuration->createVersionTable();

        $conn = $this->configuration->getConnection();

        $time = date( 'YmdHis', time() - 3600 );
        $bundle1 = 'testBundle';
        $bundle2 = 'testBundle2';

        $this->configuration->setVersions( array( $bundle1 => array(), $bundle2 => array() ) );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time );

        $conn->executeQuery( $sql );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time );

        $conn->executeQuery( $sql );

        $time1 = date( 'YmdHis', time() );
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time1 );

        $conn->executeQuery( $sql );

        $time2 = date( 'YmdHis', time() );
        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time2 );

        $conn->executeQuery( $sql );

        $versions = $this->configuration->getMigratedVersions();

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 2, count( $versions ) );
        $this->assertArrayHasKey( $bundle1, $versions );
        $this->assertArrayHasKey( $bundle2, $versions );

        $this->assertTrue( is_array( $versions[$bundle1] ) );
        $this->assertTrue( is_array( $versions[$bundle2] ) );
        $this->assertEquals( 2, count( $versions[$bundle1] ) );
        $this->assertEquals( 2, count( $versions[$bundle2] ) );

        $this->assertEquals( $time, $versions[$bundle1][0] );
        $this->assertEquals( $time, $versions[$bundle2][0] );
        $this->assertEquals( $time1, $versions[$bundle1][1] );
        $this->assertEquals( $time2, $versions[$bundle2][1] );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getConnection()
     */
    public function testMigrationsToExecute() {
        $this->configuration->createVersionTable();

        $conn = $this->configuration->getConnection();

        $time = date( 'YmdHis', time() - 3600 );
        $time1 = date( 'YmdHis', time() );
        $time2 = date( 'YmdHis', time() + 3600 );
        $time3 = date( 'YmdHis', time() + 7200 );
        $bundle1 = 'testBundle';
        $bundle2 = 'testBundle2';

        $versions = array(
            $bundle1 => array(
                $time => new \stdClass(),
                $time1 => new \stdClass(),
                $time2 => new \stdClass()
            ),
            $bundle2 => array(
                $time => new \stdClass(),
                $time2 => new \stdClass(),
                $time3 => new \stdClass()
            )
        );

        $this->configuration->setVersions( $versions );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time );

        $conn->executeQuery( $sql );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time );

        $conn->executeQuery( $sql );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle1, $time1 );

        $conn->executeQuery( $sql );

        $sql = sprintf( "INSERT INTO %s ( 'bundle', 'version' ) VALUES( '%s', '%s' )", $this->configuration->versionTableName, $bundle2, $time2 );

        $conn->executeQuery( $sql );

        $results = $this->configuration->migrationsToExecute();

        $this->assertTrue( is_array( $results ) );
        $this->assertEquals( 2, count( $results ) );
        $this->assertArrayHasKey( $bundle1, $results );
        $this->assertArrayHasKey( $bundle2, $results );

        $this->assertEquals( 1, count( $results[$bundle1] ) );
        $this->assertEquals( $time2, key( $results[$bundle1] ) );
        $this->assertEquals( 1, count( $results[$bundle2] ) );
        $this->assertEquals( $time3, key( $results[$bundle2] ) );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::getMigratedVersionsByBundle()
     */
    public function testMarkVersionMigrated() {
        $this->configuration->createVersionTable();

        $bundle = 'testBundle';
        $version = '1';

        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );
        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 0, count( $versions ) );

        $this->configuration->markVersionMigrated( $bundle, $version );
        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 1, count( $versions ) );
        $this->assertEquals( $version, $versions[0] );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @covers \Kaliop\Migration\Core\Configuration::markVersionMigrated()
     * @covers \Kaliop\Migration\Core\Configuration::getMigratedVersionsByBundle()
     */
    public function testMarkVersionNotMigrated() {
        $this->configuration->createVersionTable();

        $bundle = 'testBundle';
        $version1 = '1';
        $version2 = '2';

        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );
        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 0, count( $versions ) );

        $this->configuration->markVersionMigrated( $bundle, $version1 );
        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 1, count( $versions ) );
        $this->assertEquals( $version1, $versions[0] );

        $this->configuration->markVersionMigrated( $bundle, $version2 );
        $this->configuration->markVersionNotMigrated( $bundle, $version1 );

        $versions = $this->configuration->getMigratedVersionsByBundle( $bundle );

        $this->assertTrue( is_array( $versions ) );
        $this->assertEquals( 1, count( $versions ) );
        $this->assertEquals( $version2, $versions[0] );
    }

    /**
     * @covers \Kaliop\Migration\Core\Configuration::createVersionTable()
     * @todo Work out how this could be tested.
     */
    public function testRegisterVersionFromDirectories() {
        try {
            $this->setupVfsStructure();
        } catch ( \Exception $e ) {
            var_dump( $e->getMessage() );
            $this->markTestSkipped( "The VfsStream bundle is not installed." );
        }

        $paths = array(
            'bundle1' => array( vfsStream::url( 'bundles/bundle1/MigrationVersions' ) ),
            'bundle2' => array( vfsStream::url( 'bundles/bundle2/MigrationVersions' ) )
        );

        $this->configuration->createVersionTable();

        $versions = $this->configuration->registerVersionFromDirectories( $paths );

        $this->markTestSkipped( "FIXME: figure out a way to test this" );
    }

    /**
     * @expectedexception \Exception
     */
    public function testCheckDuplicateVersion()
    {
        $this->configuration->setVersions( array( 'testBundle' => array( '1' ) ) );

        $this->configuration->checkDuplicateVersion( 'testBundle', '1' );
    }

    /**
     *
     */
    public function testRegisterVersionPHP() {

        try {
        $this->setupVfsStructure();
        } catch( \Exception $e ) {
            $this->markTestSkipped( 'Problem with VfsStream.' );
        }

        $path = vfsStream::url( 'bundles/bundle1/MigrationVersions/Version20010101010101.php' );
    }

    /**
     * Setup a virtual filesystem with dummy migration files.
     *
     * @throws \Exception
     */
    private function setupVfsStructure() {

        try {
            $structure = array(
                'bundle1' => array(
                    'MigrationVersions' => array(
                        'Version20010101010101.php' => '<?php namespace MigrationVersions\bundle1;'."\n" . $this->dummyVersionContent . "\n?>",
                        'Version20010101010102.php' => '<?php namespace MigrationVersions\bundle1;'."\n" . $this->dummyVersionContent . "\n?>",
                    )
                ),
                'bundle2' => array(
                    'MigrationVersions' => array(
                        'Version20010101010103.php' => '<?php namespace MigrationVersions\bundle2;'."\n" . $this->dummyVersionContent . "\n?>",
                    )
                )
            );
        } catch( Exception $e ) {
            throw new \Exception( $e->getMessage() );
        }

        $this->root = vfsStream::setup( 'bundles', null, $structure );
    }

    /**
     * Dummy file contents for unit testing.
     *
     * @var string
     */
    private $dummyVersionContent = 'use Kaliop\Migration\Interfaces\VersionInterface;
        use Symfony\Component\DependencyInjection\ContainerAwareInterface;

        class Version20131001144810 implements VersionInterface, ContainerAwareInterface
        {
            private $container;

            public function execute( Schema $schema, Connection $conn )
            {

            }

            public function setContainer( \Symfony\Component\DependencyInjection\ContainerInterface $container = null )
            {
                $this->container = $container;
            }
        }';

}