import { Head, Link } from '@inertiajs/react';
import SponsorFilters from '@/components/sponsors/sponsor-filters';
import { formatCount } from '@/components/sponsors/sponsor-labels';
import SponsorRow from '@/components/sponsors/sponsor-row';
import SponsorSummary from '@/components/sponsors/sponsor-summary';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/sponsors';
import type {
    Paginated,
    SkipReason,
    Sponsor,
    SponsorCount,
    SponsorFilters as Filters,
    SponsorPermissions,
    SponsorStatus,
} from '@/types';

type Props = {
    team: { name: string; slug: string };
    sponsors: Paginated<Sponsor>;
    filters: Filters;
    statusCounts: SponsorCount<SponsorStatus>[];
    skipReasonCounts: SponsorCount<SkipReason>[];
    regions: string[];
    permissions: SponsorPermissions;
};

const columns = [
    { title: 'Name' },
    { title: 'Town' },
    { title: 'Region' },
    { title: 'Rating' },
    { title: 'SIC codes' },
    { title: 'Website' },
    { title: 'Confidence', className: 'text-right' },
    { title: 'Status' },
    { title: 'Confirmed' },
    { title: 'Careers' },
    { title: 'Developer roles' },
];

export default function SponsorsIndex({
    team,
    sponsors,
    filters,
    statusCounts,
    skipReasonCounts,
    regions,
    permissions,
}: Props) {
    return (
        <>
            <Head title="Sponsors" />

            <h1 className="sr-only">Sponsors</h1>

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <SponsorSummary
                    statusCounts={statusCounts}
                    skipReasonCounts={skipReasonCounts}
                />

                {/* Keyed by the applied filters, so the form resets when they change. */}
                <SponsorFilters
                    key={JSON.stringify(filters)}
                    teamSlug={team.slug}
                    filters={filters}
                    regions={regions}
                />

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                {columns.map((column) => (
                                    <th
                                        key={column.title}
                                        scope="col"
                                        className={`px-3 py-2 font-medium whitespace-nowrap ${column.className ?? ''}`}
                                    >
                                        {column.title}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {sponsors.data.map((sponsor) => (
                                <SponsorRow
                                    key={sponsor.id}
                                    teamSlug={team.slug}
                                    sponsor={sponsor}
                                    permissions={permissions}
                                />
                            ))}
                            {sponsors.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="px-3 py-10 text-center text-muted-foreground"
                                    >
                                        No sponsors match these filters.
                                    </td>
                                </tr>
                            ) : null}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div
                        data-test="sponsor-total"
                        className="text-muted-foreground"
                    >
                        {sponsors.total === 0
                            ? 'No sponsors'
                            : `Showing ${formatCount(sponsors.from ?? 0)} to ${formatCount(sponsors.to ?? 0)} of ${formatCount(sponsors.total)}`}
                    </div>

                    <div className="flex items-center gap-2">
                        <span className="text-muted-foreground">
                            Page {formatCount(sponsors.current_page)} of{' '}
                            {formatCount(sponsors.last_page)}
                        </span>
                        {sponsors.prev_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={sponsors.prev_page_url}>
                                    Previous
                                </Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Previous
                            </Button>
                        )}
                        {sponsors.next_page_url ? (
                            <Button variant="outline" size="sm" asChild>
                                <Link href={sponsors.next_page_url}>Next</Link>
                            </Button>
                        ) : (
                            <Button variant="outline" size="sm" disabled>
                                Next
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

SponsorsIndex.layout = (props: { team: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Sponsors',
            href: index(props.team.slug),
        },
    ],
});
