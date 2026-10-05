<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | API Keys
    |--------------------------------------------------------------------------
    |
    | Shared by every team. Never log these or send them to the frontend.
    | Trimmed, so a stray space or tab in .env does not break the key.
    |
    */

    'companies_house_key' => trim((string) env('COMPANIES_HOUSE_KEY')) ?: null,

    'brave_key' => trim((string) env('BRAVE_SEARCH_KEY')) ?: null,

    /*
    |--------------------------------------------------------------------------
    | Companies House
    |--------------------------------------------------------------------------
    |
    | REST API base URL. Empty means the live API.
    |
    */

    'companies_house_url' => rtrim(trim((string) env('COMPANIES_HOUSE_URL')), '/')
        ?: 'https://api.company-information.service.gov.uk',

    /*
    |--------------------------------------------------------------------------
    | Batching And Rate Limits
    |--------------------------------------------------------------------------
    |
    | Empty values in .env fall back to the defaults, because .env.example
    | lists these keys with no value.
    |
    */

    'batch_size' => (int) (env('SPONSOR_BATCH_SIZE') ?: 100),

    'rate_per_minute' => (int) (env('SPONSOR_RATE_PER_MINUTE') ?: 30),

    /*
    |--------------------------------------------------------------------------
    | Worker Routes
    |--------------------------------------------------------------------------
    |
    | Register rows whose Route is in this list are imported. SPONSOR_ROUTES
    | is a comma-separated list. Names must match the register CSV exactly.
    |
    */

    'routes' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SPONSOR_ROUTES', '')),
    ))) ?: [
        'Skilled Worker',
        'Global Business Mobility: Senior or Specialist Worker',
        'Global Business Mobility: Graduate Trainee',
        'Scale-up',
    ],

    // GOV.UK content API for the "Register of licensed sponsors: workers"
    // publication. sponsors:import downloads the latest CSV from it when no
    // file is given.
    'register_content_url' => 'https://www.gov.uk/api/content/government/publications/register-of-licensed-sponsors-workers',

    /*
    |--------------------------------------------------------------------------
    | Rating
    |--------------------------------------------------------------------------
    |
    | B-rated sponsors are skipped at import, because as far as we know they
    | cannot issue new certificates of sponsorship. Empty means the default.
    |
    */

    'skip_b_rated' => in_array(env('SPONSOR_SKIP_B_RATED'), [null, ''], true)
        ? true
        : filter_var(env('SPONSOR_SKIP_B_RATED'), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Employer Classification
    |--------------------------------------------------------------------------
    */

    // Strings, because Companies House returns SIC codes as strings.
    'tech_sic_codes' => [
        '62011', '62012', '62020', '62030', '62090',
        '63110', '63120', '58210', '58290', '72190',
    ],

    'tech_keywords' => [
        'software', 'tech', 'technology', 'technologies', 'digital', 'data',
        'ai', 'cloud', 'cyber', 'analytics', 'computing', 'devops', 'saas',
        'fintech', 'web', 'apps', 'developer',
    ],

    'noise_keywords' => [
        'care', 'nursing', 'food', 'restaurant', 'construction', 'cleaning',
        'recruitment', 'beauty', 'logistics', 'transport', 'dental',
        'pharmacy', 'school', 'property',
    ],

    // Starter list. Check each name against the latest register CSV and add
    // more as needed. Matching uses the full register name, ignoring only
    // letter case and extra spaces, so suffixes like "Limited" must match.
    'known_employers' => [
        'Sky UK Limited',
        'British Telecommunications plc',
        'Barclays Bank UK PLC',
        'Lloyds Bank plc',
        'HSBC UK Bank plc',
        'National Westminster Bank plc',
        'Santander UK plc',
        'Nationwide Building Society',
        'Yorkshire Building Society',
        'Leeds Building Society',
        'Skipton Building Society',
        'Jet2.com Limited',
        'Asda Stores Limited',
        'Wm Morrison Supermarkets Limited',
        'Tesco Stores Limited',
        "Sainsbury's Supermarkets Ltd",
        'Marks and Spencer plc',
        'British Broadcasting Corporation',
        'Rightmove plc',
        'Capita plc',
        'Accenture (UK) Limited',
        'Capgemini UK plc',
        'Deloitte LLP',
        'PricewaterhouseCoopers LLP',
        'Ernst & Young LLP',
        'KPMG LLP',
        'Infosys Limited',
        'Tata Consultancy Services Limited',
        'Wipro Limited',
        'Cognizant Worldwide Limited',
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Locations
    |--------------------------------------------------------------------------
    |
    | Town => region and priority. Higher priority is processed first. Towns
    | not listed get region null and priority 0.
    |
    */

    'priority_locations' => [
        'Sheffield' => ['region' => 'Yorkshire', 'priority' => 100],
        'Rotherham' => ['region' => 'Yorkshire', 'priority' => 90],
        'Doncaster' => ['region' => 'Yorkshire', 'priority' => 90],
        'Barnsley' => ['region' => 'Yorkshire', 'priority' => 90],
        'Chesterfield' => ['region' => 'East Midlands', 'priority' => 85],
        'Leeds' => ['region' => 'Yorkshire', 'priority' => 80],
        'Wakefield' => ['region' => 'Yorkshire', 'priority' => 80],
        'Bradford' => ['region' => 'Yorkshire', 'priority' => 75],
        'Huddersfield' => ['region' => 'Yorkshire', 'priority' => 75],
        'York' => ['region' => 'Yorkshire', 'priority' => 70],
        'Hull' => ['region' => 'Yorkshire', 'priority' => 70],
        'Manchester' => ['region' => 'North West', 'priority' => 60],
    ],

];
