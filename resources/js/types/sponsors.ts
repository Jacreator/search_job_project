export type SponsorStatus =
    | 'pending'
    | 'ch_done'
    | 'done'
    | 'skipped'
    | 'failed';

export type SkipReason = 'no_match' | 'not_tech' | 'inactive' | 'b_rating';

export type RatingGrade = 'A' | 'B';

export type Sponsor = {
    id: number;
    name: string;
    town: string | null;
    region: string | null;
    rating_grade: RatingGrade | null;
    rating_grade_manual: boolean;
    sic_codes: string[];
    website: string | null;
    confidence: number | null;
    confirmed: boolean;
    needs_second_check: boolean;
    status: SponsorStatus;
    skip_reason: SkipReason | null;
    is_scale_up: boolean;
    is_ai: boolean;
};

export type SponsorFilters = {
    status: SponsorStatus | null;
    town: string | null;
    region: string | null;
    min_confidence: number | null;
    tech: boolean;
    scale_up: boolean;
    rating_missing: boolean;
    needs_second_check: boolean;
    ai: boolean;
    search: string | null;
};

export type SponsorCount<T extends string> = {
    value: T;
    count: number;
};

export type SponsorPermissions = {
    canEdit: boolean;
    canSetRatings: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
