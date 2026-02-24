import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    Clock,
    Computer,
    Globe,
    Monitor,
    RefreshCw,
    Smartphone,
    Tablet,
    Wifi,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Client Errors',
        href: '/dashboard/client-errors',
    },
];

interface DeviceInfo {
    os?: string;
    os_version?: string;
    device?: string;
    device_type?: string;
    browser?: string;
    browser_version?: string;
    screen_resolution?: string;
    network?: string;
    language?: string;
    timezone?: string;
}

interface ClientError {
    id: number;
    user_id: number | null;
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
    device_info: DeviceInfo;
    occurred_at: string;
    error: string;
    payload: Record<string, unknown> | null;
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
    updated_at: string;
}

interface Pagination {
    current_page: number;
    total_pages: number;
    per_page: number;
    total: number;
}

interface Statistics {
    total: number;
    by_os: Record<string, number>;
    by_device: Record<string, number>;
    by_browser: Record<string, number>;
    by_network: Record<string, number>;
    by_resolution: Record<string, number>;
    daily: Record<string, number>;
}

interface Props {
    initialErrors: ClientError[];
    initialPagination: Pagination;
    initialStatistics: Statistics;
}

export default function Index({
    initialErrors,
    initialPagination,
    initialStatistics,
}: Props) {
    const [errors, setErrors] = useState<ClientError[]>(initialErrors);
    const [statistics, setStatistics] = useState<Statistics>(initialStatistics);
    const [pagination, setPagination] = useState<Pagination>(initialPagination);
    const [refreshing, setRefreshing] = useState(false);
    const [selectedError, setSelectedError] = useState<ClientError | null>(
        null,
    );

    const goToPage = (page: number) => {
        if (page < 1 || page > pagination.total_pages) return;

        setRefreshing(true);
        router.reload({
            only: ['initialErrors', 'initialPagination'],
            data: { page, per_page: 20 },
            onSuccess: (pageProps) => {
                setErrors(pageProps.props.initialErrors as ClientError[]);
                setPagination(pageProps.props.initialPagination as Pagination);
            },
            onError: () => {
                console.error('Failed to fetch errors');
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
                setErrors(page.props.initialErrors as ClientError[]);
                setStatistics(page.props.initialStatistics as Statistics);
                setPagination(page.props.initialPagination as Pagination);
            },
            onFinish: () => {
                setRefreshing(false);
            },
        });
    };

    const getDeviceIcon = (deviceType: string) => {
        switch (deviceType?.toLowerCase()) {
            case 'desktop':
                return <Monitor className="h-4 w-4" />;
            case 'tablet':
                return <Tablet className="h-4 w-4" />;
            case 'mobile':
                return <Smartphone className="h-4 w-4" />;
            default:
                return <Computer className="h-4 w-4" />;
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const truncateError = (error: string, maxLength: number = 100) => {
        if (!error) return 'No error message';
        if (error.length <= maxLength) return error;
        return error.substring(0, maxLength) + '...';
    };

    const topOs = Object.entries(statistics.by_os || {})
        .sort((a, b) => (b[1] as number) - (a[1] as number))
        .slice(0, 5);

    const topDevices = Object.entries(statistics.by_device || {})
        .sort((a, b) => (b[1] as number) - (a[1] as number))
        .slice(0, 5);

    const topBrowsers = Object.entries(statistics.by_browser || {})
        .sort((a, b) => (b[1] as number) - (a[1] as number))
        .slice(0, 5);

    const topNetworks = Object.entries(statistics.by_network || {})
        .sort((a, b) => (b[1] as number) - (a[1] as number))
        .slice(0, 5);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Client Errors Dashboard" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Client Errors Dashboard
                        </h1>
                        <p className="text-gray-600">
                            Monitor client-side errors from web and mobile apps
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

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Errors
                            </CardTitle>
                            <XCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.total}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Top OS
                            </CardTitle>
                            <Monitor className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="truncate text-2xl font-bold">
                                {topOs[0]?.[0] || 'N/A'}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {topOs[0]?.[1] || 0} errors
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Top Device
                            </CardTitle>
                            <Smartphone className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="truncate text-2xl font-bold capitalize">
                                {topDevices[0]?.[0] || 'N/A'}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {topDevices[0]?.[1] || 0} errors
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Top Browser
                            </CardTitle>
                            <Globe className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="truncate text-2xl font-bold">
                                {topBrowsers[0]?.[0] || 'N/A'}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {topBrowsers[0]?.[1] || 0} errors
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Errors by OS
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {topOs.map(([os, count]) => (
                                    <div
                                        key={os}
                                        className="flex items-center justify-between"
                                    >
                                        <span className="text-sm">{os}</span>
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-24 rounded-full bg-gray-200">
                                                <div
                                                    className="h-2 rounded-full bg-blue-600"
                                                    style={{
                                                        width: `${
                                                            ((count as number) /
                                                                statistics.total) *
                                                            100
                                                        }%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm text-gray-500">
                                                {count as number}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {topOs.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        No data
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Errors by Device
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {topDevices.map(([device, count]) => (
                                    <div
                                        key={device}
                                        className="flex items-center justify-between"
                                    >
                                        <div className="flex items-center gap-2">
                                            {getDeviceIcon(device)}
                                            <span className="text-sm capitalize">
                                                {device}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-24 rounded-full bg-gray-200">
                                                <div
                                                    className="h-2 rounded-full bg-green-600"
                                                    style={{
                                                        width: `${
                                                            ((count as number) /
                                                                statistics.total) *
                                                            100
                                                        }%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm text-gray-500">
                                                {count as number}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {topDevices.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        No data
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Errors by Browser
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {topBrowsers.map(([browser, count]) => (
                                    <div
                                        key={browser}
                                        className="flex items-center justify-between"
                                    >
                                        <span className="text-sm">
                                            {browser}
                                        </span>
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-24 rounded-full bg-gray-200">
                                                <div
                                                    className="h-2 rounded-full bg-purple-600"
                                                    style={{
                                                        width: `${
                                                            ((count as number) /
                                                                statistics.total) *
                                                            100
                                                        }%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm text-gray-500">
                                                {count as number}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {topBrowsers.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        No data
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">
                                Errors by Network
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-2">
                                {topNetworks.map(([network, count]) => (
                                    <div
                                        key={network}
                                        className="flex items-center justify-between"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Wifi className="h-4 w-4 text-gray-400" />
                                            <span className="text-sm">
                                                {network}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <div className="h-2 w-24 rounded-full bg-gray-200">
                                                <div
                                                    className="h-2 rounded-full bg-orange-600"
                                                    style={{
                                                        width: `${
                                                            ((count as number) /
                                                                statistics.total) *
                                                            100
                                                        }%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-sm text-gray-500">
                                                {count as number}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                                {topNetworks.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        No data
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex gap-6">
                    <div className={`${selectedError ? 'w-2/3' : 'w-full'}`}>
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent Errors</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="min-h-96 rounded-lg border">
                                    {errors.length === 0 ? (
                                        <div className="flex h-96 items-center justify-center">
                                            <p className="text-gray-500">
                                                No client errors found
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="divide-y">
                                            {errors.map((error) => (
                                                <div
                                                    key={error.id}
                                                    className={`flex cursor-pointer items-center justify-between p-4 hover:bg-gray-50 ${
                                                        selectedError?.id ===
                                                        error.id
                                                            ? 'bg-blue-50'
                                                            : ''
                                                    }`}
                                                    onClick={() =>
                                                        setSelectedError(error)
                                                    }
                                                >
                                                    <div className="flex items-center space-x-4">
                                                        <AlertTriangle className="h-5 w-5 text-red-500" />
                                                        <div>
                                                            <div className="flex items-center space-x-2">
                                                                <span className="font-medium">
                                                                    {truncateError(
                                                                        error.error,
                                                                    )}
                                                                </span>
                                                            </div>
                                                            <div className="text-sm text-gray-500">
                                                                {error.user
                                                                    ? `${error.user.name} (${error.user.email})`
                                                                    : 'Anonymous User'}
                                                                •
                                                                {error
                                                                    .device_info
                                                                    ?.device_type &&
                                                                    ` ${error.device_info.device_type}`}
                                                                {error
                                                                    .device_info
                                                                    ?.browser &&
                                                                    ` • ${error.device_info.browser}`}
                                                            </div>
                                                            <div className="text-xs text-gray-400">
                                                                <Clock className="mr-1 inline h-3 w-3" />
                                                                {formatDate(
                                                                    error.occurred_at,
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div className="text-right">
                                                        <div className="text-sm text-gray-500">
                                                            ID: {error.id}
                                                        </div>
                                                        {error.ip_address && (
                                                            <div className="text-xs text-gray-400">
                                                                {
                                                                    error.ip_address
                                                                }
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="mt-4 flex items-center justify-between border-t pt-4">
                                    <div className="text-sm text-gray-500">
                                        Showing {errors.length} of{' '}
                                        {pagination.total} errors
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
                                                goToPage(
                                                    pagination.current_page - 1,
                                                )
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
                                                goToPage(
                                                    pagination.current_page + 1,
                                                )
                                            }
                                            disabled={
                                                pagination.current_page >=
                                                    pagination.total_pages ||
                                                refreshing
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

                    {selectedError && (
                        <div className="w-1/3">
                            <Card className="sticky top-4">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="text-lg">
                                            Error Details
                                        </CardTitle>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                setSelectedError(null)
                                            }
                                        >
                                            ✕
                                        </Button>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <h4 className="mb-1 text-sm font-semibold">
                                            Error Message
                                        </h4>
                                        <div className="rounded bg-red-50 p-2 text-sm text-red-700">
                                            {selectedError.error}
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="mb-1 text-sm font-semibold">
                                            User
                                        </h4>
                                        <p className="text-sm">
                                            {selectedError.user
                                                ? `${selectedError.user.name} (${selectedError.user.email})`
                                                : 'Anonymous'}
                                        </p>
                                    </div>

                                    <div>
                                        <h4 className="mb-1 text-sm font-semibold">
                                            Timestamp
                                        </h4>
                                        <p className="text-sm">
                                            {formatDate(
                                                selectedError.occurred_at,
                                            )}
                                        </p>
                                    </div>

                                    <div>
                                        <h4 className="mb-1 text-sm font-semibold">
                                            Device Info
                                        </h4>
                                        <div className="space-y-1 text-sm">
                                            <p>
                                                <span className="text-gray-500">
                                                    OS:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.os || 'Unknown'}
                                            </p>
                                            <p>
                                                <span className="text-gray-500">
                                                    Device:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.device || 'Unknown'}
                                            </p>
                                            <p>
                                                <span className="text-gray-500">
                                                    Type:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.device_type || 'Unknown'}
                                            </p>
                                            <p>
                                                <span className="text-gray-500">
                                                    Browser:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.browser || 'Unknown'}
                                            </p>
                                            <p>
                                                <span className="text-gray-500">
                                                    Resolution:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.screen_resolution ||
                                                    'Unknown'}
                                            </p>
                                            <p>
                                                <span className="text-gray-500">
                                                    Network:
                                                </span>{' '}
                                                {selectedError.device_info
                                                    ?.network || 'Unknown'}
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h4 className="mb-1 text-sm font-semibold">
                                            Network
                                        </h4>
                                        <p className="text-sm">
                                            {selectedError.ip_address ||
                                                'No IP'}
                                        </p>
                                    </div>

                                    {selectedError.payload && (
                                        <div>
                                            <h4 className="mb-1 text-sm font-semibold">
                                                Payload
                                            </h4>
                                            <pre className="max-h-40 overflow-auto rounded bg-gray-100 p-2 text-xs">
                                                {JSON.stringify(
                                                    selectedError.payload,
                                                    null,
                                                    2,
                                                )}
                                            </pre>
                                        </div>
                                    )}

                                    {selectedError.user_agent && (
                                        <div>
                                            <h4 className="mb-1 text-sm font-semibold">
                                                User Agent
                                            </h4>
                                            <p className="text-xs break-all text-gray-500">
                                                {selectedError.user_agent}
                                            </p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
