import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    status: string;
    created_at: string;
    region?: { name: string };
    city?: { name: string };
};

type Props = {
    applications: {
        data: Application[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { status?: string; region_id?: number };
    statuses: Array<{ value: string; label: string }>;
};

const statusClasses: Record<string, string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    under_review: 'bg-blue-100 text-blue-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

export default function FieldAgentApplicationsIndex({ applications, filters, statuses }: Props) {
    useInactivityLock();
    const [status, setStatus] = useState(filters.status ?? '');

    const applyFilter = (newStatus: string) => {
        setStatus(newStatus);
        router.get(
            '/dashboard/field-agent-applications',
            { status: newStatus || undefined },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <AppLayout>
            <Head title="Field Agent Applications" />
            <div className="p-6">
                <h1 className="text-2xl font-semibold">Field Agent Applications</h1>

                <div className="mt-4 flex gap-2">
                    <Select value={status || 'all'} onValueChange={(v) => applyFilter(v === 'all' ? '' : v)}>
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All</SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s.value} value={s.value}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="mt-4 rounded border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Region</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Applied</TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {applications.data.map((a) => (
                                <TableRow key={a.id}>
                                    <TableCell>
                                        {a.first_name} {a.last_name}
                                    </TableCell>
                                    <TableCell>{a.email}</TableCell>
                                    <TableCell>{a.region?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge className={statusClasses[a.status] ?? ''}>{a.status}</Badge>
                                    </TableCell>
                                    <TableCell>{new Date(a.created_at).toLocaleDateString()}</TableCell>
                                    <TableCell className="text-right">
                                        <Link
                                            href={`/dashboard/field-agent-applications/${a.id}`}
                                            className="text-primary underline"
                                        >
                                            View
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {applications.data.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-center text-muted-foreground">
                                        No applications yet.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="mt-4 flex flex-wrap gap-1">
                    {applications.links.map((l, i) => (
                        <Button
                            key={i}
                            variant={l.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!l.url}
                            onClick={() => l.url && router.get(l.url)}
                        >
                            <span dangerouslySetInnerHTML={{ __html: l.label }} />
                        </Button>
                    ))}
                </div>
            </div>
        </AppLayout>
    );
}
