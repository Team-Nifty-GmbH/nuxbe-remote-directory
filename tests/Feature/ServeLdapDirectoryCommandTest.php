<?php

namespace NuxbeRemoteDirectory\Tests\Feature;

use NuxbeRemoteDirectory\Tests\TestCase;

/**
 * Only the guards are covered here. The success path enters a blocking accept
 * loop, so it is verified against a real client instead, not from a test.
 */
class ServeLdapDirectoryCommandTest extends TestCase
{
    public function test_refuses_to_start_without_an_account_and_without_anonymous_access(): void
    {
        config()->set('remote-directory.ldap.username', null);
        config()->set('remote-directory.ldap.password', null);
        config()->set('remote-directory.ldap.allow_anonymous', false);

        $this->artisan('remote-directory:ldap')->assertExitCode(1);
    }

    public function test_refuses_to_start_with_a_username_but_no_password(): void
    {
        config()->set('remote-directory.ldap.username', 'cn=phones,dc=flux,dc=local');
        config()->set('remote-directory.ldap.password', null);
        config()->set('remote-directory.ldap.allow_anonymous', false);

        $this->artisan('remote-directory:ldap')->assertExitCode(1);
    }
}
