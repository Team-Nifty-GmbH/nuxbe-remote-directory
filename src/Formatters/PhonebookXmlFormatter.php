<?php

namespace NuxbeRemoteDirectory\Formatters;

use DOMDocument;
use DOMElement;
use FluxErp\Models\Address;
use Illuminate\Support\Collection;

/**
 * Renders addresses as the XML remote phonebook desk phones understand
 * (Yealink, and Fanvil which reads the same DirectoryEntry structure).
 *
 * Kept apart from the controller so a second protocol (LDAP, CardDAV) only
 * needs its own formatter, not its own query.
 */
class PhonebookXmlFormatter
{
    protected const ROOT = 'YealinkIPPhoneDirectory';

    /**
     * @param  Collection<int, Address>  $addresses
     */
    public function format(Collection $addresses): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement(static::ROOT);
        $document->appendChild($root);

        foreach ($addresses as $address) {
            $numbers = array_values(array_filter([
                trim((string) $address->phone),
                trim((string) $address->phone_mobile),
            ]));

            if ($numbers === []) {
                continue;
            }

            $root->appendChild($this->entry($document, $this->name($address), $numbers));
        }

        return $document->saveXML();
    }

    /**
     * @param  array<int, string>  $numbers
     */
    protected function entry(DOMDocument $document, string $name, array $numbers): DOMElement
    {
        $entry = $document->createElement('DirectoryEntry');
        $entry->appendChild($document->createElement('Name', ''))->textContent = $name;

        foreach ($numbers as $number) {
            $entry->appendChild($document->createElement('Telephone', ''))->textContent = $number;
        }

        return $entry;
    }

    protected function name(Address $address): string
    {
        $person = trim(trim((string) $address->firstname) . ' ' . trim((string) $address->lastname));
        $company = trim((string) $address->company);

        if ($company !== '' && $person !== '') {
            return $company . ', ' . $person;
        }

        return $company !== '' ? $company : $person;
    }
}
