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
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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

const statusChipColor = (status: string): 'warning' | 'info' | 'success' | 'default' => {
    const map: Record<string, 'warning' | 'info' | 'success' | 'default'> = {
        pending: 'warning',
        in_progress: 'info',
        resolved: 'success',
        cancelled: 'default',
    };
    return map[status] || 'warning';
};

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    in_progress: 'In Progress',
    resolved: 'Resolved',
    cancelled: 'Cancelled',
};

function formatCategory(value: string): string {
    return value.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

export default function ReportsIndex({ reports, filters, categories, statuses }: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || 'all');
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
                router.get('/dashboard/reports', buildParams(), { preserveState: true, preserveScroll: true });
            }
        }, 300);
        return () => clearTimeout(delay);
    }, [searchTerm]);

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get('/dashboard/reports', buildParams({ status: value !== 'all' ? value : undefined }), { preserveState: true, preserveScroll: true });
    };

    const handleCategoryChange = (value: string) => {
        setCategoryFilter(value);
        router.get('/dashboard/reports', buildParams({ category: value !== 'all' ? value : undefined }), { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (page: number) => {
        router.get('/dashboard/reports', buildParams({ page }), { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports & Conflicts" />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2, height: '100%' }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                    <AlertTriangle style={{ width: 20, height: 20, color: '#f97316' }} />
                                    <Box>
                                        <CardTitle>Reports & Conflicts</CardTitle>
                                        <CardDescription>Review and resolve user-submitted reports</CardDescription>
                                    </Box>
                                </Box>
                                <Chip label={`${reports.total} total`} size="small" variant="outlined" />
                            </Box>
                            <Box sx={{ display: 'flex', flexDirection: { xs: 'column', sm: 'row' }, gap: 1 }}>
                                <Box sx={{ position: 'relative', flex: 1 }}>
                                    <Search style={{ position: 'absolute', top: 10, left: 10, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                    <Input
                                        type="search"
                                        placeholder="Search by report number or user..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        style={{ paddingLeft: 36 }}
                                    />
                                </Box>
                                <Select value={statusFilter} onValueChange={handleStatusChange}>
                                    <SelectTrigger style={{ width: 160 }}>
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Statuses</SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={categoryFilter} onValueChange={handleCategoryChange}>
                                    <SelectTrigger style={{ width: 176 }}>
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        {categories.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>{c.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        {reports.data.length === 0 ? (
                            <Box sx={{ py: 6, textAlign: 'center', color: 'text.secondary' }}>
                                <AlertTriangle style={{ margin: '0 auto 8px', width: 32, height: 32, opacity: 0.4 }} />
                                <Typography>No reports found.</Typography>
                            </Box>
                        ) : (
                            <>
                                <Box sx={{ overflowX: 'auto' }}>
                                    <Box component="table" sx={{ width: '100%' }}>
                                        <thead>
                                            <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Report #</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>User</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Category</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Status</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Order</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Date</Box>
                                                <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Actions</Box>
                                            </Box>
                                        </thead>
                                        <tbody>
                                            {reports.data.map((report) => {
                                                return (
                                                    <Box
                                                        component="tr"
                                                        key={report.id}
                                                        sx={{
                                                            borderBottom: 1,
                                                            borderColor: 'divider',
                                                            '&:last-child': { borderBottom: 0 },
                                                            '&:hover': { bgcolor: 'action.hover' },
                                                        }}
                                                    >
                                                        <Box component="td" sx={{ p: 1, fontSize: '0.875rem', fontFamily: 'monospace' }}>{report.report_number}</Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>{report.user.name}</Box>
                                                            <Box sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{report.user.email}</Box>
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>{formatCategory(report.category)}</Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Chip
                                                                label={statusLabel[report.status] || 'Pending'}
                                                                color={statusChipColor(report.status)}
                                                                size="small"
                                                                variant="outlined"
                                                            />
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>{report.order_number ?? '\u2014'}</Box>
                                                        <Box component="td" sx={{ p: 1, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                            {report.created_at ? new Date(report.created_at).toLocaleDateString() : '\u2014'}
                                                        </Box>
                                                        <Box component="td" sx={{ p: 1 }}>
                                                            <Button asChild variant="ghost" size="sm">
                                                                <Link href={`/dashboard/reports/${report.id}`}>
                                                                    <Eye style={{ marginRight: 4, width: 16, height: 16 }} /> View
                                                                </Link>
                                                            </Button>
                                                        </Box>
                                                    </Box>
                                                );
                                            })}
                                        </tbody>
                                    </Box>
                                </Box>
                                {reports.last_page > 1 && (
                                    <Box sx={{ mt: 2, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Page {reports.current_page} of {reports.last_page} ({reports.total} total)
                                        </Typography>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={reports.current_page === 1}
                                                onClick={() => handlePageChange(reports.current_page - 1)}
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={reports.current_page === reports.last_page}
                                                onClick={() => handlePageChange(reports.current_page + 1)}
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
            </Box>
        </AppLayout>
    );
}
