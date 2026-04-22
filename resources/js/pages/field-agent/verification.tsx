import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    CheckCircle,
    Clock,
    IdCard,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    User,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/field-agent/dashboard' },
    { title: 'My Verification', href: '/field-agent/verification' },
];

type Props = {
    application: {
        first_name: string;
        last_name: string;
        email: string;
        contact_number: string;
        location: string;
        ghana_card_number: string;
        status: string;
        region?: { name: string };
        city?: { name: string };
        ghana_card_image_url: string;
        ghana_card_back_image_url: string | null;
        selfie_url: string;
        created_at_formatted: string;
        reviewed_at_formatted: string | null;
    } | null;
};

function statusConfig(status: string) {
    switch (status) {
        case 'approved':
            return {
                label: 'Approved',
                variant: 'default' as const,
                className: 'bg-emerald-600 hover:bg-emerald-700',
            };
        case 'rejected':
            return {
                label: 'Rejected',
                variant: 'destructive' as const,
                className: '',
            };
        case 'pending':
            return {
                label: 'Pending Review',
                variant: 'secondary' as const,
                className: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
            };
        default:
            return {
                label: status,
                variant: 'secondary' as const,
                className: '',
            };
    }
}

function InfoRow({
    icon: Icon,
    label,
    value,
}: {
    icon: React.ElementType;
    label: string;
    value?: string | null;
}) {
    return (
        <div className="flex items-start gap-3 py-3">
            <div className="mt-0.5 rounded-lg bg-gray-100 p-2 dark:bg-gray-800">
                <Icon className="h-4 w-4 text-gray-500 dark:text-gray-400" />
            </div>
            <div className="min-w-0 flex-1">
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {label}
                </p>
                <p className="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                    {value || '—'}
                </p>
            </div>
        </div>
    );
}

function DocumentCard({
    title,
    url,
    icon: Icon,
}: {
    title: string;
    url: string;
    icon: React.ElementType;
}) {
    return (
        <Card className="overflow-hidden shadow-sm hover:shadow-lg transition-shadow duration-300 ring-1 ring-border border-0">
            <CardHeader className="p-4 border-b bg-muted/20">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Icon className="h-4 w-4 text-muted-foreground" />
                        <CardTitle className="text-sm font-semibold">
                            {title}
                        </CardTitle>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-3">
                <div className="relative group overflow-hidden rounded-md border aspect-[4/3] bg-muted/30">
                    <img
                        src={url}
                        alt={title}
                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                    <div className="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => window.open(url, '_blank')}
                        >
                            View Original
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Verification({ application }: Props) {
    if (!application) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="My Verification" />
                <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                    <div className="flex min-h-[40vh] flex-col items-center justify-center gap-4 rounded-3xl border-2 border-dashed border-muted p-12 text-center transition-colors hover:border-muted-foreground/20">
                        <div className="rounded-full bg-primary/10 p-6 ring-8 ring-primary/5">
                            <ShieldCheck className="h-12 w-12 text-primary" />
                        </div>
                        <div className="max-w-xs space-y-2">
                            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
                                No verification record
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Your application details will appear here once
                                available. If you believe this is an error,
                                please contact support.
                            </p>
                        </div>
                    </div>

                    {/* Bottom support section even in empty state */}
                    <div className="rounded-xl bg-primary/5 p-6 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-4 transition-all hover:bg-primary/[0.07] shadow-sm">
                        <div className="flex flex-col md:flex-row items-center gap-4 text-center md:text-left">
                            <div className="rounded-full bg-primary/10 p-2 ring-4 ring-primary/5">
                                <ShieldCheck className="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p className="font-semibold text-gray-900 dark:text-gray-100 text-base">
                                    Security & Privacy
                                </p>
                                <p className="text-sm text-muted-foreground max-w-md">
                                    Your verification data is encrypted and
                                    handled securely according to our privacy
                                    policy.
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                (window.location.href =
                                    'mailto:support@surprisemoi.com')
                            }
                            className="bg-background hover:bg-primary/5 border-primary/20 transition-colors"
                        >
                            Contact Support
                        </Button>
                    </div>
                </div>
            </AppLayout>
        );
    }

    const status = statusConfig(application.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Verification" />

            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-6">
                {/* Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between border-b pb-6">
                    <div className="flex items-center gap-4">
                        <div className="rounded-2xl bg-primary/10 p-3 text-primary">
                            <ShieldCheck className="h-8 w-8" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">
                                Verification Status
                            </h1>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    Submitted:{' '}
                                    {application.created_at_formatted}
                                </span>
                                {application.reviewed_at_formatted && (
                                    <span className="flex items-center gap-1">
                                        <CheckCircle className="h-3.5 w-3.5 text-emerald-500" />
                                        Reviewed:{' '}
                                        {application.reviewed_at_formatted}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-start md:items-end gap-2">
                        <Badge
                            className={`text-sm py-1.5 px-4 font-semibold ${status.className}`}
                            variant={status.variant}
                        >
                            {status.label}
                        </Badge>
                    </div>
                </div>

                {/* Personal Details Card */}
                <Card className="border-0 shadow-md ring-1 ring-border">
                    <CardHeader className="border-b bg-muted/30 px-6 py-4">
                        <div className="flex items-center gap-2">
                            <User className="h-5 w-5 text-primary" />
                            <CardTitle className="text-lg">
                                Personal & Location Information
                            </CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border">
                            {/* Identity Column */}
                            <div className="p-6 space-y-4">
                                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                                    Identity Details
                                </h3>
                                <InfoRow
                                    icon={User}
                                    label="Full Name"
                                    value={`${application.first_name} ${application.last_name}`}
                                />
                                <InfoRow
                                    icon={Mail}
                                    label="Email Address"
                                    value={application.email}
                                />
                                <InfoRow
                                    icon={Phone}
                                    label="Contact Number"
                                    value={application.contact_number}
                                />
                                <InfoRow
                                    icon={IdCard}
                                    label="Ghana Card No."
                                    value={application.ghana_card_number}
                                />
                            </div>

                            {/* Location Column */}
                            <div className="p-6 space-y-4">
                                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-2">
                                    Location Details
                                </h3>
                                <InfoRow
                                    icon={MapPin}
                                    label="Region"
                                    value={application.region?.name}
                                />
                                <InfoRow
                                    icon={MapPin}
                                    label="City"
                                    value={application.city?.name}
                                />
                                <InfoRow
                                    icon={MapPin}
                                    label="Street / House No."
                                    value={application.location}
                                />
                                <div className="pt-2">
                                    <div className="rounded-lg bg-muted p-3">
                                        <p className="text-xs font-medium text-muted-foreground mb-1 uppercase">
                                            Current Status
                                        </p>
                                        <p className="text-sm font-bold capitalize">
                                            {application.status.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Document Cards */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <DocumentCard
                        title="Ghana Card — Front"
                        url={application.ghana_card_image_url}
                        icon={IdCard}
                    />

                    {application.ghana_card_back_image_url && (
                        <DocumentCard
                            title="Ghana Card — Back"
                            url={application.ghana_card_back_image_url}
                            icon={IdCard}
                        />
                    )}

                    <DocumentCard
                        title="Selfie"
                        url={application.selfie_url}
                        icon={User}
                    />
                </div>

                {/* Bottom support section */}
                <div className="rounded-xl bg-primary/5 p-6 border border-primary/10 flex flex-col md:flex-row items-center justify-between gap-4 transition-all hover:bg-primary/[0.07] shadow-sm">
                    <div className="flex flex-col md:flex-row items-center gap-4 text-center md:text-left">
                        <div className="rounded-full bg-primary/10 p-2 ring-4 ring-primary/5">
                            <ShieldCheck className="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p className="font-semibold text-gray-900 dark:text-gray-100 text-base">
                                Security & Privacy
                            </p>
                            <p className="text-sm text-muted-foreground max-w-md">
                                Your verification data is encrypted and handled
                                securely according to our privacy policy.
                            </p>
                        </div>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            (window.location.href =
                                'mailto:support@surprisemoi.com')
                        }
                        className="bg-background hover:bg-primary/5 border-primary/20 transition-colors whitespace-nowrap"
                    >
                        Contact Support
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
