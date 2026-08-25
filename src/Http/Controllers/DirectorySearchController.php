<?php

namespace NuxbeRemoteDirectory\Http\Controllers;

use FluxErp\Models\Address;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NuxbeRemoteDirectory\Formatters\PhonebookXmlFormatter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directory search for phone clients: takes a query (name, company or number)
 * and answers with a phonebook the phone can render.
 */
class DirectorySearchController extends Controller
{
    public function __invoke(Request $request, PhonebookXmlFormatter $formatter): Response
    {
        if (! $this->isAuthorized($request)) {
            return response('', 401);
        }

        $term = trim((string) $request->query('q', ''));
        $digits = preg_replace('/\D+/', '', $term);
        $limit = $this->limit($request);
        $page = max(1, (int) $request->query('page', 1));

        $addresses = Address::query()
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
            ->forPage($page, $limit)
            ->get(['id', 'company', 'firstname', 'lastname', 'phone', 'phone_mobile']);

        return response($formatter->format($addresses), 200)
            ->header('Content-Type', 'text/xml; charset=utf-8');
    }

    protected function isAuthorized(Request $request): bool
    {
        $token = config('remote-directory.token');
        // Desk phones fetch the directory by plain URL and cannot set a header,
        // so the token is accepted as a query param as well.
        $sent = $request->bearerToken() ?: $request->query('token');

        return (bool) $token && hash_equals((string) $token, (string) $sent);
    }

    protected function limit(Request $request): int
    {
        $max = (int) config('remote-directory.max_limit');
        $requested = (int) $request->query('limit', config('remote-directory.limit'));

        return max(1, min($requested, $max));
    }
}
