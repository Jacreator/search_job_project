import type { SkipReason, SponsorStatus } from '@/types';

export const statusLabels: Record<SponsorStatus, string> = {
    pending: 'Pending',
    ch_done: 'Companies House done',
    done: 'Done',
    skipped: 'Skipped',
    failed: 'Failed',
};

export const statusClasses: Record<SponsorStatus, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    ch_done: 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
    done: 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
    skipped:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    failed: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
};

export const skipReasonLabels: Record<SkipReason, string> = {
    no_match: 'No Companies House match',
    not_tech: 'Not tech',
    inactive: 'Not active',
    b_rating: 'B rating',
};

export const scaleUpClasses =
    'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300';

export function confidenceClass(confidence: number): string {
    if (confidence >= 75) {
        return 'text-green-600 dark:text-green-400';
    }

    if (confidence >= 50) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-red-600 dark:text-red-400';
}

export function formatCount(count: number): string {
    return count.toLocaleString('en-GB');
}
