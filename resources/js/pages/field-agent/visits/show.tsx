import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { MapPin } from 'lucide-react';
import { useState } from 'react';

interface Vendor {
    id: number;
    business_name: string | null;
    name: string;
    email: string;
    phone: string | null;
}
interface VendorApplication {
    id: number;
    has_business_certificate: boolean;
    tin_number: string | null;
    ghana_card_front: string | null;
    ghana_card_back: string | null;
    selfie_image: string | null;
    business_certificate_document: string | null;
    proof_of_business: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;
    facebook_handle: string | null;
    instagram_handle: string | null;
    twitter_handle: string | null;
}
interface RecentVisit {
    id: string;
    started_at: string;
    status: string;
    badge_expires_at: string | null;
}
interface Props {
    vendor: Vendor;
    application: VendorApplication | null;
    recentVisits: RecentVisit[];
}

export default function VisitShow({ vendor, application, recentVisits }: Props) {
    const [starting, setStarting] = useState(false);

    const startVisit = () => {
        if (!navigator.geolocation) {
            alert('Your browser does not support location. A visit requires GPS.');
            return;
        }
        setStarting(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.post(
                    `/field-agent/visits/${vendor.id}/start`,
                    {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                    },
                    { onFinish: () => setStarting(false) },
                );
            },
            () => {
                setStarting(false);
                alert(
                    'Location is required to verify a visit happened in person. Please enable location and try again.',
                );
            },
            { enableHighAccuracy: true, timeout: 15_000 },
        );
    };

    const label = vendor.business_name ?? vendor.name;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/field-agent/dashboard' },
                { title: 'Visits', href: '/field-agent/visits' },
                { title: label, href: `/field-agent/visits/${vendor.id}` },
            ]}
        >
            <Head title={`Visit — ${label}`} />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        {label}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        {[vendor.phone, vendor.email].filter(Boolean).join(' · ')}
                    </Typography>
                </Box>

                <Box>
                    <Button
                        type="button"
                        onClick={startVisit}
                        disabled={starting}
                        size="lg"
                        sx={{ gap: 1, px: 3 }}
                    >
                        <MapPin size={16} />
                        {starting ? 'Getting location…' : 'Start visit'}
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Claim data on file</CardTitle>
                        <CardDescription>
                            What the vendor submitted during onboarding — verify against what you see in person.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ClaimGrid application={application} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent visits</CardTitle>
                        <CardDescription>
                            {recentVisits.length === 0
                                ? 'No prior visits.'
                                : `${recentVisits.length} prior ${recentVisits.length === 1 ? 'visit' : 'visits'}.`}
                        </CardDescription>
                    </CardHeader>
                    {recentVisits.length > 0 && (
                        <CardContent>
                            <Stack spacing={1}>
                                {recentVisits.map((v) => (
                                    <Box
                                        key={v.id}
                                        sx={{
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                            p: 1.5,
                                            borderRadius: 1,
                                            border: '1px solid',
                                            borderColor: 'divider',
                                        }}
                                    >
                                        <Box>
                                            <Typography variant="body2" fontWeight={500}>
                                                {new Date(v.started_at).toLocaleDateString()}
                                            </Typography>
                                            <Typography variant="caption" color="text.secondary" sx={{ textTransform: 'capitalize' }}>
                                                {v.status}
                                                {v.badge_expires_at && ` · expires ${new Date(v.badge_expires_at).toLocaleDateString()}`}
                                            </Typography>
                                        </Box>
                                    </Box>
                                ))}
                            </Stack>
                        </CardContent>
                    )}
                </Card>
            </Box>
        </AppLayout>
    );
}

function ClaimGrid({ application }: { application: VendorApplication | null }) {
    if (!application) {
        return (
            <Typography variant="body2" color="text.secondary">
                No application on file.
            </Typography>
        );
    }
    return (
        <Box
            sx={{
                display: 'grid',
                gap: 2,
                gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' },
            }}
        >
            <DocImg label="Ghana Card (front)" path={application.ghana_card_front} />
            <DocImg label="Ghana Card (back)" path={application.ghana_card_back} />
            <DocImg label="Selfie" path={application.selfie_image} />
            {application.has_business_certificate && (
                <DocImg label="Business certificate" path={application.business_certificate_document} />
            )}
            {application.tin_number && <DocImg label="Proof of business" path={application.proof_of_business} />}
            <KV
                label="Mobile money"
                value={
                    application.mobile_money_provider
                        ? `${application.mobile_money_provider} — ${application.mobile_money_number}`
                        : '—'
                }
            />
            <KV label="TIN number" value={application.tin_number ?? '—'} />
            <KV label="Facebook" value={application.facebook_handle ?? '—'} />
            <KV label="Instagram" value={application.instagram_handle ?? '—'} />
            <KV label="Twitter/X" value={application.twitter_handle ?? '—'} />
        </Box>
    );
}

function DocImg({ label, path }: { label: string; path: string | null }) {
    if (!path) {
        return <KV label={label} value="—" />;
    }
    const src = path.startsWith('http') ? path : `/storage/${path}`;
    return (
        <Box>
            <Typography variant="caption" color="text.secondary" fontWeight={500} sx={{ display: 'block', mb: 0.5 }}>
                {label}
            </Typography>
            <Box
                component="a"
                href={src}
                target="_blank"
                rel="noreferrer"
                sx={{ display: 'block', borderRadius: 1, overflow: 'hidden', border: '1px solid', borderColor: 'divider', textDecoration: 'none', color: 'inherit' }}
            >
                <Box
                    component="img"
                    src={src}
                    alt={label}
                    sx={{ width: '100%', maxHeight: 192, objectFit: 'contain', display: 'block', bgcolor: 'action.hover' }}
                />
            </Box>
        </Box>
    );
}

function KV({ label, value }: { label: string; value: string }) {
    return (
        <Box>
            <Typography variant="caption" color="text.secondary" fontWeight={500} sx={{ display: 'block' }}>
                {label}
            </Typography>
            <Typography variant="body2">{value}</Typography>
        </Box>
    );
}
