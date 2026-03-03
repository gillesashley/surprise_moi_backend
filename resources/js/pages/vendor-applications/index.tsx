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
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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
    payment_completed: boolean;
    payment_status: string | null;
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
        { color: 'warning' | 'info' | 'success' | 'error'; icon: React.ReactNode; label: string }
    > = {
        pending: {
            color: 'warning',
            icon: <Clock style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Pending Review',
        },
        under_review: {
            color: 'info',
            icon: <FileCheck style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Under Review',
        },
        approved: {
            color: 'success',
            icon: <CheckCircle style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Approved',
        },
        rejected: {
            color: 'error',
            icon: <XCircle style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Rejected',
        },
    };

    const config = variants[status] || variants.pending;

    return (
        <Chip
            label={
                <Box sx={{ display: 'flex', alignItems: 'center' }}>
                    {config.icon}
                    {config.label}
                </Box>
            }
            color={config.color}
            size="small"
            variant="outlined"
        />
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
            <ArrowUp style={{ marginLeft: 4, display: 'inline', width: 16, height: 16 }} />
        ) : (
            <ArrowDown style={{ marginLeft: 4, display: 'inline', width: 16, height: 16 }} />
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                {/* New Submission Notification */}
                {showNewSubmissionNotice && (
                    <Box sx={{ borderRadius: 1.5, bgcolor: 'info.light', p: 2, fontSize: '0.875rem', color: 'info.dark', display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <Box component="span">✨ New vendor application received! Refreshing...</Box>
                        <Box
                            component="button"
                            onClick={() => {
                                if (isDevelopment) {
                                    console.log('🎯 [VendorApplicationsIndex] Notification closed');
                                }
                                setShowNewSubmissionNotice(false);
                            }}
                            sx={{ color: 'info.main', '&:hover': { color: 'info.dark' } }}
                        >
                            ✕
                        </Box>
                    </Box>
                )}
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Box>
                                    <CardTitle>Vendor Applications</CardTitle>
                                    <CardDescription>
                                        Review and manage vendor applications
                                    </CardDescription>
                                </Box>
                                <Box sx={{ width: 192 }}>
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
                                </Box>
                            </Box>

                            {/* Search Bar */}
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Box sx={{ position: 'relative', flex: 1 }}>
                                    <Search style={{ position: 'absolute', top: 10, left: 10, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                    <Input
                                        type="search"
                                        placeholder="Search by name or email..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        style={{ paddingLeft: 36 }}
                                    />
                                </Box>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        {applications.data.length === 0 ? (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No vendor applications found.
                            </Box>
                        ) : (
                            <>
                                <Box sx={{ overflowX: 'auto' }}>
                                    <Box component="table" sx={{ width: '100%' }}>
                                        <thead>
                                            <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Applicant
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
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
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
                                                    onClick={() =>
                                                        handleSort('status')
                                                    }
                                                >
                                                    Status
                                                    {getSortIcon('status')}
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
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
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Payment
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{ cursor: 'pointer', p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500, '&:hover': { bgcolor: 'action.hover' } }}
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
                                                </Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'right', fontSize: '0.875rem', fontWeight: 500 }}>
                                                    Actions
                                                </Box>
                                            </Box>
                                        </thead>
                                        <tbody>
                                            {applications.data.map(
                                                (application) => (
                                                    <Box
                                                        component="tr"
                                                        key={application.id}
                                                        sx={{ borderBottom: 1, borderColor: 'divider', '&:last-child': { border: 0 } }}
                                                    >
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Box>
                                                                <Box sx={{ fontWeight: 500 }}>
                                                                    {
                                                                        application
                                                                            .user
                                                                            .name
                                                                    }
                                                                </Box>
                                                                <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                                    {
                                                                        application
                                                                            .user
                                                                            .email
                                                                    }
                                                                </Box>
                                                            </Box>
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
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
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            {getStatusBadge(
                                                                application.status,
                                                            )}
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Box sx={{ fontSize: '0.875rem' }}>
                                                                {
                                                                    application.completed_step
                                                                }
                                                                /4 steps
                                                            </Box>
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Chip
                                                                label={
                                                                    application.payment_status === 'success' ? 'Paid'
                                                                    : application.payment_status === 'failed' ? 'Failed'
                                                                    : application.payment_status === 'pending' ? 'Pending'
                                                                    : application.payment_status === 'processing' ? 'Processing'
                                                                    : 'Unpaid'
                                                                }
                                                                color={
                                                                    application.payment_status === 'success' ? 'success'
                                                                    : application.payment_status === 'failed' ? 'error'
                                                                    : application.payment_status === 'pending' ? 'warning'
                                                                    : application.payment_status === 'processing' ? 'info'
                                                                    : 'default'
                                                                }
                                                                size="small"
                                                                variant="outlined"
                                                            />
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            {application.submitted_at ? (
                                                                <Box sx={{ fontSize: '0.875rem' }}>
                                                                    {new Date(
                                                                        application.submitted_at,
                                                                    ).toLocaleDateString()}
                                                                </Box>
                                                            ) : (
                                                                <Box component="span" sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                                    Not
                                                                    submitted
                                                                </Box>
                                                            )}
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1, textAlign: 'right' }}>
                                                            <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1 }}>
                                                                {application.status ===
                                                                    'pending' &&
                                                                    application.submitted_at !==
                                                                        null &&
                                                                    application.completed_step >= 4 &&
                                                                    application.payment_completed && (
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
                                                                                <ThumbsUp style={{ marginRight: 8, width: 16, height: 16 }} />
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
                                                                                <ThumbsDown style={{ marginRight: 8, width: 16, height: 16 }} />
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
                                                                        <Eye style={{ marginRight: 8, width: 16, height: 16 }} />
                                                                        View
                                                                    </Link>
                                                                </Button>
                                                            </Box>
                                                        </Box>
                                                    </Box>
                                                ),
                                            )}
                                        </tbody>
                                    </Box>
                                </Box>

                                {/* Pagination */}
                                {applications.last_page > 1 && (
                                    <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                        <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Showing {applications.data.length}{' '}
                                            of {applications.total} applications
                                        </Box>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
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
                                        </Box>
                                    </Box>
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
                                <ThumbsUp style={{ marginRight: 8, width: 16, height: 16 }} />
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
                        <Box sx={{ py: 2 }}>
                            <Textarea
                                placeholder="Enter rejection reason (minimum 10 characters)..."
                                value={rejectForm.data.reason}
                                onChange={(e) =>
                                    rejectForm.setData('reason', e.target.value)
                                }
                                rows={4}
                                style={
                                    rejectForm.errors.reason
                                        ? { borderColor: '#ef4444' }
                                        : undefined
                                }
                            />
                            {rejectForm.errors.reason && (
                                <Typography sx={{ mt: 0.5, fontSize: '0.875rem', color: 'error.main' }}>
                                    {rejectForm.errors.reason}
                                </Typography>
                            )}
                        </Box>
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
                                <ThumbsDown style={{ marginRight: 8, width: 16, height: 16 }} />
                                {rejectForm.processing
                                    ? 'Rejecting...'
                                    : 'Reject Application'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Box>
        </AppLayout>
    );
}
