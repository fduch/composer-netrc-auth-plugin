<?php
/**
 * This file is part of the composer-netrc-auth-plugin package.
 *
 * (c) Alex Medvedev <alex.medwedew@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Date: 3/21/14
 */

namespace Fduch\Composer\Plugin;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PreFileDownloadEvent;

use Fduch\Netrc\Netrc;
use Fduch\Netrc\Exception\ParseException;

/**
 * Provides netrc-based authorization during composer file downloading process
 *
 * @author Alex Medvedev
 */
class NetrcAuthPlugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    /**
     * {inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            PluginEvents::PRE_FILE_DOWNLOAD => array(
                array('onPreFileDownload', 0)
            )
        );
    }

    /**
     * Tries to authorize user using netrc credentials
     *
     * @param PreFileDownloadEvent $event event contains information about host for authorization
     */
    public function onPreFileDownload(PreFileDownloadEvent $event)
    {
        // parse host
        $host = parse_url($event->getProcessedUrl(), PHP_URL_HOST);
        if (!$host) {
            if ($this->io->isVerbose()) {
                $this->io->write(sprintf("<warning>Cannot authenticate user via netrc credentials. Unable to fetch ".
                    "host from processing url: </warning><comment>%s</comment>", $event->getProcessedUrl()));
            }
            return;
        }

        // check that user is already authenticated
        if ($this->io->hasAuthentication($host)) {
            if ($this->io->isVerbose()) {
                $this->io->write(sprintf("    Skipping netrc authentication. User is already ".
                    "authenticated on <comment>%s</comment>", $host));
            }
            return;
        }

        // trying to authenticate user with netrc credentials
        try {
            $netrcParsed = Netrc::parse();
            if (isset($netrcParsed[$host]['login']) && isset($netrcParsed[$host]['password'])) {
                $this->io->setAuthentication($host, $netrcParsed[$host]['login'], $netrcParsed[$host]['password']);
                $this->io->write("    <info>User successfully authenticated using netrc credentials</info>");
            } else {
                if ($this->io->isVerbose()) {
                    $this->io->write(sprintf("<warning>Cannot authenticate user via netrc credentials. " .
                        "Unable to fetch user login or password for host </warning><comment>%s</comment>", $host));
                }
            }
        } catch (ParseException $ex) {
            // we cannot authorize current user via netrc
            $this->io->write("<warning>Cannot authenticate user via netrc credentials. Is your netrc file valid?</warning>");
        }
    }
}
 