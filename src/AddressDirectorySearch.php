<?php

namespace NuxbeRemoteDirectory;

use FluxErp\Models\Address;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * The one directory query. Every protocol (XML phonebook, LDAP) asks the same
 * question, only the rendering differs.
 */
class AddressDirectorySearch
{
    /**
     * @return Collection<int, Address>
     */
    public function search(string $term, int $limit, int $page = 1): Collection
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term);

        return Address::query()
            ->where('is_active', true)
            ->when(
                $term !== '',
                fn (Builder $query) => $query->where(
                    fn (Builder $query) => $query
                        ->where('company', 'like', $term . '%')
                        ->orWhere('firstname', 'like', $term . '%')
                        ->orWhere('lastname', 'like', $term . '%')
                        ->when(
                            strlen((string) $digits) >= 3,
                            fn (Builder $query) => $query
                                ->orWhere('phone', 'like', '%' . $digits . '%')
                                ->orWhere('phone_mobile', 'like', '%' . $digits . '%')
                        )
                )
            )
            ->where(
                fn (Builder $query) => $query
                    ->whereNotNull('phone')
                    ->orWhereNotNull('phone_mobile')
            )
            ->orderBy('company')
            ->orderBy('lastname')
            ->orderBy('id')
            ->forPage(max(1, $page), $limit)
            ->get(['id', 'company', 'firstname', 'lastname', 'phone', 'phone_mobile']);
    }

    public function limit(?int $requested): int
    {
        $max = (int) config('remote-directory.max_limit');

        return max(1, min((int) ($requested ?: config('remote-directory.limit')), $max));
    }
}
