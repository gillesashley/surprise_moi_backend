import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    CheckCircle,
    XCircle,
    Clock,
    AlertCircle,
    FileText,
    Eye
} from 'lucide-react';

interface VisitRow {
    id: string;
    status: string;
    started_at: string;
    submitted_at: string | null;
    vendor: { id: number; business_name: string | null; name: string };
    field_agent: { id: number; name: string };
}

interface Props {
    tab: 'needs-review' | 'recent-failures' | 'all';
    visits: {
        data: VisitRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

const TABS: Array<{ key: Props['tab']; label: string }> = [
    { key: 'needs-review', label: 'Needs review' },
    { key: 'recent-failures', label: 'Recent failures' },
    { key: 'all', label: 'All visits' },
];

export default function AdminVendorVisitsIndex({ tab, visits }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/dashboard' },
                { title: 'Vendor visits', href: '/dashboard/vendor-visits' },
            ]}
        >
            <Head title="Vendor visits" />
            <Box sx={{ p: 2 }}>
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                            <Box>
                                <CardTitle>Vendor visits</CardTitle>
                                <CardDescription>Manage and review vendor verification visits</CardDescription>
                            </Box>
                            <Box sx={{ width: 400 }}>
                                <Tabs value={tab} onValueChange={(val) => router.visit(`/dashboard/vendor-visits?tab=${val}`)}>
                                    <TabsList>
                                        {TABS.map((t) => (
                                            <TabsTrigger key={t.key} value={t.key}>
                                                {t.label}
                                            </TabsTrigger>
                                        ))}
                                    </TabsList>
                                </Tabs>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        {visits.data.length === 0 ? (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No visits found.
                            </Box>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Agent</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Started</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {visits.data.map((v) => (
                                        <TableRow key={v.id}>
                                            <TableCell className="font-medium">{v.vendor.business_name ?? v.vendor.name}</TableCell>
                                            <TableCell>{v.field_agent.name}</TableCell>
                                            <TableCell>
                                                <StatusBadge status={v.status} />
                                            </TableCell>
                                            <TableCell>{new Date(v.started_at).toLocaleString()}</TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/dashboard/vendor-visits/${v.id}`}>
                                                        <Eye style={{ marginRight: 8, width: 16, height: 16 }} />
                                                        View
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const variants: Record<
        string,
        { color: 'success' | 'error' | 'warning' | 'info' | 'default'; icon: React.ReactNode; label: string }
    > = {
        passed: {
            color: 'success',
            icon: <CheckCircle style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Passed',
        },
        failed: {
            color: 'error',
            icon: <XCircle style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Failed',
        },
        submitted: {
            color: 'warning',
            icon: <Clock style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Needs Review',
        },
        revoked: {
            color: 'default',
            icon: <AlertCircle style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Revoked',
        },
        draft: {
            color: 'info',
            icon: <FileText style={{ marginRight: 4, width: 12, height: 12 }} />,
            label: 'Draft',
        },
    };

    const config = variants[status] || { color: 'default', icon: null, label: status };

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
}
