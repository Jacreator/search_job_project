# Tech Classification

Add the rule that decides if a sponsor is a tech company.

## Class

`App\Support\TechClassifier::isTech(array $sicCodes): bool`

True when any code is in `config('sponsor-finder.tech_sic_codes')`.

## Rules

- Only `active` companies count as tech for the pipeline.
- Keep the classifier pure. No database or HTTP.

## Check When Done

- Unit tests for tech, non-tech, mixed codes, and empty codes.
