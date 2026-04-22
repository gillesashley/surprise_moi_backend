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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Eye, Footprints, MapPin, Store } from 'lucide-react';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';

interface Visit {
    id: string;
    vendor_name: string;
    field_agent_name: string;
    status: string;
    submitted_at: string | null;
    has_shop: boolean;
    location: string | null;
}

interface Props {
    visits: {
        data: Visit[];
        links: any[];
    };
    filters: {
        status?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vendor Visits',
        href: '/dashboard/vendor-visits',
    },
];

export default function VendorVisitsIndex({ visits }: Props) {
    useInactivityLock();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor Visits - Questionnaires" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4 overflow-auto">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Vendor Visits</h1>
                        <p className="text-muted-foreground">
                            Review questionnaires submitted by field agents during vendor onboarding.
                        </p>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent Questionnaires</CardTitle>
                        <CardDescription>
                            A list of all vendor visits and their questionnaire status.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Vendor</TableHead>
                                    <TableHead>Field Agent</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Location</TableHead>
                                    <TableHead>Submitted</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Action</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visits.data.map((visit) => (
                                    <TableRow key={visit.id}>
                                        <TableCell className="font-medium">
                                            {visit.vendor_name}
                                        </TableCell>
                                        <TableCell>{visit.field_agent_name}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                {visit.has_shop ? (
                                                    <Store className="w-4 h-4 text-blue-500" />
                                                ) : (
                                                    <MapPin className="w-4 h-4 text-orange-500" />
                                                )}
                                                <span className="text-xs">
                                                    {visit.has_shop ? 'Physical Shop' : 'Online/Home'}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell className="max-w-[200px] truncate">
                                            {visit.location || 'N/A'}
                                        </TableCell>
                                        <TableCell>
                                            {visit.submitted_at
                                                ? new Date(visit.submitted_at).toLocaleDateString()
                                                : 'Pending'}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={visit.status === 'submitted' ? 'default' : 'secondary'}>
                                                {visit.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={`/dashboard/vendor-visits/${visit.id}`}>
                                                    <Eye className="w-4 h-4 mr-2" />
                                                    Review
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                                {visits.data.length === 0 && (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center">
                                            No vendor visits found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
