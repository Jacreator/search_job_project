import {
    formatCount,
    skipReasonLabels,
    statusLabels,
} from '@/components/sponsors/sponsor-labels';
import { Card, CardContent } from '@/components/ui/card';
import type { SkipReason, SponsorCount, SponsorStatus } from '@/types';

type Props = {
    statusCounts: SponsorCount<SponsorStatus>[];
    skipReasonCounts: SponsorCount<SkipReason>[];
};

export default function SponsorSummary({
    statusCounts,
    skipReasonCounts,
}: Props) {
    return (
        <div className="space-y-3">
            <div className="grid grid-cols-2 gap-3 md:grid-cols-5">
                {statusCounts.map((status) => (
                    <Card
                        key={status.value}
                        data-test={`status-count-${status.value}`}
                        className="gap-0 py-4"
                    >
                        <CardContent className="px-4">
                            <div className="text-sm text-muted-foreground">
                                {statusLabels[status.value]}
                            </div>
                            <div className="text-2xl font-semibold tabular-nums">
                                {formatCount(status.count)}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <dl className="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm">
                <dt className="text-muted-foreground">Skipped because:</dt>
                {skipReasonCounts.map((reason) => (
                    <div
                        key={reason.value}
                        data-test={`skip-count-${reason.value}`}
                        className="flex items-center gap-1.5"
                    >
                        <dt>{skipReasonLabels[reason.value]}</dt>
                        <dd className="font-medium tabular-nums">
                            {formatCount(reason.count)}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
