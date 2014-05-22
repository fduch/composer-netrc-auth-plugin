<?php
/**
 * This file is part of the composer-netrc-auth-plugin package.
 *
 * (c) Alex Medvedev <alex.medwedew@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 5/21/14
 */

namespace Fduch\Composer\Plugin;

use Composer\Composer;
use Composer\IO\ConsoleIO;
use Composer\Plugin\PreFileDownloadEvent;

use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Output\OutputInterface;

use Fduch\Composer\Plugin\Exception\RuntimeException as PluginRuntimeException;

use PHPUnit_Framework_TestCase;

class NetrcAuthPluginTest extends PHPUnit_Framework_TestCase
{
    /**
     * Plugin instance
     *
     * @var \Fduch\Composer\Plugin\NetrcAuthPlugin | \PHPUnit_Framework_MockObject_MockObject
     */
    protected $plugin;

    protected $rfs;

    protected $io;

    protected $output;

    public function setUp()
    {
        /** @var \Composer\Util\RemoteFilesystem | \PHPUnit_Framework_MockObject_MockObject rfs */
        $this->rfs = $this->getMockBuilder("\Composer\Util\RemoteFilesystem")
            ->disableOriginalConstructor()
            ->getMock();

        $this->output = new StreamOutput(fopen('php://memory', 'w', false));

        $this->io = new ConsoleIO(new ArrayInput(array()),
            $this->output,
            new HelperSet());

        // mock original getParsedNetrc method in order avoid real netrc file parsing
        $this->plugin = $this->getMockBuilder("\Fduch\Composer\Plugin\NetrcAuthPlugin")
            ->setMethods(array("getParsedNetrc"))
            ->getMock();

        $this->plugin->activate(new Composer(), $this->io);
    }

    /**
     * @test
     */
    public function pluginDoesNothingWhenHostOfProcessedUrlIsUnreachable()
    {
        $event = new PreFileDownloadEvent("test", $this->rfs, 'test');
        $this->plugin->onPreFileDownload($event);
        $this->assertEmpty($this->getDisplay());
    }

    /**
     * @test
     */
    public function pluginReportsIfVerboseWhenHostOfProcessedUrlIsUnreachable()
    {
        $event = new PreFileDownloadEvent("test", $this->rfs, 'test');
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->plugin->onPreFileDownload($event);
        $this->assertTrue((bool)strstr($this->getDisplay(), "Unable to fetch host from processing url"));
    }

    /**
     * @test
     */
    public function pluginDoesNothingWhenUserIsAlreadyAuthenticated()
    {
        $hostToAuth = 'test.com';
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->io->setAuthentication($hostToAuth, "login", "pass");
        $this->plugin->onPreFileDownload($event);
        $this->assertEmpty($this->getDisplay());
    }

    /**
     * @test
     */
    public function pluginReportsIfVerboseWhenUserIsAlreadyAuthenticated()
    {
        $hostToAuth = 'test.com';
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->io->setAuthentication($hostToAuth, "login", "pass");
        $this->plugin->onPreFileDownload($event);
        $this->assertTrue((bool)strstr($this->getDisplay(), "User is already authenticated on"));
    }

    /**
     * @test
     * @dataProvider netrcWithNotEnoughCredentialsForHostProvider
     */
    public function pluginDoesNothingWhenThereIsNotEnoughCredentialsInNetrcForHost($host, array $netrcParsed)
    {
        $hostToAuth = 'test.com';
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->plugin->expects($this->once())
            ->method("getParsedNetrc")
            ->will($this->returnValue($netrcParsed));
        $this->plugin->onPreFileDownload($event);
        $this->assertEmpty($this->getDisplay());
    }

    /**
     * @test
     * @dataProvider netrcWithNotEnoughCredentialsForHostProvider
     */
    public function pluginReportsIfVerboseWhenThereIsNotEnoughCredentialsInNetrcForHost($host, array $netrcParsed)
    {
        $hostToAuth = 'test.com';
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->output->setVerbosity(OutputInterface::VERBOSITY_VERBOSE);
        $this->plugin->expects($this->once())
            ->method("getParsedNetrc")
            ->will($this->returnValue($netrcParsed));
        $this->plugin->onPreFileDownload($event);
        $this->assertTrue((bool)strstr($this->getDisplay(), "Unable to fetch user login or password for host"));
    }

    public function netrcWithNotEnoughCredentialsForHostProvider()
    {
        $result = array();
        // empty host
        $result[] = array("host", array("anotherHost" => array()));
        // empty login
        $result[] = array("host", array("host" => array("password" => "pass")));
        // empty password
        $result[] = array("host", array("host" => array("login" => "login")));

        return $result;
    }

    /**
     * @test
     */
    public function pluginAlwaysReportsWhenNetrcCannotBeParsed()
    {
        $hostToAuth = 'test.com';
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->plugin->expects($this->once())
            ->method("getParsedNetrc")
            ->will($this->throwException(new PluginRuntimeException("netrc cannot be parsed")));
        $this->plugin->onPreFileDownload($event);
        $this->assertTrue((bool)strstr($this->getDisplay(), "Is your netrc file valid?"));
    }

    /**
     * @test
     */
    public function pluginAlwaysReportsAndAuthenticatesIfPossible()
    {
        $hostToAuth    = 'test.com';
        $netrcLogin    = "login1";
        $netrcPassword = "pass1";
        $event = new PreFileDownloadEvent("test", $this->rfs, "http://$hostToAuth/test.php");
        $this->plugin->expects($this->once())
            ->method("getParsedNetrc")
            ->will($this->returnValue(array($hostToAuth => array("login" => $netrcLogin, "password" => $netrcPassword))));
        $this->plugin->onPreFileDownload($event);
        $this->assertTrue((bool)strstr($this->getDisplay(), "User successfully authenticated using netrc credentials"));
        $this->assertEquals(
            array("username" => $netrcLogin, "password" => $netrcPassword),
            $this->io->getAuthentication($hostToAuth)
        );
    }

    /**
     * Returns the display of the last console output.
     */
    protected function getDisplay()
    {
        rewind($this->output->getStream());
        return stream_get_contents($this->output->getStream());
    }
}
 