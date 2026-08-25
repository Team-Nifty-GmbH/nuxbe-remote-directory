<?php

namespace NuxbeRemoteDirectory\Tests\Feature;

use FluxErp\Models\Address;
use FluxErp\Models\Contact;
use NuxbeRemoteDirectory\Tests\TestCase;
use SimpleXMLElement;

class DirectorySearchTest extends TestCase
{
    public function test_rejects_a_request_without_a_token(): void
    {
        $this->get('/api/remote-directory/search?q=wigwam')
            ->assertUnauthorized();
    }

    public function test_rejects_a_wrong_token(): void
    {
        $this->withToken('nope')
            ->get('/api/remote-directory/search?q=wigwam')
            ->assertUnauthorized();
    }

    public function test_returns_the_matching_company_as_phonebook_xml(): void
    {
        $this->address([
            'company' => 'Wigwam GmbH',
            'firstname' => 'Erika',
            'lastname' => 'Mustermann',
            'phone' => '+49 831 1234567',
            'phone_mobile' => '+49 175 5867488',
        ]);
        $this->address([
            'company' => 'Tipi AG',
            'firstname' => 'Max',
            'lastname' => 'Beispiel',
            'phone' => '+49 89 999999',
        ]);

        $response = $this->withToken('test-token')
            ->get('/api/remote-directory/search?q=wigwam')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/xml; charset=utf-8');

        $xml = new SimpleXMLElement($response->getContent());

        $this->assertSame('YealinkIPPhoneDirectory', $xml->getName());
        $this->assertCount(1, $xml->DirectoryEntry);
        $this->assertSame('Wigwam GmbH, Erika Mustermann', (string) $xml->DirectoryEntry[0]->Name);
        $this->assertSame(
            ['+49 831 1234567', '+49 175 5867488'],
            array_map('strval', iterator_to_array($xml->DirectoryEntry[0]->Telephone, false))
        );
    }

    public function test_accepts_the_token_as_a_query_param_and_searches_by_number(): void
    {
        $this->address([
            'company' => 'Wigwam GmbH',
            'firstname' => null,
            'lastname' => null,
            'phone' => '+49 831 1234567',
            'phone_mobile' => null,
        ]);

        $response = $this->get('/api/remote-directory/search?q=1234567&token=test-token')
            ->assertOk();

        $xml = new SimpleXMLElement($response->getContent());

        $this->assertCount(1, $xml->DirectoryEntry);
        $this->assertSame('Wigwam GmbH', (string) $xml->DirectoryEntry[0]->Name);
    }

    public function test_skips_inactive_addresses_and_addresses_without_a_number(): void
    {
        $this->address([
            'company' => 'Wigwam Inaktiv',
            'phone' => '+49 831 1',
            'is_active' => false,
        ]);
        $this->address([
            'company' => 'Wigwam Ohne Nummer',
            'phone' => null,
            'phone_mobile' => null,
        ]);

        $response = $this->withToken('test-token')
            ->get('/api/remote-directory/search?q=wigwam')
            ->assertOk();

        $this->assertCount(0, (new SimpleXMLElement($response->getContent()))->DirectoryEntry);
    }

    public function test_caps_the_limit_a_client_may_ask_for(): void
    {
        Address::factory()
            ->count(3)
            ->create([
                'contact_id' => Contact::factory()->create()->getKey(),
                'company' => 'Wigwam GmbH',
                'phone' => '+49 831 1234567',
                'is_active' => true,
            ]);

        $response = $this->withToken('test-token')
            ->get('/api/remote-directory/search?q=wigwam&limit=999999')
            ->assertOk();

        $this->assertCount(3, (new SimpleXMLElement($response->getContent()))->DirectoryEntry);
    }

    protected function address(array $attributes): Address
    {
        return Address::factory()->create($attributes + [
            'contact_id' => Contact::factory()->create()->getKey(),
            'is_active' => true,
        ]);
    }
}
