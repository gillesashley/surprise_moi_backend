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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Eye } from 'lucide-react';
import { useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    status: string;
    is_team: boolean;
    created_at: string;
    region?: { name: string };
    city?: { name: string };
};

type Props = {
    applications: {
        data: Application[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: { status?: string; region_id?: number; type?: 'individual' | 'team' };
    statuses: Array<{ value: string; label: string }>;
};

const statusClasses: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    under_review: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

export default function FieldAgentApplicationsIndex({
    applications,
    filters,
    statuses,
}: Props) {
    useInactivityLock();
    const [status, setStatus] = useState(filters.status ?? '');
    const [type, setType] = useState(filters.type ?? '');

    const applyFilter = (newStatus: string) => {
        setStatus(newStatus);
        router.get(
            '/dashboard/field-agent-applications',
            {
                status: newStatus || undefined,
                type: type || undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const applyType = (newType: string) => {
        setType(newType);
        router.get(
            '/dashboard/field-agent-applications',
            {
                status: status || undefined,
                type: newType || undefined,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    const breadcrumbs = [
        {
            title: 'Field Agent Applications',
            href: '/dashboard/field-agent-applications',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Field Agent Applications" />
            <Box
                sx={{
                    display: 'flex',
                    height: '100%',
                    flex: 1,
                    flexDirection: 'column',
                    gap: 2,
                    p: 2,
                }}
            >
                <Card>
                    <CardHeader>
                        <CardTitle>Field Agent Applications</CardTitle>
                        <CardDescription>
                            Review and manage applications from potential field
                            agents.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <Box
                            sx={{
                                mb: 2,
                                display: 'flex',
                                alignItems: 'center',
                                gap: 2,
                            }}
                        >
                            <Select
                                value={status || 'all'}
                                onValueChange={(v) =>
                                    applyFilter(v === 'all' ? '' : v)
                                }
                            >
                                <SelectTrigger className="w-48">
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
                                value={type || 'all'}
                                onValueChange={(v) =>
                                    applyType(v === 'all' ? '' : v)
                                }
                            >
                                <SelectTrigger className="w-48">
                                    <SelectValue placeholder="All types" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All Types
                                    </SelectItem>
                                    <SelectItem value="individual">
                                        Individual
                                    </SelectItem>
                                    <SelectItem value="team">Team</SelectItem>
                                </SelectContent>
                            </Select>
                        </Box>

                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box
                                        component="tr"
                                        sx={{
                                            borderBottom: 1,
                                            borderColor: 'divider',
                                        }}
                                    >
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Name
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Email
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Region
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Status
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'left',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Applied
                                        </Box>
                                        <Box
                                            component="th"
                                            sx={{
                                                p: 1,
                                                textAlign: 'right',
                                                fontSize: '0.875rem',
                                                fontWeight: 500,
                                            }}
                                        >
                                            Actions
                                        </Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {applications.data.map((a) => (
                                        <Box
                                            component="tr"
                                            key={a.id}
                                            sx={{
                                                borderBottom: 1,
                                                borderColor: 'divider',
                                                '&:last-child': { border: 0 },
                                                '&:hover': {
                                                    bgcolor: 'action.hover',
                                                },
                                            }}
                                        >
                                            <Box
                                                component="td"
                                                sx={{
                                                    p: 1,
                                                    fontSize: '0.875rem',
                                                }}
                                            >
                                                <Box
                                                    sx={{
                                                        display: 'inline-flex',
                                                        alignItems: 'center',
                                                        gap: 1,
                                                    }}
                                                >
                                                    <span>
                                                        {a.first_name}{' '}
                                                        {a.last_name}
                                                    </span>
                                                    {a.is_team && (
                                                        <Badge variant="secondary">
                                                            Team
                                                        </Badge>
                                                    )}
                                                </Box>
                                            </Box>
                                            <Box
                                                component="td"
                                                sx={{
                                                    p: 1,
                                                    fontSize: '0.875rem',
                                                }}
                                            >
                                                {a.email}
                                            </Box>
                                            <Box
                                                component="td"
                                                sx={{
                                                    p: 1,
                                                    fontSize: '0.875rem',
                                                }}
                                            >
                                                {a.region?.name ?? '—'}
                                            </Box>
                                            <Box
                                                component="td"
                                                sx={{
                                                    p: 1,
                                                    fontSize: '0.875rem',
                                                }}
                                            >
                                                <Badge
                                                    className={
                                                        statusClasses[
                                                            a.status
                                                        ] ?? ''
                                                    }
                                                >
                                                    {a.status}
                                                </Badge>
                                            </Box>
                                            <Box
                                                component="td"
                                                sx={{
                                                    p: 1,
                                                    fontSize: '0.875rem',
                                                }}
                                            >
                                                {new Date(
                                                    a.created_at,
                                                ).toLocaleDateString()}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Box
                                                    sx={{
                                                        display: 'flex',
                                                        justifyContent:
                                                            'flex-end',
                                                    }}
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/dashboard/field-agent-applications/${a.id}`}
                                                        >
                                                            <Eye
                                                                style={{
                                                                    width: 16,
                                                                    height: 16,
                                                                }}
                                                            />
                                                        </Link>
                                                    </Button>
                                                </Box>
                                            </Box>
                                        </Box>
                                    ))}
                                    {applications.data.length === 0 && (
                                        <Box component="tr">
                                            <Box
                                                component="td"
                                                colSpan={6}
                                                sx={{
                                                    p: 4,
                                                    textAlign: 'center',
                                                    color: 'text.secondary',
                                                }}
                                            >
                                                No applications yet.
                                            </Box>
                                        </Box>
                                    )}
                                </tbody>
                            </Box>
                        </Box>

                        {/* Pagination */}
                        {applications.last_page > 1 && (
                            <Box
                                sx={{
                                    mt: 2,
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <Typography
                                    sx={{
                                        fontSize: '0.875rem',
                                        color: 'text.secondary',
                                    }}
                                >
                                    Showing {applications.data.length} of{' '}
                                    {applications.total} applications
                                </Typography>
                                <Box sx={{ display: 'flex', gap: 1 }}>
                                    {applications.links.map((l, i) => {
                                        // Filter out "Next" and "Previous" labels if they contain HTML
                                        const isPrev =
                                            l.label.includes('Previous');
                                        const isNext = l.label.includes('Next');

                                        if (!l.url && (isPrev || isNext))
                                            return null;

                                        return (
                                            <Button
                                                key={i}
                                                variant={
                                                    l.active
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                                size="sm"
                                                disabled={!l.url}
                                                onClick={() =>
                                                    l.url && router.get(l.url)
                                                }
                                            >
                                                <span
                                                    dangerouslySetInnerHTML={{
                                                        __html: l.label,
                                                    }}
                                                />
                                            </Button>
                                        );
                                    })}
                                </Box>
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
