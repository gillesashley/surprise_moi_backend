import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import {
    CheckCircle,
    Clock,
    FileText,
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
            return { label: 'Approved', variant: 'default' as const, className: 'bg-emerald-600 hover:bg-emerald-700' };
        case 'rejected':
            return { label: 'Rejected', variant: 'destructive' as const, className: '' };
        case 'pending':
            return { label: 'Pending Review', variant: 'secondary' as const, className: 'bg-amber-100 text-amber-800 hover:bg-amber-200' };
        default:
            return { label: status, variant: 'secondary' as const, className: '' };
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
                <p className="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">{label}</p>
                <p className="mt-0.5 text-sm font-medium text-gray-900 dark:text-gray-100">
                    {value || '—'}
                </p>
            </div>
        </div>
    );
}

export default function Verification({ application }: Props) {
    if (!application) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="My Verification" />
                <div className="flex min-h-[50vh] flex-col items-center justify-center gap-4 p-6">
                    <div className="rounded-full bg-gray-100 p-6 dark:bg-gray-800">
                        <ShieldCheck className="h-12 w-12 text-gray-300 dark:text-gray-600" />
                    </div>
                    <div className="text-center">
                        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            No verification record
                        </h2>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your application details will appear here once available.
                        </p>
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
                            <h1 className="text-2xl font-bold tracking-tight">Verification Status</h1>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-muted-foreground">
                                <span className="flex items-center gap-1">
                                    <Clock className="h-3.5 w-3.5" />
                                    Submitted: {application.created_at_formatted}
                                </span>
                                {application.reviewed_at_formatted && (
                                    <span className="flex items-center gap-1">
                                        <CheckCircle className="h-3.5 w-3.5 text-emerald-500" />
                                        Reviewed: {application.reviewed_at_formatted}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                    <div className="flex flex-col items-start md:items-end gap-2">
                        <Badge className={`text-sm py-1.5 px-4 font-semibold ${status.className}`} variant={status.variant}>
                            {status.label}
                        </Badge>
                    </div>
                </div>

                {/* Personal Details Card */}
                <Card className="border-0 shadow-md ring-1 ring-border">
                    <CardHeader className="border-b bg-muted/30 px-6 py-4">
                        <div className="flex items-center gap-2">
                            <User className="h-5 w-5 text-primary" />
                            <CardTitle className="text-lg">Personal & Location Information</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-border">
                            {/* Identity Column */}
                            <div className="p-6 space-y-4">
                                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-2">Identity Details</h3>
                                <InfoRow icon={User} label="Full Name" value={`${application.first_name} ${application.last_name}`} />
                                <InfoRow icon={Mail} label="Email Address" value={application.email} />
                                <InfoRow icon={Phone} label="Contact Number" value={application.contact_number} />
                                <InfoRow icon={IdCard} label="Ghana Card No." value={application.ghana_card_number} />
                            </div>

                            {/* Location Column */}
                            <div className="p-6 space-y-4">
                                <h3 className="text-sm font-semibold text-muted-foreground uppercase tracking-wider mb-2">Location Details</h3>
                                <InfoRow icon={MapPin} label="Region" value={application.region?.name} />
                                <InfoRow icon={MapPin} label="City" value={application.city?.name} />
                                <InfoRow icon={MapPin} label="Street / House No." value={application.location} />
                                <div className="pt-2">
                                   <div className="rounded-lg bg-muted p-3">
                                       <p className="text-xs font-medium text-muted-foreground mb-1 uppercase">Current Status</p>
                                       <p className="text-sm font-bold capitalize">{application.status.replace('_', ' ')}</p>
                                   </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Document Cards */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Ghana Card Front */}
                    <Card className="border-0 shadow-sm overflow-hidden">
                        <CardHeader className="pb-3">
                            <div className="flex items-center gap-2">
                                <IdCard className="h-4 w-4 text-gray-500" />
                                <CardTitle className="text-base">Ghana Card — Front</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-hidden rounded-lg border bg-gray-50 dark:bg-gray-800">
                                <img
                                    src={application.ghana_card_image_url}
                                    className="h-auto w-full object-contain"
                                    alt="Ghana card front"
                                    style={{ maxHeight: '320px' }}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Ghana Card Back (if available) */}
                    {application.ghana_card_back_image_url && (
                        <Card className="border-0 shadow-sm overflow-hidden">
                            <CardHeader className="pb-3">
                                <div className="flex items-center gap-2">
                                    <IdCard className="h-4 w-4 text-gray-500" />
                                    <CardTitle className="text-base">Ghana Card — Back</CardTitle>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="overflow-hidden rounded-lg border bg-gray-50 dark:bg-gray-800">
                                    <img
                                        src={application.ghana_card_back_image_url}
                                        className="h-auto w-full object-contain"
                                        alt="Ghana card back"
                                        style={{ maxHeight: '320px' }}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Selfie */}
                    <Card className={`border-0 shadow-sm overflow-hidden ${!application.ghana_card_back_image_url ? '' : ''}`}>
                        <CardHeader className="pb-3">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4 text-gray-500" />
                                <CardTitle className="text-base">Selfie</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="overflow-hidden rounded-lg border bg-gray-50 dark:bg-gray-800">
                                <img
                                    src={application.selfie_url}
                                    className="h-auto w-full object-contain"
                                    alt="Selfie"
                                    style={{ maxHeight: '320px' }}
                                />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
