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
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { ArrowRight, ClipboardList, UserPlus } from 'lucide-react';

interface VendorApplication {
    id: number;
    status: string;
    submitted_at: string | null;
    user: {
        id: number;
        business_name: string | null;
        name: string;
        email: string;
    };
    vendor_visit: {
        id: string;
        status: string;
    } | null;
}

interface Props {
    applications: VendorApplication[];
}

export default function VisitsIndex({ applications }: Props) {
    const pendingAction = applications.filter(
        (a) => a.vendor_visit?.status !== 'submitted',
    );
    const submitted = applications.filter(
        (a) => a.vendor_visit?.status === 'submitted',
    );

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/field-agent/dashboard' },
                { title: 'Vendor Onboarding', href: '/field-agent/visits' },
            ]}
        >
            <Head title="Vendor Onboarding" />

            <Box
                sx={{
                    display: 'flex',
                    flex: 1,
                    flexDirection: 'column',
                    gap: 3,
                    p: 3,
                }}
            >
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        Vendor Onboarding
                    </Typography>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ mt: 0.5 }}
                    >
                        Manage new vendors who registered using your referral
                        code. Complete their questionnaires to get them
                        approved.
                    </Typography>
                </Box>

                <Section
                    icon={<UserPlus size={18} />}
                    title="Needs Questionnaire"
                    count={pendingAction.length}
                    emptyText="No pending vendors."
                >
                    {pendingAction.map((app) => (
                        <VendorApplicationRow key={app.id} application={app} />
                    ))}
                </Section>

                <Section
                    icon={<ClipboardList size={18} />}
                    title="Submitted Questionnaires"
                    count={submitted.length}
                    emptyText="No submitted questionnaires."
                >
                    {submitted.map((app) => (
                        <VendorApplicationRow key={app.id} application={app} />
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

function VendorApplicationRow({
    application,
}: {
    application: VendorApplication;
}) {
    const label = application.user.business_name ?? application.user.name;
    const isSubmitted = application.vendor_visit?.status === 'submitted';
    const isDraft = application.vendor_visit?.status === 'draft';

    let secondary = 'New registration';
    if (isSubmitted) secondary = 'Questionnaire submitted';
    else if (isDraft) secondary = 'Draft questionnaire started';

    const handleAction = () => {
        if (application.vendor_visit) {
            router.get(
                `/field-agent/visits/forms/${application.vendor_visit.id}`,
            );
        } else {
            router.post(`/field-agent/visits/${application.id}/start`);
        }
    };

    return (
        <Box
            sx={{
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                gap: 2,
                p: 2,
                borderRadius: 1,
                border: '1px solid',
                borderColor: 'divider',
                transition:
                    'background-color 120ms ease, border-color 120ms ease',
                '&:hover': {
                    backgroundColor: 'action.hover',
                    borderColor: 'text.secondary',
                },
            }}
        >
            <Box sx={{ minWidth: 0 }}>
                <Typography variant="body1" fontWeight={600} noWrap>
                    {label}
                </Typography>
                <Typography variant="body2" color="text.secondary" noWrap>
                    {secondary}
                </Typography>
            </Box>
            <Box
                sx={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 1,
                    flexShrink: 0,
                }}
            >
                <Button
                    variant="outlined"
                    size="small"
                    onClick={handleAction}
                    endIcon={!isSubmitted && <ArrowRight size={16} />}
                >
                    {isSubmitted
                        ? 'View'
                        : isDraft
                          ? 'Resume'
                          : 'Start Questionnaire'}
                </Button>
            </Box>
        </Box>
    );
}
