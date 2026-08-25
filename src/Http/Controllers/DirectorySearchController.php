<?php

namespace NuxbeRemoteDirectory\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use NuxbeRemoteDirectory\AddressDirectorySearch;
use NuxbeRemoteDirectory\Formatters\PhonebookXmlFormatter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Directory search for phone clients: takes a query (name, company or number)
 * and answers with a phonebook the phone can render.
 */
class DirectorySearchController extends Controller
{
    public function __invoke(
        Request $request,
        AddressDirectorySearch $search,
        PhonebookXmlFormatter $formatter
    ): Response {
        if (! $this->isAuthorized($request)) {
            return response('', 401);
        }

        $addresses = $search->search(
            (string) $request->query('q', ''),
            $search->limit((int) $request->query('limit')),
            (int) $request->query('page', 1)
        );

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
}
