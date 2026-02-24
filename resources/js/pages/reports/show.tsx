import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CheckCircle,
    Clock,
    FileImage,
    Loader2,
    Package,
    User as UserIcon,
} from 'lucide-react';
import { useState } from 'react';

interface Attachment {
    id: number;
    file_name: string;
    file_size: number;
    mime_type: string;
    url: string;
}

interface Report {
    id: number;
    report_number: string;
    category: string;
    description: string;
    status: string;
    user: { id: number; name: string; email: string; phone: string | null };
    order: { id: number; order_number: string } | null;
    attachments: Attachment[];
    resolver: { id: number; name: string } | null;
    resolution_notes: string | null;
    cancellation_reason: string | null;
    resolved_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    can_be_cancelled: boolean;
    is_pending: boolean;
    is_in_progress: boolean;
}

interface Props {
    report: Report;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports & Conflicts', href: '/dashboard/reports' },
    { title: 'Report Detail', href: '#' },
];

const statusConfig: Record<string, { color: string; label: string }> = {
    pending: {
        color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        label: 'Pending',
    },
    in_progress: {
        color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        label: 'In Progress',
    },
    resolved: {
        color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        label: 'Resolved',
    },
    cancelled: {
        color: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        label: 'Cancelled',
    },
};

function formatCategory(value: string): string {
    return value
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1048576).toFixed(1)} MB`;
}

export default function ReportShow({ report }: Props) {
    const [resolveDialogOpen, setResolveDialogOpen] = useState(false);
    const sc = statusConfig[report.status] ?? statusConfig.pending;

    const resolveForm = useForm({ resolution_notes: '' });

    const handleMarkInProgress = () => {
        router.post(
            `/dashboard/reports/${report.id}/status`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const handleResolve = () => {
        resolveForm.post(`/dashboard/reports/${report.id}/resolve`, {
            onSuccess: () => {
                setResolveDialogOpen(false);
                resolveForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Report ${report.report_number}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/dashboard/reports">
                            <ArrowLeft className="mr-1 h-4 w-4" /> Back to
                            Reports
                        </Link>
                    </Button>
                    <div className="flex gap-2">
                        {report.is_pending && (
                            <Button
                                variant="outline"
                                onClick={handleMarkInProgress}
                            >
                                <Clock className="mr-1 h-4 w-4" /> Mark In
                                Progress
                            </Button>
                        )}
                        {(report.is_pending || report.is_in_progress) && (
                            <Button onClick={() => setResolveDialogOpen(true)}>
                                <CheckCircle className="mr-1 h-4 w-4" /> Resolve
                                Report
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Main info */}
                    <div className="flex flex-col gap-4 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle className="font-mono">
                                            {report.report_number}
                                        </CardTitle>
                                        <CardDescription>
                                            {formatCategory(report.category)}
                                        </CardDescription>
                                    </div>
                                    <Badge className={sc.color}>
                                        {sc.label}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-4">
                                <div>
                                    <p className="mb-1 text-sm font-medium text-muted-foreground">
                                        Description
                                    </p>
                                    <p className="text-sm leading-relaxed">
                                        {report.description}
                                    </p>
                                </div>
                                {report.cancellation_reason && (
                                    <div className="rounded-md bg-gray-50 p-3 dark:bg-gray-900">
                                        <p className="mb-1 text-sm font-medium text-muted-foreground">
                                            Cancellation Reason
                                        </p>
                                        <p className="text-sm">
                                            {report.cancellation_reason}
                                        </p>
                                    </div>
                                )}
                                {report.resolution_notes && (
                                    <div className="rounded-md bg-green-50 p-3 dark:bg-green-950">
                                        <p className="mb-1 text-sm font-medium text-green-700 dark:text-green-300">
                                            Resolution Notes
                                        </p>
                                        <p className="text-sm">
                                            {report.resolution_notes}
                                        </p>
                                        {report.resolver && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Resolved by{' '}
                                                {report.resolver.name}
                                                {report.resolved_at &&
                                                    ` on ${new Date(report.resolved_at).toLocaleDateString()}`}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Attachments */}
                        {report.attachments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <FileImage className="h-4 w-4" />{' '}
                                        Attachments ({report.attachments.length}
                                        )
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                        {report.attachments.map((att) => (
                                            <a
                                                key={att.id}
                                                href={att.url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="group flex flex-col items-center gap-1 rounded-lg border p-3 text-center hover:bg-muted/50"
                                            >
                                                <img
                                                    src={att.url}
                                                    alt={att.file_name}
                                                    className="h-20 w-full rounded object-cover"
                                                    onError={(e) => {
                                                        (
                                                            e.target as HTMLImageElement
                                                        ).style.display =
                                                            'none';
                                                    }}
                                                />
                                                <span className="mt-1 truncate text-xs font-medium">
                                                    {att.file_name}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {formatFileSize(
                                                        att.file_size,
                                                    )}
                                                </span>
                                            </a>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar info */}
                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <UserIcon className="h-4 w-4" /> Submitted
                                    By
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 text-sm">
                                <p className="font-medium">
                                    {report.user.name}
                                </p>
                                <p className="text-muted-foreground">
                                    {report.user.email}
                                </p>
                                {report.user.phone && (
                                    <p className="text-muted-foreground">
                                        {report.user.phone}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {report.order && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-base">
                                        <Package className="h-4 w-4" /> Related
                                        Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="font-mono text-sm">
                                        {report.order.order_number}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-1 text-sm text-muted-foreground">
                                <p>
                                    Created:{' '}
                                    {report.created_at
                                        ? new Date(
                                              report.created_at,
                                          ).toLocaleString()
                                        : '—'}
                                </p>
                                <p>
                                    Updated:{' '}
                                    {report.updated_at
                                        ? new Date(
                                              report.updated_at,
                                          ).toLocaleString()
                                        : '—'}
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Resolve Dialog */}
                <Dialog
                    open={resolveDialogOpen}
                    onOpenChange={setResolveDialogOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Resolve Report</DialogTitle>
                            <DialogDescription>
                                Provide resolution notes explaining how this
                                report was handled.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="flex flex-col gap-2">
                            <Label htmlFor="resolution_notes">
                                Resolution Notes
                            </Label>
                            <Textarea
                                id="resolution_notes"
                                placeholder="Describe how the issue was resolved..."
                                rows={4}
                                value={resolveForm.data.resolution_notes}
                                onChange={(e) =>
                                    resolveForm.setData(
                                        'resolution_notes',
                                        e.target.value,
                                    )
                                }
                            />
                            {resolveForm.errors.resolution_notes && (
                                <p className="text-sm text-destructive">
                                    {resolveForm.errors.resolution_notes}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setResolveDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleResolve}
                                disabled={resolveForm.processing}
                            >
                                {resolveForm.processing && (
                                    <Loader2 className="mr-1 h-4 w-4 animate-spin" />
                                )}
                                Resolve Report
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
