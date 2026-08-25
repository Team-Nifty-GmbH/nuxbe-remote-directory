# nuxbe-remote-directory

Serves Flux ERP contacts as a **remote directory** for phone clients. A desk phone
or softphone searches Flux directly ("wigwam" finds the Wigwam GmbH entry) instead
of a phonebook that has to be exported and kept in sync.

This is the opposite direction of the caller name lookup in
[`team-nifty-gmbh/nuxbe-freepbx`](https://github.com/Team-Nifty-GmbH/nuxbe-freepbx),
which resolves an incoming number into a name. Here the client sends a search term
and receives matching contacts with their numbers.

## Installation

```bash
composer require team-nifty-gmbh/nuxbe-remote-directory
php artisan vendor:publish --tag=nuxbe-remote-directory-config
```

Set the shared token in the app `.env`:

```dotenv
REMOTE_DIRECTORY_TOKEN=some-long-random-string
REMOTE_DIRECTORY_LIMIT=50
REMOTE_DIRECTORY_MAX_LIMIT=200
```

## Endpoint

```
GET /api/remote-directory/search?q=<term>&limit=<n>&page=<n>
```

| Parameter | Meaning |
|-----------|---------|
| `q`       | Search term: company, firstname, lastname (prefix match) or a number with at least 3 digits (contains match). Empty returns the first page of the whole directory. |
| `limit`   | Entries per page, defaults to `remote-directory.limit`, capped by `remote-directory.max_limit`. |
| `page`    | 1 based page number. |
| `token`   | The shared token, when the client cannot send an `Authorization: Bearer` header. |

Authentication is the bearer token, or the same value as a `token` query
parameter. Desk phones fetch a plain URL and cannot set headers, which is why the
query parameter exists.

Only active addresses that carry at least one number are returned.

### Response

XML phonebook, `Content-Type: text/xml; charset=utf-8`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<YealinkIPPhoneDirectory>
  <DirectoryEntry>
    <Name>Wigwam GmbH, Erika Mustermann</Name>
    <Telephone>+49 831 1234567</Telephone>
    <Telephone>+49 175 5867488</Telephone>
  </DirectoryEntry>
</YealinkIPPhoneDirectory>
```

Fanvil reads the same `DirectoryEntry` structure. Should a client insist on the
`IPPhoneDirectory` root element, that is the single constant
`PhonebookXmlFormatter::ROOT`.

## Phone configuration (Yealink)

Remote phonebook URL:

```
https://<flux-host>/api/remote-directory/search?token=<REMOTE_DIRECTORY_TOKEN>
```

Remote search URL (the phone appends the typed term):

```
https://<flux-host>/api/remote-directory/search?token=<REMOTE_DIRECTORY_TOKEN>&q=
```

## LDAP directory

Clients that speak LDAP rather than an XML phonebook (many desk phones, Bria)
use the second protocol. It is a listener, not a route, so it runs as its own
process:

```bash
php artisan remote-directory:ldap
```

```dotenv
REMOTE_DIRECTORY_LDAP_IP=0.0.0.0
REMOTE_DIRECTORY_LDAP_PORT=389
REMOTE_DIRECTORY_LDAP_BASE_DN="dc=flux,dc=local"
REMOTE_DIRECTORY_LDAP_USERNAME="cn=phones,dc=flux,dc=local"
REMOTE_DIRECTORY_LDAP_PASSWORD=some-long-random-string
```

Phone side (Yealink wording, Fanvil is the same with other labels):

| Setting | Value |
|---------|-------|
| Server / Port | the Flux host, `389` |
| Base | the value of `REMOTE_DIRECTORY_LDAP_BASE_DN` |
| Username / Password | the values above |
| Name filter | `(\|(cn=%)(sn=%))` |
| Number filter | `(\|(telephoneNumber=%)(mobile=%))` |
| Name attributes | `cn sn givenName` |
| Number attributes | `telephoneNumber mobile` |
| Version | LDAP 3 |

An entry carries `cn` (company and person), `o`, `givenName`, `sn`,
`telephoneNumber` and `mobile`, and empty fields are left out. The DN is
`uid=<address id>,<base dn>`.

The listener serves one bind account. Without `REMOTE_DIRECTORY_LDAP_USE_SSL`
or a certificate in `REMOTE_DIRECTORY_LDAP_SSL_CERT`, that password and every
search travel in the clear, so either terminate TLS or keep the port on the
network the phones sit on. Anonymous binds are off unless
`REMOTE_DIRECTORY_LDAP_ALLOW_ANONYMOUS=true`, which hands the directory to
anyone who reaches the port.

The server forks one process per connection and needs `ext-pcntl` and
`ext-posix`. On Forge it belongs in a daemon, not in the deploy script.

## Adding another protocol

The query lives in `AddressDirectorySearch` and is shared by both protocols.
The XML side renders in `Formatters/PhonebookXmlFormatter`, the LDAP side in
`Ldap/DirectoryRequestHandler`. A third protocol writes its own renderer and
reuses the same query.

## Tests

The package tests run against MySQL, because Flux core ships MySQL only
migrations. Point `DB_HOST` / `DB_PORT` / `DB_DATABASE` in `phpunit.xml` at a
throwaway database, then:

```bash
composer install
composer test
```
