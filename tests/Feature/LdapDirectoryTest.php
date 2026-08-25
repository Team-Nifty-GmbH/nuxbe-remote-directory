<?php

namespace NuxbeRemoteDirectory\Tests\Feature;

use FluxErp\Models\Address;
use FluxErp\Models\Contact;
use FreeDSx\Ldap\Control\ControlBag;
use FreeDSx\Ldap\Operation\Request\SearchRequest;
use FreeDSx\Ldap\Search\Filters;
use FreeDSx\Ldap\Server\RequestContext;
use FreeDSx\Ldap\Server\Token\BindToken;
use NuxbeRemoteDirectory\Ldap\DirectoryRequestHandler;
use NuxbeRemoteDirectory\Tests\TestCase;

class LdapDirectoryTest extends TestCase
{
    public function test_binds_with_the_configured_account_only(): void
    {
        $handler = app(DirectoryRequestHandler::class);

        $this->assertTrue($handler->bind('cn=phones,dc=flux,dc=local', 'phone-secret'));
        $this->assertFalse($handler->bind('cn=phones,dc=flux,dc=local', 'wrong'));
        $this->assertFalse($handler->bind('cn=someone,dc=flux,dc=local', 'phone-secret'));
    }

    public function test_rejects_every_bind_while_no_account_is_configured(): void
    {
        config()->set('remote-directory.ldap.username', null);
        config()->set('remote-directory.ldap.password', null);

        $this->assertFalse(app(DirectoryRequestHandler::class)->bind('', ''));
    }

    public function test_answers_a_name_search_the_way_a_phone_sends_it(): void
    {
        $address = $this->address([
            'company' => 'Wigwam GmbH',
            'firstname' => 'Erika',
            'lastname' => 'Mustermann',
            'phone' => '+49 831 1234567',
            'phone_mobile' => '+49 175 5867488',
        ]);
        $this->address(['company' => 'Tipi AG', 'phone' => '+49 89 999999']);

        // What a Yealink sends for the typed "wig".
        $entries = $this->search(Filters::or(
            Filters::startsWith('cn', 'wig'),
            Filters::startsWith('sn', 'wig')
        ));

        $this->assertCount(1, $entries);

        $entry = iterator_to_array($entries)[0];

        $this->assertSame('uid=' . $address->getKey() . ',dc=flux,dc=local', (string) $entry->getDn());
        $this->assertSame('Wigwam GmbH, Erika Mustermann', $entry->get('cn')->firstValue());
        $this->assertSame('Mustermann', $entry->get('sn')->firstValue());
        $this->assertSame('Erika', $entry->get('givenName')->firstValue());
        $this->assertSame('Wigwam GmbH', $entry->get('o')->firstValue());
        $this->assertSame('+49 831 1234567', $entry->get('telephoneNumber')->firstValue());
        $this->assertSame('+49 175 5867488', $entry->get('mobile')->firstValue());
    }

    public function test_answers_a_number_search(): void
    {
        $this->address([
            'company' => 'Wigwam GmbH',
            'firstname' => null,
            'lastname' => null,
            'phone' => '+49 831 1234567',
        ]);
        $this->address(['company' => 'Tipi AG', 'phone' => '+49 89 999999']);

        $entries = $this->search(Filters::or(
            Filters::startsWith('telephoneNumber', '1234567'),
            Filters::startsWith('mobile', '1234567')
        ));

        $this->assertCount(1, $entries);
        $this->assertSame('Wigwam GmbH', iterator_to_array($entries)[0]->get('cn')->firstValue());
    }

    public function test_leaves_out_attributes_the_address_does_not_have(): void
    {
        $this->address([
            'company' => 'Wigwam GmbH',
            'firstname' => null,
            'lastname' => null,
            'phone' => '+49 831 1234567',
            'phone_mobile' => null,
        ]);

        $entry = iterator_to_array($this->search(Filters::startsWith('cn', 'wig')))[0];

        $this->assertSame('Wigwam GmbH', $entry->get('cn')->firstValue());
        $this->assertNull($entry->get('sn'));
        $this->assertNull($entry->get('givenName'));
        $this->assertNull($entry->get('mobile'));
    }

    public function test_honours_the_size_limit_the_client_asks_for(): void
    {
        Address::factory()
            ->count(3)
            ->create([
                'contact_id' => Contact::factory()->create()->getKey(),
                'company' => 'Wigwam GmbH',
                'phone' => '+49 831 1234567',
                'is_active' => true,
            ]);

        $this->assertCount(2, $this->search(Filters::startsWith('cn', 'wig'), sizeLimit: 2));
        $this->assertCount(3, $this->search(Filters::startsWith('cn', 'wig')));
    }

    public function test_skips_inactive_addresses_and_addresses_without_a_number(): void
    {
        $this->address(['company' => 'Wigwam Inaktiv', 'phone' => '+49 831 1', 'is_active' => false]);
        $this->address(['company' => 'Wigwam Ohne Nummer', 'phone' => null, 'phone_mobile' => null]);

        $this->assertCount(0, $this->search(Filters::startsWith('cn', 'wig')));
    }

    protected function search($filter, int $sizeLimit = 0)
    {
        $request = (new SearchRequest($filter))
            ->base('dc=flux,dc=local')
            ->useSubtreeScope();

        if ($sizeLimit > 0) {
            $request->sizeLimit($sizeLimit);
        }

        return app(DirectoryRequestHandler::class)->search(
            new RequestContext(new ControlBag(), new BindToken('cn=phones,dc=flux,dc=local', 'phone-secret')),
            $request
        );
    }

    protected function address(array $attributes): Address
    {
        return Address::factory()->create($attributes + [
            'contact_id' => Contact::factory()->create()->getKey(),
            'is_active' => true,
        ]);
    }
}
