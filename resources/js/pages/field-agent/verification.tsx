import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import Divider from '@mui/material/Divider';
import Typography from '@mui/material/Typography';
import {
    CheckCircle,
    Clock,
    IdCard,
    Mail,
    MapPin,
    Phone,
    ShieldCheck,
    User,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/field-agent/dashboard' },
    { title: 'My Verification', href: '/field-agent/verification' },
];

type Props = {
    application: {
        first_name: string;
        last_name: string;
        email: string;
        contact_number: string;
        location: string;
        ghana_card_number: string;
        status: string;
        region?: { name: string };
        city?: { name: string };
        ghana_card_image_url: string;
        ghana_card_back_image_url: string | null;
        selfie_url: string;
        created_at_formatted: string;
        reviewed_at_formatted: string | null;
    } | null;
};

function statusConfig(status: string): {
    label: string;
    variant: 'default' | 'secondary' | 'destructive';
} {
    switch (status) {
        case 'approved':
            return { label: 'Approved', variant: 'default' };
        case 'rejected':
            return { label: 'Rejected', variant: 'destructive' };
        case 'pending':
            return { label: 'Pending Review', variant: 'secondary' };
        default:
            return { label: status, variant: 'secondary' };
    }
}

function InfoRow({
    icon: Icon,
    label,
    value,
}: {
    icon: React.ElementType;
    label: string;
    value?: string | null;
}) {
    return (
        <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5, py: 1.25 }}>
            <Box
                sx={{
                    mt: 0.25,
                    p: 1,
                    borderRadius: 1.5,
                    bgcolor: 'action.hover',
                    display: 'inline-flex',
                }}
            >
                <Icon size={16} style={{ color: 'rgba(0,0,0,0.54)' }} />
            </Box>
            <Box sx={{ minWidth: 0, flex: 1 }}>
                <Typography
                    variant="caption"
                    sx={{
                        display: 'block',
                        fontWeight: 500,
                        letterSpacing: '0.04em',
                        textTransform: 'uppercase',
                        color: 'text.secondary',
                    }}
                >
                    {label}
                </Typography>
                <Typography
                    variant="body2"
                    sx={{ mt: 0.25, fontWeight: 500, color: 'text.primary' }}
                >
                    {value || '—'}
                </Typography>
            </Box>
        </Box>
    );
}

function DocumentCard({
    title,
    url,
    icon: Icon,
}: {
    title: string;
    url: string;
    icon: React.ElementType;
}) {
    return (
        <Card
            variant="outlined"
            sx={{
                overflow: 'hidden',
                borderRadius: 2,
                transition: 'box-shadow 0.3s',
                '&:hover': {
                    boxShadow: '0 10px 20px rgba(0,0,0,0.08)',
                },
            }}
        >
            <Box
                sx={{
                    p: 2,
                    borderBottom: 1,
                    borderColor: 'divider',
                    bgcolor: 'action.hover',
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1,
                }}
            >
                <Icon size={16} style={{ color: 'rgba(0,0,0,0.6)' }} />
                <Typography variant="subtitle2" fontWeight={600}>
                    {title}
                </Typography>
            </Box>
            <Box sx={{ p: 1.5 }}>
                <Box
                    sx={{
                        position: 'relative',
                        overflow: 'hidden',
                        borderRadius: 1,
                        border: 1,
                        borderColor: 'divider',
                        aspectRatio: '4 / 3',
                        bgcolor: 'action.hover',
                        '&:hover .doc-card-overlay': {
                            opacity: 1,
                        },
                        '&:hover .doc-card-image': {
                            transform: 'scale(1.05)',
                        },
                    }}
                >
                    <Box
                        component="img"
                        className="doc-card-image"
                        src={url}
                        alt={title}
                        sx={{
                            width: '100%',
                            height: '100%',
                            objectFit: 'cover',
                            transition: 'transform 500ms',
                        }}
                    />
                    <Box
                        className="doc-card-overlay"
                        sx={{
                            position: 'absolute',
                            inset: 0,
                            bgcolor: 'rgba(0,0,0,0.4)',
                            opacity: 0,
                            transition: 'opacity 200ms',
                            display: 'flex',
                            alignItems: 'center',
                            justifyContent: 'center',
                        }}
                    >
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => window.open(url, '_blank')}
                        >
                            View Original
                        </Button>
                    </Box>
                </Box>
            </Box>
        </Card>
    );
}

export default function Verification({ application }: Props) {
    if (!application) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="My Verification" />
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: 'column',
                        gap: 4,
                        p: { xs: 2, md: 3 },
                        mx: 'auto',
                        maxWidth: '1280px',
                        width: '100%',
                    }}
                >
                    <Box
                        sx={{
                            display: 'flex',
                            flexDirection: 'column',
                            alignItems: 'center',
                            justifyContent: 'center',
                            gap: 2,
                            minHeight: '40vh',
                            p: 6,
                            border: '2px dashed',
                            borderColor: 'divider',
                            borderRadius: 4,
                            textAlign: 'center',
                        }}
                    >
                        <Box
                            sx={{
                                p: 3,
                                borderRadius: '50%',
                                bgcolor: 'primary.light',
                                color: 'primary.main',
                                display: 'inline-flex',
                            }}
                        >
                            <ShieldCheck size={48} />
                        </Box>
                        <Box sx={{ maxWidth: 320 }}>
                            <Typography variant="h6" fontWeight={700} gutterBottom>
                                No verification record
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                Your application details will appear here once
                                available. If you believe this is an error,
                                please contact support.
                            </Typography>
                        </Box>
                    </Box>

                    <SupportFooter />
                </Box>
            </AppLayout>
        );
    }

    const status = statusConfig(application.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Verification" />

            <Box
                sx={{
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 4,
                    p: { xs: 2, md: 3 },
                    mx: 'auto',
                    maxWidth: '1280px',
                    width: '100%',
                }}
            >
                {/* Page Header */}
                <Box
                    sx={{
                        display: 'flex',
                        flexDirection: { xs: 'column', md: 'row' },
                        alignItems: { md: 'center' },
                        justifyContent: 'space-between',
                        gap: 2,
                        pb: 3,
                        borderBottom: 1,
                        borderColor: 'divider',
                    }}
                >
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Box
                            sx={{
                                p: 1.5,
                                borderRadius: 2,
                                bgcolor: 'primary.light',
                                color: 'primary.main',
                                display: 'inline-flex',
                            }}
                        >
                            <ShieldCheck size={32} />
                        </Box>
                        <Box>
                            <Typography
                                variant="h5"
                                fontWeight={700}
                                sx={{ letterSpacing: '-0.01em' }}
                            >
                                Verification Status
                            </Typography>
                            <Box
                                sx={{
                                    display: 'flex',
                                    flexWrap: 'wrap',
                                    columnGap: 2,
                                    rowGap: 0.5,
                                    mt: 0.5,
                                    color: 'text.secondary',
                                }}
                            >
                                <Box
                                    sx={{
                                        display: 'inline-flex',
                                        alignItems: 'center',
                                        gap: 0.5,
                                    }}
                                >
                                    <Clock size={14} />
                                    <Typography variant="body2">
                                        Submitted: {application.created_at_formatted}
                                    </Typography>
                                </Box>
                                {application.reviewed_at_formatted && (
                                    <Box
                                        sx={{
                                            display: 'inline-flex',
                                            alignItems: 'center',
                                            gap: 0.5,
                                        }}
                                    >
                                        <CheckCircle
                                            size={14}
                                            style={{ color: '#10b981' }}
                                        />
                                        <Typography variant="body2">
                                            Reviewed:{' '}
                                            {application.reviewed_at_formatted}
                                        </Typography>
                                    </Box>
                                )}
                            </Box>
                        </Box>
                    </Box>
                    <Box
                        sx={{
                            display: 'flex',
                            alignItems: { xs: 'flex-start', md: 'flex-end' },
                        }}
                    >
                        <Badge variant={status.variant}>{status.label}</Badge>
                    </Box>
                </Box>

                {/* Personal Details Card */}
                <Card
                    variant="outlined"
                    sx={{ borderRadius: 2, overflow: 'hidden' }}
                >
                    <Box
                        sx={{
                            px: 3,
                            py: 2,
                            borderBottom: 1,
                            borderColor: 'divider',
                            bgcolor: 'action.hover',
                            display: 'flex',
                            alignItems: 'center',
                            gap: 1,
                        }}
                    >
                        <User size={20} style={{ color: '#6366f1' }} />
                        <Typography variant="h6" fontWeight={600}>
                            Personal & Location Information
                        </Typography>
                    </Box>
                    <Box
                        sx={{
                            display: 'grid',
                            gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
                        }}
                    >
                        {/* Identity Column */}
                        <Box
                            sx={{
                                p: 3,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 1,
                                borderBottom: {
                                    xs: 1,
                                    md: 0,
                                },
                                borderRight: {
                                    xs: 0,
                                    md: 1,
                                },
                                borderColor: 'divider',
                            }}
                        >
                            <Typography
                                variant="overline"
                                sx={{
                                    fontWeight: 600,
                                    color: 'text.secondary',
                                    letterSpacing: '0.08em',
                                }}
                            >
                                Identity Details
                            </Typography>
                            <InfoRow
                                icon={User}
                                label="Full Name"
                                value={`${application.first_name} ${application.last_name}`}
                            />
                            <InfoRow
                                icon={Mail}
                                label="Email Address"
                                value={application.email}
                            />
                            <InfoRow
                                icon={Phone}
                                label="Contact Number"
                                value={application.contact_number}
                            />
                            <InfoRow
                                icon={IdCard}
                                label="Ghana Card No."
                                value={application.ghana_card_number}
                            />
                        </Box>

                        {/* Location Column */}
                        <Box
                            sx={{
                                p: 3,
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 1,
                            }}
                        >
                            <Typography
                                variant="overline"
                                sx={{
                                    fontWeight: 600,
                                    color: 'text.secondary',
                                    letterSpacing: '0.08em',
                                }}
                            >
                                Location Details
                            </Typography>
                            <InfoRow
                                icon={MapPin}
                                label="Region"
                                value={application.region?.name}
                            />
                            <InfoRow
                                icon={MapPin}
                                label="City"
                                value={application.city?.name}
                            />
                            <InfoRow
                                icon={MapPin}
                                label="Street / House No."
                                value={application.location}
                            />
                            <Box sx={{ pt: 1 }}>
                                <Box
                                    sx={{
                                        p: 1.5,
                                        borderRadius: 1.5,
                                        bgcolor: 'action.hover',
                                    }}
                                >
                                    <Typography
                                        variant="caption"
                                        sx={{
                                            display: 'block',
                                            fontWeight: 500,
                                            color: 'text.secondary',
                                            textTransform: 'uppercase',
                                            letterSpacing: '0.05em',
                                            mb: 0.5,
                                        }}
                                    >
                                        Current Status
                                    </Typography>
                                    <Typography
                                        variant="body2"
                                        fontWeight={700}
                                        sx={{ textTransform: 'capitalize' }}
                                    >
                                        {application.status.replace('_', ' ')}
                                    </Typography>
                                </Box>
                            </Box>
                        </Box>
                    </Box>
                </Card>

                {/* Document Cards */}
                <Box
                    sx={{
                        display: 'grid',
                        gap: 3,
                        gridTemplateColumns: {
                            xs: '1fr',
                            sm: 'repeat(2, 1fr)',
                            lg: 'repeat(3, 1fr)',
                        },
                    }}
                >
                    <DocumentCard
                        title="Ghana Card — Front"
                        url={application.ghana_card_image_url}
                        icon={IdCard}
                    />

                    {application.ghana_card_back_image_url && (
                        <DocumentCard
                            title="Ghana Card — Back"
                            url={application.ghana_card_back_image_url}
                            icon={IdCard}
                        />
                    )}

                    <DocumentCard
                        title="Selfie"
                        url={application.selfie_url}
                        icon={User}
                    />
                </Box>

                <SupportFooter />
            </Box>
        </AppLayout>
    );
}

function SupportFooter() {
    return (
        <Box
            sx={{
                p: 3,
                borderRadius: 2,
                border: 1,
                borderColor: 'primary.light',
                bgcolor: 'primary.lighter',
                display: 'flex',
                flexDirection: { xs: 'column', md: 'row' },
                alignItems: { xs: 'stretch', md: 'center' },
                justifyContent: 'space-between',
                gap: 2,
            }}
        >
            <Box
                sx={{
                    display: 'flex',
                    flexDirection: { xs: 'column', md: 'row' },
                    alignItems: 'center',
                    gap: 2,
                    textAlign: { xs: 'center', md: 'left' },
                }}
            >
                <Box
                    sx={{
                        p: 1,
                        borderRadius: '50%',
                        bgcolor: 'primary.light',
                        color: 'primary.main',
                        display: 'inline-flex',
                    }}
                >
                    <ShieldCheck size={20} />
                </Box>
                <Box>
                    <Typography variant="subtitle1" fontWeight={600}>
                        Security & Privacy
                    </Typography>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ maxWidth: 480 }}
                    >
                        Your verification data is encrypted and handled securely
                        according to our privacy policy.
                    </Typography>
                </Box>
            </Box>
            <Box sx={{ display: 'flex', justifyContent: { xs: 'center', md: 'flex-end' } }}>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        (window.location.href = 'mailto:support@surprisemoi.com')
                    }
                >
                    Contact Support
                </Button>
            </Box>
        </Box>
    );
}
