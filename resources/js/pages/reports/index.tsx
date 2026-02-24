import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Eye, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ReportSummary {
    id: number;
    report_number: string;
    category: string;
    description: string;
    status: string;
    user: { id: number; name: string; email: string };
    order_number: string | null;
    created_at: string | null;
}

interface PaginatedReports {
    data: ReportSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Category {
    value: string;
    label: string;
    icon: string;
}

interface Status {
    value: string;
    label: string;
}

interface Props {
    reports: PaginatedReports;
    filters: { status?: string; search?: string; category?: string };
    categories: Category[];
    statuses: Status[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Reports & Conflicts', href: '/dashboard/reports' },
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

export default function ReportsIndex({
    reports,
    filters,
    categories,
    statuses,
}: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [categoryFilter, setCategoryFilter] = useState(
        filters.category || 'all',
    );
    const [searchTerm, setSearchTerm] = useState(filters.search || '');

    const buildParams = (overrides: Record<string, unknown> = {}) => ({
        ...(statusFilter !== 'all' && { status: statusFilter }),
        ...(categoryFilter !== 'all' && { category: categoryFilter }),
        ...(searchTerm && { search: searchTerm }),
        page: 1,
        ...overrides,
    });

    useEffect(() => {
        const delay = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get('/dashboard/reports', buildParams(), {
                    preserveState: true,
                    preserveScroll: true,
                });
            }
        }, 300);
        return () => clearTimeout(delay);
    }, [searchTerm]);

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get(
            '/dashboard/reports',
            buildParams({ status: value !== 'all' ? value : undefined }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleCategoryChange = (value: string) => {
        setCategoryFilter(value);
        router.get(
            '/dashboard/reports',
            buildParams({ category: value !== 'all' ? value : undefined }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get('/dashboard/reports', buildParams({ page }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports & Conflicts" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <AlertTriangle className="h-5 w-5 text-orange-500" />
                                    <div>
                                        <CardTitle>
                                            Reports & Conflicts
                                        </CardTitle>
                                        <CardDescription>
                                            Review and resolve user-submitted
                                            reports
                                        </CardDescription>
                                    </div>
                                </div>
                                <Badge className="text-sm">
                                    {reports.total} total
                                </Badge>
                            </div>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <div className="relative flex-1">
                                    <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Search by report number or user..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                                <Select
                                    value={statusFilter}
                                    onValueChange={handleStatusChange}
                                >
                                    <SelectTrigger className="w-40">
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
                                        </SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={categoryFilter}
                                    onValueChange={handleCategoryChange}
                                >
                                    <SelectTrigger className="w-44">
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Categories
                                        </SelectItem>
                                        {categories.map((c) => (
                                            <SelectItem
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {reports.data.length === 0 ? (
                            <div className="py-12 text-center text-muted-foreground">
                                <AlertTriangle className="mx-auto mb-2 h-8 w-8 opacity-40" />
                                <p>No reports found.</p>
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto">
                                    <table className="w-full">
                                        <thead>
                                            <tr className="border-b">
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Report #
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    User
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Category
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Status
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Order
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Date
                                                </th>
                                                <th className="p-2 text-left text-sm font-medium">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {reports.data.map((report) => {
                                                const sc =
                                                    statusConfig[
                                                        report.status
                                                    ] ?? statusConfig.pending;
                                                return (
                                                    <tr
                                                        key={report.id}
                                                        className="border-b last:border-0 hover:bg-muted/30"
                                                    >
                                                        <td className="p-2 font-mono text-sm">
                                                            {
                                                                report.report_number
                                                            }
                                                        </td>
                                                        <td className="p-2">
                                                            <div className="text-sm font-medium">
                                                                {
                                                                    report.user
                                                                        .name
                                                                }
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {
                                                                    report.user
                                                                        .email
                                                                }
                                                            </div>
                                                        </td>
                                                        <td className="p-2 text-sm">
                                                            {formatCategory(
                                                                report.category,
                                                            )}
                                                        </td>
                                                        <td className="p-2">
                                                            <Badge
                                                                className={
                                                                    sc.color
                                                                }
                                                            >
                                                                {sc.label}
                                                            </Badge>
                                                        </td>
                                                        <td className="p-2 text-sm">
                                                            {report.order_number ??
                                                                '—'}
                                                        </td>
                                                        <td className="p-2 text-sm text-muted-foreground">
                                                            {report.created_at
                                                                ? new Date(
                                                                      report.created_at,
                                                                  ).toLocaleDateString()
                                                                : '—'}
                                                        </td>
                                                        <td className="p-2">
                                                            <Button
                                                                asChild
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                <Link
                                                                    href={`/dashboard/reports/${report.id}`}
                                                                >
                                                                    <Eye className="mr-1 h-4 w-4" />{' '}
                                                                    View
                                                                </Link>
                                                            </Button>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                                {reports.last_page > 1 && (
                                    <div className="mt-4 flex items-center justify-between">
                                        <p className="text-sm text-muted-foreground">
                                            Page {reports.current_page} of{' '}
                                            {reports.last_page} ({reports.total}{' '}
                                            total)
                                        </p>
                                        <div className="flex gap-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    reports.current_page === 1
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        reports.current_page -
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
                                                    reports.current_page ===
                                                    reports.last_page
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        reports.current_page +
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
            </div>
        </AppLayout>
    );
}
