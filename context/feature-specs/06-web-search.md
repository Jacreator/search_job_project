# Web Search Client

Build the search client only.

## Interface

`App\Contracts\WebSearch`:

- `search(string $query): array` returning a list of `SearchResult` (url, title).

## Implementation

`App\Services\BraveWebSearch`:

- `GET https://api.search.brave.com/res/v1/web/search`
- Header: `X-Subscription-Token`
- Params: `q`, `country=gb`, `count=10`
- Read results from `web.results`.

Bind the interface to the Brave class in a service provider.

## Query

`"{name} {town}"` using the original sponsor name.

## Check When Done

- `Http::fake()` test returns parsed results.
- Empty or malformed response returns an empty list, not an error.
