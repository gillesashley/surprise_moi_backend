import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ArrowRight, CalendarClock, FileClock, Store } from 'lucide-react';

interface VendorRow {
    id: number;
    business_name: string | null;
    name: string;
    field_verified_until: string | null;
}

interface DraftRow {
    id: string;
    started_at: string;
    vendor: { id: number; business_name: string | null; name: string };
}

interface Props {
    needsVisit: VendorRow[];
    expiringSoon: VendorRow[];
    drafts: DraftRow[];
}

export default function VisitsIndex({ needsVisit, expiringSoon, drafts }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/field-agent/dashboard' },
                { title: 'Visits', href: '/field-agent/visits' },
            ]}
        >
            <Head title="Vendor visits" />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, p: 3 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        Vendor visits
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        Verify vendors in the field, resume drafts, and catch expiring badges.
                    </Typography>
                </Box>

                <Section
                    icon={<Store size={18} />}
                    title="Needs visit now"
                    count={needsVisit.length}
                    emptyText="Nothing pending — great job."
                >
                    {needsVisit.map((v) => (
                        <VendorRowItem key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section
                    icon={<CalendarClock size={18} />}
                    title="Expiring soon"
                    count={expiringSoon.length}
                    emptyText="No badges expiring in the next 30 days."
                >
                    {expiringSoon.map((v) => (
                        <VendorRowItem key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section
                    icon={<FileClock size={18} />}
                    title="Resume drafts"
                    count={drafts.length}
                    emptyText="No draft visits."
                >
                    {drafts.map((d) => (
                        <RowLink
                            key={d.id}
                            href={`/field-agent/visits/forms/${d.id}`}
                            primary={d.vendor.business_name ?? d.vendor.name}
                            secondary={`Started ${new Date(d.started_at).toLocaleString()}`}
                        />
                    ))}
                </Section>
            </Box>
        </AppLayout>
    );
}

function Section({
    icon,
    title,
    count,
    emptyText,
    children,
}: {
    icon: React.ReactNode;
    title: string;
    count: number;
    emptyText: string;
    children: React.ReactNode;
}) {
    const isEmpty = count === 0;
    return (
        <Card>
            <CardHeader>
                <CardTitle>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                        {icon}
                        <span>{title}</span>
                    </Box>
                </CardTitle>
                <CardDescription>
                    {count} {count === 1 ? 'vendor' : 'vendors'}
                </CardDescription>
            </CardHeader>
            <CardContent>
                {isEmpty ? (
                    <Typography variant="body2" color="text.secondary">
                        {emptyText}
                    </Typography>
                ) : (
                    <Stack spacing={1.5}>{children}</Stack>
                )}
            </CardContent>
        </Card>
    );
}

function VendorRowItem({ vendor, badgeExpires }: { vendor: VendorRow; badgeExpires: string | null }) {
    const label = vendor.business_name ?? vendor.name;
    const secondary = badgeExpires
        ? `Expires ${new Date(badgeExpires).toLocaleDateString()}`
        : 'Never verified';
    return <RowLink href={`/field-agent/visits/${vendor.id}`} primary={label} secondary={secondary} />;
}

function RowLink({ href, primary, secondary }: { href: string; primary: string; secondary: string }) {
    return (
        <Box
            component={Link}
            href={href}
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 2,
                p: 2,
                borderRadius: 1,
                border: '1px solid',
                borderColor: 'divider',
                textDecoration: 'none',
                color: 'inherit',
                transition: 'background-color 120ms ease, border-color 120ms ease',
                '&:hover': {
                    backgroundColor: 'action.hover',
                    borderColor: 'text.secondary',
                },
            }}
        >
            <Box sx={{ minWidth: 0 }}>
                <Typography variant="body1" fontWeight={600} noWrap>
                    {primary}
                </Typography>
                <Typography variant="body2" color="text.secondary" noWrap>
                    {secondary}
                </Typography>
            </Box>
            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, color: 'primary.main', flexShrink: 0 }}>
                <Typography variant="body2" fontWeight={500}>
                    Open
                </Typography>
                <ArrowRight size={16} />
            </Box>
        </Box>
    );
}
