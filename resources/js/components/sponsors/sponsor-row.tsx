import { router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import {
    confidenceClass,
    scaleUpClasses,
    secondCheckClasses,
    skipReasonLabels,
    statusClasses,
    statusLabels,
} from '@/components/sponsors/sponsor-labels';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { update } from '@/routes/sponsors';
import { update as updateRating } from '@/routes/sponsors/rating';
import type { Sponsor, SponsorPermissions } from '@/types';

type Props = {
    teamSlug: string;
    sponsor: Sponsor;
    permissions: SponsorPermissions;
};

export default function SponsorRow({ teamSlug, sponsor, permissions }: Props) {
    // The lookup would overwrite a website saved on a row it has not finished.
    const canEdit = permissions.canEdit && sponsor.status === 'done';
    const canSetRating =
        permissions.canSetRatings &&
        (sponsor.rating_grade === null || sponsor.rating_grade_manual);

    const [editing, setEditing] = useState(false);
    const [website, setWebsite] = useState(sponsor.website ?? '');
    const [websiteError, setWebsiteError] = useState<string>();
    const [saving, setSaving] = useState(false);

    const startEditing = () => {
        setWebsite(sponsor.website ?? '');
        setWebsiteError(undefined);
        setEditing(true);
    };

    const saveWebsite = (event: FormEvent) => {
        event.preventDefault();

        router.visit(update([teamSlug, sponsor.id]), {
            data: { website: website.trim() },
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onError: (errors) => setWebsiteError(errors.website),
            onSuccess: () => setEditing(false),
        });
    };

    const setConfirmed = (confirmed: boolean) => {
        router.visit(update([teamSlug, sponsor.id]), {
            data: { confirmed },
            preserveScroll: true,
        });
    };

    const setRating = (grade: string) => {
        router.visit(updateRating([teamSlug, sponsor.id]), {
            data: { grade },
            preserveScroll: true,
        });
    };

    return (
        <tr
            data-test="sponsor-row"
            className="border-b align-top last:border-0"
        >
            <td className="px-3 py-3">
                <div className="font-medium">{sponsor.name}</div>
                {sponsor.is_scale_up ? (
                    <Badge
                        data-test="scale-up-badge"
                        className={`mt-1 border-transparent ${scaleUpClasses}`}
                    >
                        Scale-up
                    </Badge>
                ) : null}
            </td>
            <td className="px-3 py-3">{sponsor.town || '-'}</td>
            <td className="px-3 py-3">{sponsor.region ?? '-'}</td>
            <td className="px-3 py-3">
                {canSetRating ? (
                    <Select
                        value={sponsor.rating_grade ?? ''}
                        onValueChange={setRating}
                    >
                        <SelectTrigger
                            size="sm"
                            data-test="rating-select"
                            aria-label={`Rating for ${sponsor.name}`}
                        >
                            <SelectValue placeholder="Missing" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="A">A</SelectItem>
                            <SelectItem value="B">B</SelectItem>
                        </SelectContent>
                    </Select>
                ) : (
                    <span
                        className={
                            sponsor.rating_grade === null
                                ? 'text-muted-foreground'
                                : ''
                        }
                    >
                        {sponsor.rating_grade ?? 'Missing'}
                    </span>
                )}
            </td>
            <td className="px-3 py-3 tabular-nums">
                {sponsor.sic_codes.length > 0
                    ? sponsor.sic_codes.join(', ')
                    : '-'}
            </td>
            <td className="px-3 py-3">
                {editing ? (
                    <form onSubmit={saveWebsite} className="space-y-2">
                        <Input
                            type="url"
                            value={website}
                            placeholder="https://example.com"
                            data-test="website-input"
                            aria-label={`Website for ${sponsor.name}`}
                            aria-invalid={websiteError ? true : undefined}
                            autoFocus
                            className="h-8 min-w-56"
                            onChange={(event) => setWebsite(event.target.value)}
                        />
                        <InputError message={websiteError} />
                        <div className="flex items-center gap-2">
                            <Button
                                type="submit"
                                size="sm"
                                disabled={saving}
                                data-test="website-save-button"
                            >
                                Save
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() => setEditing(false)}
                            >
                                Cancel
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="flex items-center gap-1">
                        {sponsor.website ? (
                            <a
                                href={sponsor.website}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="break-all underline underline-offset-4"
                            >
                                {sponsor.website}
                            </a>
                        ) : (
                            <span className="text-muted-foreground">-</span>
                        )}
                        {canEdit ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                data-test="website-edit-button"
                                aria-label={`Edit website for ${sponsor.name}`}
                                onClick={startEditing}
                            >
                                <Pencil className="h-4 w-4" />
                            </Button>
                        ) : null}
                    </div>
                )}
            </td>
            <td className="px-3 py-3 text-right tabular-nums">
                {sponsor.confidence === null ? (
                    <span className="text-muted-foreground">-</span>
                ) : (
                    <span
                        className={`font-medium ${confidenceClass(sponsor.confidence)}`}
                    >
                        {sponsor.confidence}
                    </span>
                )}
            </td>
            <td className="px-3 py-3">
                <Badge
                    className={`border-transparent ${statusClasses[sponsor.status]}`}
                >
                    {statusLabels[sponsor.status]}
                </Badge>
                {sponsor.needs_second_check ? (
                    <Badge
                        data-test="second-check-badge"
                        className={`ml-1 border-transparent ${secondCheckClasses}`}
                    >
                        Needs second check
                    </Badge>
                ) : null}
                {sponsor.skip_reason ? (
                    <div className="mt-1 text-xs text-muted-foreground">
                        {skipReasonLabels[sponsor.skip_reason]}
                    </div>
                ) : null}
            </td>
            <td className="px-3 py-3">
                {canEdit && sponsor.website ? (
                    <Checkbox
                        checked={sponsor.confirmed}
                        data-test="confirmed-checkbox"
                        aria-label={`Website confirmed for ${sponsor.name}`}
                        onCheckedChange={(checked) =>
                            setConfirmed(checked === true)
                        }
                    />
                ) : (
                    <span
                        className={
                            sponsor.confirmed ? '' : 'text-muted-foreground'
                        }
                    >
                        {sponsor.confirmed ? 'Yes' : 'No'}
                    </span>
                )}
            </td>
        </tr>
    );
}
