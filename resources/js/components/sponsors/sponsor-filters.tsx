import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import {
    statusLabels,
    visaFilterLabels,
} from '@/components/sponsors/sponsor-labels';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/sponsors';
import type {
    SponsorFilters as Filters,
    SponsorStatus,
    VisaSponsorshipFilter,
} from '@/types';

const ANY = 'any';

type Props = {
    teamSlug: string;
    filters: Filters;
    regions: string[];
};

export default function SponsorFilters({ teamSlug, filters, regions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState<string>(filters.status ?? ANY);
    const [town, setTown] = useState(filters.town ?? '');
    const [region, setRegion] = useState(filters.region ?? ANY);
    const [minConfidence, setMinConfidence] = useState(
        filters.min_confidence === null ? '' : String(filters.min_confidence),
    );
    const [tech, setTech] = useState(filters.tech);
    const [scaleUp, setScaleUp] = useState(filters.scale_up);
    const [ai, setAi] = useState(filters.ai);
    const [developerRoles, setDeveloperRoles] = useState(
        filters.developer_roles,
    );
    const [visa, setVisa] = useState<string>(filters.visa_sponsorship ?? ANY);
    const [ratingMissing, setRatingMissing] = useState(filters.rating_missing);
    const [needsSecondCheck, setNeedsSecondCheck] = useState(
        filters.needs_second_check,
    );

    // A region from the query string that the team no longer has stays selectable.
    const regionOptions =
        filters.region && !regions.includes(filters.region)
            ? [...regions, filters.region]
            : regions;

    const apply = (event: FormEvent) => {
        event.preventDefault();

        // Only filters that are switched on go in the query string.
        const query: Record<string, string> = {};

        if (search.trim() !== '') {
            query.search = search.trim();
        }

        if (status !== ANY) {
            query.status = status;
        }

        if (town.trim() !== '') {
            query.town = town.trim();
        }

        if (region !== ANY) {
            query.region = region;
        }

        if (minConfidence !== '') {
            query.min_confidence = minConfidence;
        }

        if (tech) {
            query.tech = '1';
        }

        if (scaleUp) {
            query.scale_up = '1';
        }

        if (ai) {
            query.ai = '1';
        }

        if (developerRoles) {
            query.developer_roles = '1';
        }

        if (visa !== ANY) {
            query.visa_sponsorship = visa;
        }

        if (ratingMissing) {
            query.rating_missing = '1';
        }

        if (needsSecondCheck) {
            query.needs_second_check = '1';
        }

        router.get(index.url(teamSlug), query, { preserveScroll: true });
    };

    return (
        <form
            onSubmit={apply}
            data-test="sponsor-filters"
            className="space-y-4 rounded-xl border p-4"
        >
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div className="grid gap-2">
                    <Label htmlFor="filter-search">Name</Label>
                    <Input
                        id="filter-search"
                        type="search"
                        value={search}
                        placeholder="Search by name"
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="filter-status">Status</Label>
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger id="filter-status" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any status</SelectItem>
                            {(Object.keys(statusLabels) as SponsorStatus[]).map(
                                (value) => (
                                    <SelectItem key={value} value={value}>
                                        {statusLabels[value]}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="filter-town">Town</Label>
                    <Input
                        id="filter-town"
                        value={town}
                        placeholder="Any town"
                        onChange={(event) => setTown(event.target.value)}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="filter-region">Region</Label>
                    <Select value={region} onValueChange={setRegion}>
                        <SelectTrigger id="filter-region" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any region</SelectItem>
                            {regionOptions.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="filter-visa">Visa sponsorship</Label>
                    <Select value={visa} onValueChange={setVisa}>
                        <SelectTrigger id="filter-visa" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANY}>Any</SelectItem>
                            {(
                                Object.keys(
                                    visaFilterLabels,
                                ) as VisaSponsorshipFilter[]
                            ).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {visaFilterLabels[value]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="filter-min-confidence">
                        Minimum confidence
                    </Label>
                    <Input
                        id="filter-min-confidence"
                        type="number"
                        min={0}
                        max={100}
                        step={1}
                        value={minConfidence}
                        placeholder="0 to 100"
                        onChange={(event) =>
                            setMinConfidence(event.target.value)
                        }
                    />
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={tech}
                            onCheckedChange={(checked) =>
                                setTech(checked === true)
                            }
                        />
                        Tech only
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={scaleUp}
                            onCheckedChange={(checked) =>
                                setScaleUp(checked === true)
                            }
                        />
                        Scale-up only
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={ai}
                            onCheckedChange={(checked) =>
                                setAi(checked === true)
                            }
                        />
                        AI only
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={developerRoles}
                            onCheckedChange={(checked) =>
                                setDeveloperRoles(checked === true)
                            }
                        />
                        Developer roles
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={ratingMissing}
                            onCheckedChange={(checked) =>
                                setRatingMissing(checked === true)
                            }
                        />
                        Rating missing
                    </Label>
                    <Label className="flex items-center gap-2 font-normal">
                        <Checkbox
                            checked={needsSecondCheck}
                            onCheckedChange={(checked) =>
                                setNeedsSecondCheck(checked === true)
                            }
                        />
                        Needs second check
                    </Label>
                </div>

                <div className="flex items-center gap-2">
                    <Button variant="ghost" asChild>
                        <Link href={index(teamSlug)} preserveScroll>
                            Clear
                        </Link>
                    </Button>
                    <Button type="submit" data-test="apply-filters-button">
                        Apply filters
                    </Button>
                </div>
            </div>
        </form>
    );
}
