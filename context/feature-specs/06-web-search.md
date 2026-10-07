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

An HTTP or connection error (after one retry for connection errors, 429, and 5xx) throws `App\Exceptions\WebSearchRequestFailed`, like the Companies House client, so the enrich job retries it and never mistakes it for "no website". Messages never include the key or the query.

## Query

`"{name} {town}"` using the original sponsor name, built by `App\Support\SearchQuery::for($name, $town)`. A missing town (`''`) gives the name alone.

## Check When Done

- `Http::fake()` test returns parsed results.
- Empty or malformed response returns an empty list, not an error.
