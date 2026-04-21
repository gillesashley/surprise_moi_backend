import AppLayout from '@/layouts/app-layout';
import { Head, useForm, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    ArrowLeft,
    CheckCircle,
    XCircle,
    MapPin,
    AlertTriangle,
    Clock,
    FileText,
    Image as ImageIcon,
    ShieldAlert
} from 'lucide-react';

interface Item {
    id: number;
    item_key: string;
    category: string;
    criticality: string;
    passed: boolean | null;
    note: string | null;
}
interface Visit {
    id: string;
    status: string;
    computed_result: string | null;
    admin_override_result: string | null;
    admin_override_reason: string | null;
    admin_override_at: string | null;
    override_by: { id: number; name: string } | null;
    started_at: string;
    submitted_at: string | null;
    visit_latitude: string;
    visit_longitude: string;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    escalated: boolean;
    badge_issued_at: string | null;
    badge_expires_at: string | null;
    vendor: {
        id: number;
        business_name: string | null;
        name: string;
        email: string;
        phone: string | null;
        field_verified_until: string | null;
    };
    field_agent: { id: number; name: string };
    items: Item[];
}
interface Props {
    visit: Visit;
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
            icon: <AlertTriangle style={{ marginRight: 4, width: 12, height: 12 }} />,
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

export default function AdminVendorVisitShow({ visit }: Props) {
    const override = useForm({ result: 'passed', reason: '' });
    const revoke = useForm({ reason: '' });

    const storefrontSrc = visit.storefront_photo_path ? `/storage/${visit.storefront_photo_path}` : null;
    const ownerSrc = visit.owner_photo_path ? `/storage/${visit.owner_photo_path}` : null;
    const mapsUrl = `https://maps.google.com/?q=${visit.visit_latitude},${visit.visit_longitude}`;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/dashboard' },
                { title: 'Vendor visits', href: '/dashboard/vendor-visits' },
                { title: visit.vendor.business_name ?? visit.vendor.name, href: '#' },
            ]}
        >
            <Head title="Visit detail" />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, overflow: 'auto', p: 2, maxWidth: '1000px', mx: 'auto' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/dashboard/vendor-visits">
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Visits
                        </Link>
                    </Button>
                    <Box sx={{ display: 'flex', gap: 2, alignItems: 'center' }}>
                        {visit.escalated && <Chip label="Escalated" color="error" size="small" />}
                        <StatusBadge status={visit.status} />
                    </Box>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>{visit.vendor.business_name ?? visit.vendor.name}</CardTitle>
                        <CardDescription>
                            Verification visit conducted by {visit.field_agent.name}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                            <Box>
                                <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Started At</Box>
                                <Box sx={{ fontWeight: 500 }}>{new Date(visit.started_at).toLocaleString()}</Box>
                            </Box>
                            <Box>
                                <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Location</Box>
                                <Box sx={{ fontWeight: 500 }}>
                                    <a href={mapsUrl} target="_blank" rel="noreferrer" className="text-primary hover:underline flex items-center">
                                        <MapPin style={{ width: 14, height: 14, marginRight: 4 }} />
                                        View on Map
                                    </a>
                                </Box>
                            </Box>
                            <Box>
                                <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Computed Result</Box>
                                <Box sx={{ fontWeight: 500 }}>
                                    {visit.computed_result ? (
                                        <Badge variant={visit.computed_result === 'passed' ? 'default' : 'destructive'}>
                                            {visit.computed_result}
                                        </Badge>
                                    ) : (
                                        <span className="text-muted-foreground">Pending</span>
                                    )}
                                </Box>
                            </Box>
                        </Box>
                    </CardContent>
                </Card>

                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: '2fr 1fr' }, alignItems: 'start' }}>
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <FileText style={{ width: 20, height: 20 }} />
                                    Checklist
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ul className="space-y-3">
                                    {visit.items.map((it) => (
                                        <li key={it.id} className="border-b pb-2 last:border-0 last:pb-0">
                                            <div className="flex items-start justify-between">
                                                <div className="flex items-start gap-2">
                                                    {it.criticality === 'critical' ? (
                                                        <AlertTriangle className="text-destructive mt-0.5" style={{ width: 16, height: 16 }} />
                                                    ) : (
                                                        <div className="w-4 h-4 mt-0.5 rounded-full bg-muted" />
                                                    )}
                                                    <div>
                                                        <span className="font-medium text-sm">{it.item_key}</span>
                                                        {it.note && <div className="text-sm text-muted-foreground mt-1 italic">“{it.note}”</div>}
                                                    </div>
                                                </div>
                                                <Badge variant={it.passed === null ? 'outline' : it.passed ? 'default' : 'destructive'} className={it.passed ? 'bg-green-600 hover:bg-green-700' : ''}>
                                                    {it.passed === null ? 'unanswered' : it.passed ? 'pass' : 'fail'}
                                                </Badge>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </CardContent>
                        </Card>

                        {(storefrontSrc || ownerSrc) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <ImageIcon style={{ width: 20, height: 20 }} />
                                        Photos
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                        {storefrontSrc && (
                                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                                <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Storefront</Box>
                                                <a href={storefrontSrc} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-md border">
                                                    <img src={storefrontSrc} alt="Storefront" className="h-48 w-full object-cover transition-transform hover:scale-105" />
                                                </a>
                                            </Box>
                                        )}
                                        {ownerSrc && (
                                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                                <Box sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Owner / ID</Box>
                                                <a href={ownerSrc} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-md border">
                                                    <img src={ownerSrc} alt="Owner" className="h-48 w-full object-cover transition-transform hover:scale-105" />
                                                </a>
                                            </Box>
                                        )}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}
                        
                        {visit.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-lg">Agent Notes</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ p: 3, bgcolor: 'action.hover', borderRadius: 1, whiteSpace: 'pre-line', fontSize: '0.875rem' }}>
                                        {visit.notes}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}
                    </Box>

                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <ShieldAlert style={{ width: 20, height: 20 }} />
                                    Admin Override
                                </CardTitle>
                                <CardDescription>Override the computed result</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {visit.admin_override_result && (
                                    <Box sx={{ mb: 3, p: 2, bgcolor: 'warning.light', color: 'warning.dark', borderRadius: 1, fontSize: '0.875rem' }}>
                                        Previously overridden to <span className="font-bold">{visit.admin_override_result}</span> by{' '}
                                        {visit.override_by?.name}: “{visit.admin_override_reason}”
                                    </Box>
                                )}
                                <form
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        override.post(`/dashboard/vendor-visits/${visit.id}/override`);
                                    }}
                                    className="space-y-3"
                                >
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Box component="label" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Result</Box>
                                        <Select
                                            value={override.data.result}
                                            onValueChange={(val) => override.setData('result', val)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select result" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="passed">Mark passed</SelectItem>
                                                <SelectItem value="failed">Mark failed</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </Box>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Box component="label" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>Reason</Box>
                                        <Textarea
                                            required
                                            placeholder="Reason for override..."
                                            value={override.data.reason}
                                            onChange={(e) => override.setData('reason', e.target.value)}
                                            rows={3}
                                        />
                                    </Box>
                                    <Button
                                        type="submit"
                                        disabled={override.processing}
                                        className="w-full"
                                    >
                                        Apply Override
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>

                        {visit.status === 'passed' && (
                            <Card className="border-destructive/50 bg-destructive/5">
                                <CardHeader>
                                    <CardTitle className="text-lg text-destructive">Revoke Active Badge</CardTitle>
                                    <CardDescription>Immediately remove Field Verified status</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            if (!confirm('Revoke this badge? The vendor will lose Field Verified status immediately.')) return;
                                            revoke.post(`/dashboard/vendor-visits/${visit.id}/revoke`);
                                        }}
                                        className="space-y-3"
                                    >
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Textarea
                                                required
                                                placeholder="Reason for revoking..."
                                                value={revoke.data.reason}
                                                onChange={(e) => revoke.setData('reason', e.target.value)}
                                                rows={2}
                                            />
                                        </Box>
                                        <Button
                                            type="submit"
                                            variant="destructive"
                                            disabled={revoke.processing}
                                            className="w-full"
                                        >
                                            Revoke Badge
                                        </Button>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </Box>
                </Box>
            </Box>
        </AppLayout>
    );
}
