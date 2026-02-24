import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
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

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'completed':
                return 'bg-green-100 text-green-800';
            case 'failed':
                return 'bg-red-100 text-red-800';
            case 'processing':
                return 'bg-blue-100 text-blue-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed':
                return <CheckCircle className="h-4 w-4" />;
            case 'failed':
                return <XCircle className="h-4 w-4" />;
            case 'processing':
                return <RefreshCw className="h-4 w-4 animate-spin" />;
            case 'pending':
                return <Clock className="h-4 w-4" />;
            default:
                return <AlertTriangle className="h-4 w-4" />;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jobs Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-auto p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Jobs Dashboard
                        </h1>
                        <p className="text-gray-600">
                            Monitor background job performance and status
                        </p>
                    </div>
                    <Button
                        onClick={handleRefresh}
                        disabled={refreshing}
                        variant="outline"
                    >
                        <RefreshCw
                            className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`}
                        />
                        Refresh
                    </Button>
                </div>

                {/* Statistics Cards */}
                {statistics && (
                    <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Total Failed Jobs
                                </CardTitle>
                                <XCircle className="h-4 w-4 text-red-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {statistics.total_failed}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Recent Failures (24h)
                                </CardTitle>
                                <Clock className="h-4 w-4 text-yellow-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {statistics.recent_failures}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Active Queues
                                </CardTitle>
                                <AlertTriangle className="h-4 w-4 text-orange-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {
                                        Object.keys(statistics.by_queue || {})
                                            .length
                                    }
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Top Queue
                                </CardTitle>
                                <CheckCircle className="h-4 w-4 text-blue-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="truncate text-2xl font-bold">
                                    {Object.entries(
                                        statistics.by_queue || {},
                                    ).sort(
                                        (a, b) =>
                                            (b[1] as number) - (a[1] as number),
                                    )[0]?.[0] || 'N/A'}
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {Object.entries(
                                        statistics.by_queue || {},
                                    ).sort(
                                        (a, b) =>
                                            (b[1] as number) - (a[1] as number),
                                    )[0]?.[1] || 0}{' '}
                                    jobs
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Recent Jobs */}
                <Card>
                    <CardHeader>
                        <CardTitle>Recent Jobs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="min-h-96 rounded-lg border">
                            {jobs.length === 0 ? (
                                <div className="flex h-96 items-center justify-center">
                                    <p className="text-gray-500">
                                        No failed jobs found
                                    </p>
                                </div>
                            ) : (
                                <div className="divide-y">
                                    {jobs.map((job) => {
                                        const status = 'failed';
                                        const jobName =
                                            job.payload?.displayName ||
                                            job.payload?.job ||
                                            'Unknown Job';

                                        return (
                                            <div
                                                key={job.id}
                                                className="flex items-center justify-between p-4 hover:bg-gray-50"
                                            >
                                                <div className="flex items-center space-x-4">
                                                    {getStatusIcon(status)}
                                                    <div>
                                                        <div className="flex items-center space-x-2">
                                                            <span className="font-medium">
                                                                {jobName}
                                                            </span>
                                                            <Badge
                                                                className={getStatusColor(
                                                                    status,
                                                                )}
                                                            >
                                                                {status}
                                                            </Badge>
                                                        </div>
                                                        <div className="text-sm text-gray-500">
                                                            Queue: {job.queue} •
                                                            Connection:{' '}
                                                            {job.connection}
                                                        </div>
                                                        <div className="text-xs text-gray-400">
                                                            Failed:{' '}
                                                            {formatDate(
                                                                job.failed_at,
                                                            )}
                                                        </div>
                                                        {job.exception && (
                                                            <div className="mt-2 max-w-md">
                                                                <div className="rounded bg-red-50 px-2 py-1 text-xs text-red-700">
                                                                    <span className="font-semibold">
                                                                        Error:{' '}
                                                                    </span>
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
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="text-sm text-gray-500">
                                                        ID: {job.id}
                                                    </div>
                                                    {job.payload?.data?.id && (
                                                        <div className="text-xs text-gray-400">
                                                            Entity ID:{' '}
                                                            {
                                                                job.payload.data
                                                                    .id
                                                            }
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>

                        {/* Pagination Controls */}
                        <div className="mt-4 flex items-center justify-between border-t pt-4">
                            <div className="text-sm text-gray-500">
                                Showing {jobs.length} of {pagination.total} jobs
                                <span className="ml-2">
                                    (Page {pagination.current_page} of{' '}
                                    {pagination.total_pages})
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
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
                                    <ChevronLeft className="mr-1 h-4 w-4" />
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
                                    <ChevronRight className="ml-1 h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
