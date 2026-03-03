import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import {
    AlertTriangle,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
    Clock,
    RefreshCw,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Jobs',
        href: '/dashboard/jobs',
    },
];

interface Job {
    id: number;
    uuid: string;
    connection: string;
    queue: string;
    payload: {
        displayName?: string;
        job?: string;
        data?: Record<string, any>;
    };
    exception: string;
    failed_at: string;
}

interface JobStatistics {
    total_failed: number;
    by_queue?: Record<string, number>;
    recent_failures: number;
}

interface Pagination {
    current_page: number;
    total_pages: number;
    per_page: number;
    total: number;
}

interface Props {
    initialStatistics: JobStatistics;
    initialJobs: Job[];
    initialPagination: Pagination;
}

export default function Index({
    initialStatistics,
    initialJobs,
    initialPagination,
}: Props) {
    const [jobs, setJobs] = useState<Job[]>(initialJobs);
    const [statistics, setStatistics] =
        useState<JobStatistics>(initialStatistics);
    const [pagination, setPagination] = useState<Pagination>(initialPagination);
    const [refreshing, setRefreshing] = useState(false);

    const goToPage = (page: number) => {
        if (page < 1 || page > pagination.total_pages) return;

        setRefreshing(true);
        router.reload({
            only: ['initialJobs', 'initialPagination'],
            data: { page, per_page: 20 },
            onSuccess: (pageProps) => {
                setJobs(pageProps.props.initialJobs as Job[]);
                setPagination(pageProps.props.initialPagination as Pagination);
            },
            onError: () => {
                console.error('Failed to fetch jobs');
            },
            onFinish: () => {
                setRefreshing(false);
            },
        });
    };

    const handleRefresh = () => {
        setRefreshing(true);
        router.reload({
            onSuccess: (page) => {
                setJobs(page.props.initialJobs as Job[]);
                setStatistics(page.props.initialStatistics as JobStatistics);
                setPagination(page.props.initialPagination as Pagination);
            },
            onFinish: () => {
                setRefreshing(false);
            },
        });
    };

    const getStatusColor = (status: string): 'error' | 'success' | 'info' | 'warning' | 'default' => {
        switch (status) {
            case 'completed':
                return 'success';
            case 'failed':
                return 'error';
            case 'processing':
                return 'info';
            case 'pending':
                return 'warning';
            default:
                return 'default';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <CheckCircle style={{ width: 16, height: 16 }} />;
            case 'failed':
                return <XCircle style={{ width: 16, height: 16 }} />;
            case 'processing':
                return <RefreshCw style={{ width: 16, height: 16, animation: 'spin 1s linear infinite' }} />;
            case 'pending':
                return <Clock style={{ width: 16, height: 16 }} />;
            default:
                return <AlertTriangle style={{ width: 16, height: 16 }} />;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jobs Dashboard" />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, overflow: 'auto', p: 2, height: '100%' }}>
                {/* Header */}
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Box>
                        <Typography variant="h5" sx={{ fontWeight: 700, color: 'text.primary' }}>
                            Jobs Dashboard
                        </Typography>
                        <Typography sx={{ color: 'text.secondary' }}>
                            Monitor background job performance and status
                        </Typography>
                    </Box>
                    <Button
                        onClick={handleRefresh}
                        disabled={refreshing}
                        variant="outline"
                    >
                        <RefreshCw
                            style={{ marginRight: 8, width: 16, height: 16, ...(refreshing ? { animation: 'spin 1s linear infinite' } : {}) }}
                        />
                        Refresh
                    </Button>
                </Box>

                {/* Statistics Cards */}
                {statistics && (
                    <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)', lg: 'repeat(4, 1fr)' }, gap: 3 }}>
                        <Card>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', pb: 1, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                    Total Failed Jobs
                                </CardTitle>
                                <XCircle style={{ width: 16, height: 16, color: '#dc2626' }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                    {statistics.total_failed}
                                </Box>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', pb: 1, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                    Recent Failures (24h)
                                </CardTitle>
                                <Clock style={{ width: 16, height: 16, color: '#ca8a04' }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                    {statistics.recent_failures}
                                </Box>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', pb: 1, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                    Active Queues
                                </CardTitle>
                                <AlertTriangle style={{ width: 16, height: 16, color: '#ea580c' }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                    {
                                        Object.keys(statistics.by_queue || {})
                                            .length
                                    }
                                </Box>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader sx={{ display: 'flex', flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', pb: 1, '& > *': { my: 0 } }}>
                                <CardTitle sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                    Top Queue
                                </CardTitle>
                                <CheckCircle style={{ width: 16, height: 16, color: '#2563eb' }} />
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ fontSize: '1.5rem', fontWeight: 700, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                                    {Object.entries(
                                        statistics.by_queue || {},
                                    ).sort(
                                        (a, b) =>
                                            (b[1] as number) - (a[1] as number),
                                    )[0]?.[0] || 'N/A'}
                                </Box>
                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                    {Object.entries(
                                        statistics.by_queue || {},
                                    ).sort(
                                        (a, b) =>
                                            (b[1] as number) - (a[1] as number),
                                    )[0]?.[1] || 0}{' '}
                                    jobs
                                </Typography>
                            </CardContent>
                        </Card>
                    </Box>
                )}

                {/* Recent Jobs */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Jobs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ minHeight: 384, borderRadius: 2, border: 1, borderColor: 'divider' }}>
                            {jobs.length === 0 ? (
                                <Box sx={{ display: 'flex', height: 384, alignItems: 'center', justifyContent: 'center' }}>
                                    <Typography sx={{ color: 'text.secondary' }}>
                                        No failed jobs found
                                    </Typography>
                                </Box>
                            ) : (
                                <Box>
                                    {jobs.map((job) => {
                                        const status = 'failed';
                                        const jobName =
                                            job.payload?.displayName ||
                                            job.payload?.job ||
                                            'Unknown Job';

                                        return (
                                            <Box
                                                key={job.id}
                                                sx={{
                                                    display: 'flex',
                                                    alignItems: 'center',
                                                    justifyContent: 'space-between',
                                                    p: 2,
                                                    '&:hover': { bgcolor: 'action.hover' },
                                                    borderBottom: 1,
                                                    borderColor: 'divider',
                                                    '&:last-child': { borderBottom: 0 },
                                                }}
                                            >
                                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                                    {getStatusIcon(status)}
                                                    <Box>
                                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                            <Box component="span" sx={{ fontWeight: 500 }}>
                                                                {jobName}
                                                            </Box>
                                                            <Chip
                                                                label={status}
                                                                color={getStatusColor(status)}
                                                                size="small"
                                                                variant="outlined"
                                                            />
                                                        </Box>
                                                        <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                            Queue: {job.queue} •
                                                            Connection:{' '}
                                                            {job.connection}
                                                        </Box>
                                                        <Box sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                                            Failed:{' '}
                                                            {formatDate(
                                                                job.failed_at,
                                                            )}
                                                        </Box>
                                                        {job.exception && (
                                                            <Box sx={{ mt: 1, maxWidth: 448 }}>
                                                                <Box sx={{ borderRadius: 1, bgcolor: 'error.lighter', px: 1, py: 0.5, fontSize: '0.75rem', color: 'error.dark' }}>
                                                                    <Box component="span" sx={{ fontWeight: 600 }}>
                                                                        Error:{' '}
                                                                    </Box>
                                                                    {job.exception
                                                                        .split(
                                                                            '\n',
                                                                        )[0]
                                                                        .substring(
                                                                            0,
                                                                            100,
                                                                        )}
                                                                    {job
                                                                        .exception
                                                                        .length >
                                                                        100 &&
                                                                        '...'}
                                                                </Box>
                                                            </Box>
                                                        )}
                                                    </Box>
                                                </Box>
                                                <Box sx={{ textAlign: 'right' }}>
                                                    <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        ID: {job.id}
                                                    </Box>
                                                    {job.payload?.data?.id && (
                                                        <Box sx={{ fontSize: '0.75rem', color: 'text.disabled' }}>
                                                            Entity ID:{' '}
                                                            {
                                                                job.payload.data
                                                                    .id
                                                            }
                                                        </Box>
                                                    )}
                                                </Box>
                                            </Box>
                                        );
                                    })}
                                </Box>
                            )}
                        </Box>

                        {/* Pagination Controls */}
                        <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderTop: 1, borderColor: 'divider', pt: 2 }}>
                            <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                Showing {jobs.length} of {pagination.total} jobs
                                <Box component="span" sx={{ ml: 1 }}>
                                    (Page {pagination.current_page} of{' '}
                                    {pagination.total_pages})
                                </Box>
                            </Box>
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        goToPage(pagination.current_page - 1)
                                    }
                                    disabled={
                                        pagination.current_page === 1 ||
                                        refreshing
                                    }
                                >
                                    <ChevronLeft style={{ marginRight: 4, width: 16, height: 16 }} />
                                    Previous
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        goToPage(pagination.current_page + 1)
                                    }
                                    disabled={
                                        pagination.current_page >=
                                            pagination.total_pages || refreshing
                                    }
                                >
                                    Next
                                    <ChevronRight style={{ marginLeft: 4, width: 16, height: 16 }} />
                                </Button>
                            </Box>
                        </Box>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
