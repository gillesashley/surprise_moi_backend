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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle,
    Clock,
    Eye,
    FileCheck,
    Search,
    ThumbsDown,
    ThumbsUp,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useVendorApprovalEvents } from '@/hooks/use-vendor-approval-events';

interface VendorApplicationSummary {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    status: string;
    is_registered_vendor: boolean;
    submitted_at: string | null;
    reviewed_at: string | null;
    current_step: number;
    completed_step: number;
}

interface PaginatedApplications {
    data: VendorApplicationSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    applications: PaginatedApplications;
    filters: {
        status?: string;
        search?: string;
        sort_by?: string;
        sort_order?: string;
    };
    statuses: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vendor Applications',
        href: '/vendor-applications',
    },
];

const getStatusBadge = (status: string) => {
    const variants: Record<
        string,
        { color: string; icon: React.ReactNode; label: string }
    > = {
        pending: {
            color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
            icon: <Clock className="mr-1 h-3 w-3" />,
            label: 'Pending Review',
        },
        under_review: {
            color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
            icon: <FileCheck className="mr-1 h-3 w-3" />,
            label: 'Under Review',
        },
        approved: {
            color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            icon: <CheckCircle className="mr-1 h-3 w-3" />,
            label: 'Approved',
        },
        rejected: {
            color: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
            icon: <XCircle className="mr-1 h-3 w-3" />,
            label: 'Rejected',
        },
    };

    const config = variants[status] || variants.pending;

    return (
        <Badge className={`${config.color} flex items-center`}>
            {config.icon}
            {config.label}
        </Badge>
    );
};

export default function VendorApplicationsIndex({
    applications,
    filters,
    statuses,
}: Props) {
    const isDevelopment = import.meta.env.DEV;

    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [approveDialogOpen, setApproveDialogOpen] = useState(false);
    const [rejectDialogOpen, setRejectDialogOpen] = useState(false);
    const [selectedApplication, setSelectedApplication] =
        useState<VendorApplicationSummary | null>(null);
    const [showNewSubmissionNotice, setShowNewSubmissionNotice] = useState(false);

    const rejectForm = useForm({
        reason: '',
    });

    if (isDevelopment) {
        console.log('🏪 [VendorApplicationsIndex] Component mounted');
        console.log('🏪 [VendorApplicationsIndex] Current filters:', filters);
        console.log('🏪 [VendorApplicationsIndex] Applications loaded:', applications.total, 'total,', applications.data.length, 'on page');
    }

    // Listen for new vendor application submissions
    useVendorApprovalEvents((event) => {
        if (isDevelopment) {
            console.log('🎯 [VendorApplicationsIndex] Reverb event callback triggered', {
                timestamp: new Date().toISOString(),
                event,
            });
            console.log('🎯 [VendorApplicationsIndex] Showing notification banner...');
        }

        // Show notification banner
        setShowNewSubmissionNotice(true);

        if (isDevelopment) {
            console.log('🎯 [VendorApplicationsIndex] Scheduling page refresh in 1000ms...');
        }

        // Auto-refresh the list after a short delay
        setTimeout(() => {
            if (isDevelopment) {
                console.log('🎯 [VendorApplicationsIndex] Reloading page...');
            }
            router.reload({ preserveState: true });
        }, 1000);
    });

    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get(
                    '/dashboard/vendor-applications',
                    {
                        search: searchTerm,
                        page: 1,
                        ...(statusFilter !== 'all' && { status: statusFilter }),
                    },
                    {
                        preserveState: true,
                        preserveScroll: true,
                    },
                );
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchTerm, statusFilter]);

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get(
            '/dashboard/vendor-applications',
            {
                ...(value !== 'all' && { status: value }),
                search: filters.search,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/vendor-applications',
            {
                page,
                search: filters.search,
                sort_by: filters.sort_by,
                sort_order: filters.sort_order,
                ...(statusFilter !== 'all' && { status: statusFilter }),
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleSort = (column: string) => {
        let newSortOrder = 'asc';
        if (filters.sort_by === column && filters.sort_order === 'asc') {
            newSortOrder = 'desc';
        }

        router.get(
            '/dashboard/vendor-applications',
            {
                sort_by: column,
                sort_order: newSortOrder,
                search: filters.search,
                ...(statusFilter !== 'all' && { status: statusFilter }),
                page: 1,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const getSortIcon = (column: string) => {
        if (filters.sort_by !== column) return null;
        return filters.sort_order === 'asc' ? (
            <ArrowUp className="ml-1 inline size-4" />
        ) : (
            <ArrowDown className="ml-1 inline size-4" />
        );
    };

    const handleApprove = (application: VendorApplicationSummary) => {
        setSelectedApplication(application);
        setApproveDialogOpen(true);
    };

    const confirmApprove = () => {
        if (!selectedApplication) return;

        router.post(
            `/dashboard/vendor-applications/${selectedApplication.id}/approve`,
            {},
            {
                onSuccess: () => {
                    setApproveDialogOpen(false);
                    setSelectedApplication(null);
                },
            },
        );
    };

    const handleReject = (application: VendorApplicationSummary) => {
        setSelectedApplication(application);
        setRejectDialogOpen(true);
        rejectForm.reset();
    };

    const confirmReject = () => {
        if (!selectedApplication) return;

        rejectForm.post(
            `/dashboard/vendor-applications/${selectedApplication.id}/reject`,
            {
                onSuccess: () => {
                    setRejectDialogOpen(false);
                    setSelectedApplication(null);
                    rejectForm.reset();
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor Applications" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* New Submission Notification */}
                {showNewSubmissionNotice && (
                    <div className="rounded-md bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200 flex items-center justify-between">
                        <span>✨ New vendor application received! Refreshing...</span>
                        <button
                            onClick={() => {
                                if (isDevelopment) {
                                    console.log('🎯 [VendorApplicationsIndex] Notification closed');
                                }
                                setShowNewSubmissionNotice(false);
                            }}
                            className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            ✕
                        </button>
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Vendor Applications</CardTitle>
                                    <CardDescription>
                                        Review and manage vendor applications
                                    </CardDescription>
                                </div>
                                <div className="w-48">
                                    <Select
                                        value={statusFilter}
                                        onValueChange={handleStatusChange}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Filter by status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                All Applications
                                            </SelectItem>
                                            {statuses.map((status) => (
                                                <SelectItem
                                                    key={status}
                                                    value={status}
                                                >
                                                    {status
                                                        .split('_')
                                                        .map(
                                                            (word) =>
                                                                word
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                word.slice(1),
                                                        )
                                                        .join(' ')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Search Bar */}
                            <div className="flex items-center gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Search by name or email..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {applications.data.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                No vendor applications found.
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Applicant
                                                </th>
                                                <th
                                                    className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                                    onClick={() =>
                                                        handleSort(
                                                            'is_registered_vendor',
                                                        )
                                                    }
                                                >
                                                    Type
                                                    {getSortIcon(
                                                        'is_registered_vendor',
                                                    )}
                                                </th>
                                                <th
                                                    className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                                    onClick={() =>
                                                        handleSort('status')
                                                    }
                                                >
                                                    Status
                                                    {getSortIcon('status')}
                                                </th>
                                                <th
                                                    className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                                    onClick={() =>
                                                        handleSort(
                                                            'completed_step',
                                                        )
                                                    }
                                                >
                                                    Progress
                                                    {getSortIcon(
                                                        'completed_step',
                                                    )}
                                                </th>
                                                <th
                                                    className="cursor-pointer p-2 text-left text-sm font-medium hover:bg-muted/50"
                                                    onClick={() =>
                                                        handleSort(
                                                            'submitted_at',
                                                        )
                                                    }
                                                >
                                                    Submitted
                                                    {getSortIcon(
                                                        'submitted_at',
                                                    )}
                                                </th>
                                                <th className="p-2 text-right text-sm font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {applications.data.map(
                                                (application) => (
                                                    <tr
                                                        key={application.id}
                                                        className="border-b last:border-0"
                                                    >
                                                        <td className="p-2">
                                                            <div>
                                                                <div className="font-medium">
                                                                    {
                                                                        application
                                                                            .user
                                                                            .name
                                                                    }
                                                                </div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    {
                                                                        application
                                                                            .user
                                                                            .email
                                                                    }
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            <Badge
                                                                variant={
                                                                    application.is_registered_vendor
                                                                        ? 'default'
                                                                        : 'secondary'
                                                                }
                                                            >
                                                                {application.is_registered_vendor
                                                                    ? 'Registered'
                                                                    : 'Individual'}
                                                            </Badge>
                                                        </td>
                                                        <td className="p-2">
                                                            {getStatusBadge(
                                                                application.status,
                                                            )}
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="text-sm">
                                                                {
                                                                    application.completed_step
                                                                }
                                                                /4 steps
                                                            </div>
                                                        </td>
                                                        <td className="p-2">
                                                            {application.submitted_at ? (
                                                                <div className="text-sm">
                                                                    {new Date(
                                                                        application.submitted_at,
                                                                    ).toLocaleDateString()}
                                                                </div>
                                                            ) : (
                                                                <span className="text-sm text-muted-foreground">
                                                                    Not
                                                                    submitted
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="p-2 text-right">
                                                            <div className="flex justify-end gap-2">
                                                                {application.status ===
                                                                    'pending' &&
                                                                    application.submitted_at !==
                                                                        null && (
                                                                        <>
                                                                            <Button
                                                                                variant="default"
                                                                                size="sm"
                                                                                onClick={() =>
                                                                                    handleApprove(
                                                                                        application,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <ThumbsUp className="mr-2 size-4" />
                                                                                Approve
                                                                            </Button>
                                                                            <Button
                                                                                variant="destructive"
                                                                                size="sm"
                                                                                onClick={() =>
                                                                                    handleReject(
                                                                                        application,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <ThumbsDown className="mr-2 size-4" />
                                                                                Reject
                                                                            </Button>
                                                                        </>
                                                                    )}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={`/dashboard/vendor-applications/${application.id}`}
                                                                    >
                                                                        <Eye className="mr-2 size-4" />
                                                                        View
                                                                    </Link>
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Pagination */}
                                {applications.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <div className="text-sm text-muted-foreground">
                                            Showing {applications.data.length}{' '}
                                            of {applications.total} applications
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    applications.current_page ===
                                                    1
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        applications.current_page -
                                                            1,
                                                    )
                                                }
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    applications.current_page ===
                                                    applications.last_page
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        applications.current_page +
                                                            1,
                                                    )
                                                }
                                            >
                                                Next
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Approve Dialog */}
                <Dialog
                    open={approveDialogOpen}
                    onOpenChange={setApproveDialogOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                Approve Vendor Application
                            </DialogTitle>
                            <DialogDescription>
                                Are you sure you want to approve this vendor
                                application for{' '}
                                <strong>
                                    {selectedApplication?.user.name}
                                </strong>
                                ? They will be granted vendor privileges and can
                                start creating shops, products, and services.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setApproveDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button onClick={confirmApprove}>
                                <ThumbsUp className="mr-2 size-4" />
                                Approve Application
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Reject Dialog */}
                <Dialog
                    open={rejectDialogOpen}
                    onOpenChange={setRejectDialogOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reject Vendor Application</DialogTitle>
                            <DialogDescription>
                                Please provide a reason for rejecting the
                                application from{' '}
                                <strong>
                                    {selectedApplication?.user.name}
                                </strong>
                                . This will be sent to the applicant.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="py-4">
                            <Textarea
                                placeholder="Enter rejection reason (minimum 10 characters)..."
                                value={rejectForm.data.reason}
                                onChange={(e) =>
                                    rejectForm.setData('reason', e.target.value)
                                }
                                rows={4}
                                className={
                                    rejectForm.errors.reason
                                        ? 'border-red-500'
                                        : ''
                                }
                            />
                            {rejectForm.errors.reason && (
                                <p className="mt-1 text-sm text-red-500">
                                    {rejectForm.errors.reason}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => {
                                    setRejectDialogOpen(false);
                                    rejectForm.reset();
                                }}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmReject}
                                disabled={
                                    rejectForm.processing ||
                                    rejectForm.data.reason.length < 10
                                }
                            >
                                <ThumbsDown className="mr-2 size-4" />
                                {rejectForm.processing
                                    ? 'Rejecting...'
                                    : 'Reject Application'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
