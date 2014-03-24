<?php
/**
/*
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
        $host = parse_url($event->getProcessedUrl(), PHP_URL_HOST);
        if ($host && !$this->io->hasAuthentication($host)) {
            try {
                $netrcParsed = Netrc::parse();
                if (isset($netrcParsed[$host]['login']) && isset($netrcParsed[$host]['password'])) {
                    $this->io->setAuthentication($host, $netrcParsed[$host]['login'], $netrcParsed[$host]['password']);
                }
            } catch (ParseException $ex) {
                // we cannot authorize current user via netrc
            }
        }
    }
}
 