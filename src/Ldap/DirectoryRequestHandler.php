<?php

namespace NuxbeRemoteDirectory\Ldap;

use FluxErp\Models\Address;
use FreeDSx\Ldap\Entry\Entries;
use FreeDSx\Ldap\Entry\Entry;
use FreeDSx\Ldap\Operation\Request\SearchRequest;
use FreeDSx\Ldap\Search\Filter\FilterInterface;
use FreeDSx\Ldap\Server\RequestContext;
use FreeDSx\Ldap\Server\RequestHandler\GenericRequestHandler;
use NuxbeRemoteDirectory\AddressDirectorySearch;

/**
 * Answers the LDAP searches a phone sends. The server library evaluates
 * nothing on its own, so base DN, filter and size limit are handled here.
 *
 * Read only: every other operation stays with GenericRequestHandler, which
 * rejects it.
 */
class DirectoryRequestHandler extends GenericRequestHandler
{
    public function __construct(protected AddressDirectorySearch $directory) {}

    public function bind(string $username, string $password): bool
    {
        $expectedUser = (string) config('remote-directory.ldap.username');
        $expectedPassword = (string) config('remote-directory.ldap.password');

        if ($expectedUser === '' || $expectedPassword === '') {
            return false;
        }

        return hash_equals($expectedUser, $username)
            && hash_equals($expectedPassword, $password);
    }

    public function search(RequestContext $context, SearchRequest $search): Entries
    {
        $limit = $this->directory->limit($search->getSizeLimit() ?: null);
        $addresses = $this->directory->search($this->term($search->getFilter()), $limit);

        return new Entries(
            ...$addresses->map(fn (Address $address): Entry => $this->entry($address))->all()
        );
    }

    /**
     * A phone searches with a filter over several attributes at once, such as
     * (|(cn=wig*)(sn=wig*)) for a name or (|(telephoneNumber=0831*)(mobile=0831*))
     * for a number. Every branch carries the same typed term, so the longest
     * value in the filter is that term.
     *
     * ponytail: reading the values off the filter string covers every filter a
     * phone sends. Walking the filter tree only pays off once a client sends
     * branches that mean different things.
     */
    protected function term(FilterInterface $filter): string
    {
        preg_match_all('/([\w.;-]+)=([^)(]*)/', $filter->toString(), $matches, PREG_SET_ORDER);

        $values = [];
        foreach ($matches as [, $attribute, $value]) {
            if (strcasecmp($attribute, 'objectClass') === 0) {
                continue;
            }

            $value = trim(str_replace('*', '', $value));

            if ($value !== '') {
                $values[] = $value;
            }
        }

        usort($values, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $values[0] ?? '';
    }

    protected function entry(Address $address): Entry
    {
        $person = trim(trim((string) $address->firstname) . ' ' . trim((string) $address->lastname));
        $company = trim((string) $address->company);

        $attributes = array_filter([
            'cn' => $company !== '' && $person !== ''
                ? $company . ', ' . $person
                : ($company !== '' ? $company : $person),
            'o' => $company,
            'givenName' => trim((string) $address->firstname),
            'sn' => trim((string) $address->lastname),
            'telephoneNumber' => trim((string) $address->phone),
            'mobile' => trim((string) $address->phone_mobile),
        ], fn (string $value): bool => $value !== '');

        return Entry::fromArray(
            // The address id keeps the DN unique and free of characters that
            // would have to be escaped.
            'uid=' . $address->getKey() . ',' . config('remote-directory.ldap.base_dn'),
            $attributes
        );
    }
}
