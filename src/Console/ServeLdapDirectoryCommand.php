<?php

namespace NuxbeRemoteDirectory\Console;

use FreeDSx\Ldap\LdapServer;
use Illuminate\Console\Command;
use NuxbeRemoteDirectory\Ldap\DirectoryRequestHandler;
use Psr\Log\LoggerInterface;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

class ServeLdapDirectoryCommand extends Command
{
    protected $signature = 'remote-directory:ldap';

    protected $description = 'Serve the Flux contacts as an LDAP directory for phone clients';

    public function handle(DirectoryRequestHandler $handler, LoggerInterface $logger): int
    {
        $config = config('remote-directory.ldap');

        if (! $config['allow_anonymous'] && (! $config['username'] || ! $config['password'])) {
            error('Set remote-directory.ldap.username and password, or allow anonymous binds. Without either, every bind is rejected.');

            return static::FAILURE;
        }

        if ($config['allow_anonymous']) {
            warning('Anonymous binds are allowed, anyone who reaches this port reads the whole directory.');
        }

        if (! $config['use_ssl'] && ! $config['ssl_cert']) {
            warning('Serving without a certificate, binds and searches travel in the clear.');
        }

        info('LDAP directory on ' . $config['ip'] . ':' . $config['port'] . ', base ' . $config['base_dn']);

        (new LdapServer([
            'ip' => $config['ip'],
            'port' => $config['port'],
            'allow_anonymous' => $config['allow_anonymous'],
            'require_authentication' => ! $config['allow_anonymous'],
            'dse_naming_contexts' => $config['base_dn'],
            'use_ssl' => $config['use_ssl'],
            'ssl_cert' => $config['ssl_cert'],
            'ssl_cert_key' => $config['ssl_cert_key'],
        ]))
            ->useRequestHandler($handler)
            // A listener that rejects binds is worth a log: without one, nobody
            // can tell afterwards who connected and what they searched for.
            ->useLogger($logger)
            ->run();

        return static::SUCCESS;
    }
}
