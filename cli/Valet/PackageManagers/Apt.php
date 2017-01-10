<?php

namespace Valet\PackageManagers;

use DomainException;
use Valet\CommandLine;
use Valet\Contracts\PackageManager;

class Apt implements PackageManager
{
    var $cli;

    /**
     * Create a new Apt instance.
     *
     * @param  CommandLine  $cli
     * @return void
     */
    function __construct(CommandLine $cli)
    {
        $this->cli = $cli;
    }

    /**
     * Get array of installed packages
     *
     * @param  string  $package
     * @return array
     */
    function packages($package)
    {
        $query = "dpkg -l {$package} | grep '^ii' | sed 's_  _\\t_g' | cut -f 2";

        return explode(PHP_EOL, $this->cli->run($query));
    }

    /**
     * Determine if the given package is installed.
     *
     * @param  string $package
     * @return bool
     */
    function installed($package)
    {
        return in_array($package, $this->packages($package));
    }

    /**
     * Ensure that the given package is installed.
     *
     * @param  string $package
     * @return void
     */
    function ensureInstalled($package)
    {
        if ($package === 'nginx') {
            $package = 'nginx-core';
        }

        if (! $this->installed($package)) {
            $this->installOrFail($package);
        }
    }

    /**
     * Install the given package and throw an exception on failure.
     *
     * @param  string $package
     * @return void
     */
    function installOrFail($package)
    {
        output('<info>['.$package.'] is not installed, installing it now via Apt...</info> 🍻');

        $this->cli->run(trim('apt-get install -y '.$package), function ($exitCode, $errorOutput) use ($package) {
            output($errorOutput);

            throw new DomainException('Apt was unable to install ['.$package.'].');
        });
    }

    /**
     * Configure package manager on valet install.
     *
     * @return void
     */
    function setup()
    {
        // Nothing to do
    }

    /**
     * Get installed PHP version.
     *
     * @return string
     */
    function getPHPVersion()
    {
        $packages = $this->packages('php*cli');

        return explode('php', strtok($packages[0], '-'))[1];
    }

    /**
     * Setup dnsmasq in Ubuntu.
     */
    function dnsmasqSetup($sm)
    {
        $this->ensureInstalled('dnsmasq');

        /* I don't want you to lose your network connection
         * everytime we update the domain so lets remove
         * the Dnsmasq control from NetworkManager
         */
        $conf = '/etc/NetworkManager/NetworkManager.conf';

        $inControlOfNetworkManager = !empty($this->cli->run("grep '^dns=dnsmasq' $conf"));

        if ($inControlOfNetworkManager) {
            $this->cli->run("sudo sed -i 's/^dns=/#dns=/g' $conf");

            $sm->stop('network-manager');
            $this->cli->run('sudo pkill dnsmasq');
            $sm->start('network-manager');
            $this->dnsmasqRestart($sm);
        }
    }

    /**
     * Restart dnsmasq in Ubuntu.
     */
    function dnsmasqRestart($sm)
    {
        $sm->restart('dnsmasq');
    }

    /**
     * Dnsmasq config path distro.
     *
     * @return string
     */
    function dnsmasqConfigPath()
    {
        return '/etc/dnsmasq.d/valet';
    }

    /**
     * Determine if package manager is available on the system.
     *
     * @return bool
     */
    function isAvailable()
    {
        try {
            $output = $this->cli->run('which apt-get', function ($exitCode, $output) {
                throw new DomainException('Apt not available');
            });

            return $output != '';
        } catch (DomainException $e) {
            return false;
        }
    }
}
