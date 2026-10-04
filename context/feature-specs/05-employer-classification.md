# Employer Classification

Add the rule that decides if a sponsor is a tech employer, and why.

## Enum

`App\Enums\TechReason`: `Sic`, `Keyword`, `KnownEmployer` (see spec 02).

## Class

`App\Support\EmployerClassifier::classify(string $name, array $sicCodes): ?TechReason`

Checks run in this order and stop at the first hit:

1. Any SIC code is in `config('sponsor-finder.tech_sic_codes')`: `Sic`.
2. The normalised name (`CompanyName::normalise`) contains a whole word from `config('sponsor-finder.tech_keywords')` and no whole word from `config('sponsor-finder.noise_keywords')`: `Keyword`.
3. The normalised name equals a normalised entry in `config('sponsor-finder.known_employers')`: `KnownEmployer`.
4. Otherwise null.

## Rules

- Keep the classifier pure. No database or HTTP. Config is passed in or read once, so tests can set it.
- Match whole words only, so "ai" does not match "maintenance" and "web" does not match "webster".
- Only `active` companies continue in the pipeline. The enrich job checks status, not the classifier.

## Check When Done

- Unit tests for:
  - a tech SIC code gives `Sic`
  - mixed codes with one tech code give `Sic`
  - no tech code but a tech keyword in the name gives `Keyword`
  - a tech keyword plus a noise keyword gives null (for example "Digital Care Ltd")
  - a known employer gives `KnownEmployer`
  - no codes, no keyword, not known gives null
  - empty codes and an empty name give null
  - whole-word matching (for example "Maintenance Services" is not `Keyword`)
