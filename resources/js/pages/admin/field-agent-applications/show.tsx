import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { FormEvent, useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    location: string;
    ghana_card_number: string;
    status: string;
    region?: { name: string };
    city?: { name: string };
    reviewer?: { name: string } | null;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
    ghana_card_image_url: string;
    ghana_card_back_image_url: string | null;
    selfie_url: string;
};

const STATUS_LABEL: Record<string, string> = {
    pending: 'Pending',
    under_review: 'Under review',
    approved: 'Approved',
    rejected: 'Rejected',
};

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const STATUS_VARIANT: Record<string, BadgeVariant> = {
    pending: 'secondary',
    under_review: 'default',
    approved: 'default',
    rejected: 'destructive',
};

export default function FieldAgentApplicationShow({
    application,
}: {
    application: Application;
}) {
    useInactivityLock();
    const [rejectOpen, setRejectOpen] = useState(false);

    const approveForm = useForm({});
    const rejectForm = useForm({ rejection_reason: '' });

    const reviewable =
        application.status === 'pending' ||
        application.status === 'under_review';

    const onApprove = (e: FormEvent) => {
        e.preventDefault();
        approveForm.post(
            `/dashboard/field-agent-applications/${application.id}/approve`,
        );
    };

    const onReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(
            `/dashboard/field-agent-applications/${application.id}/reject`,
            {
                onSuccess: () => setRejectOpen(false),
            },
        );
    };

    return (
        <AppLayout>
            <Head
                title={`Application — ${application.first_name} ${application.last_name}`}
            />
            <Box sx={{ maxWidth: 'lg', p: { xs: 2, md: 4 } }}>
                <Stack spacing={3}>
                    <Stack
                        direction={{ xs: 'column', sm: 'row' }}
                        spacing={2}
                        sx={{
                            justifyContent: 'space-between',
                            alignItems: { sm: 'center' },
                        }}
                    >
                        <Stack
                            spacing={1}
                            direction="row"
                            sx={{ alignItems: 'center', flexWrap: 'wrap' }}
                        >
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                {application.first_name} {application.last_name}
                            </Typography>
                            <Badge
                                variant={
                                    STATUS_VARIANT[application.status] ??
                                    'outline'
                                }
                            >
                                {STATUS_LABEL[application.status] ??
                                    application.status}
                            </Badge>
                        </Stack>
                        {reviewable && (
                            <Stack direction="row" spacing={1}>
                                <form onSubmit={onApprove}>
                                    <Button
                                        type="submit"
                                        disabled={approveForm.processing}
                                    >
                                        Approve
                                    </Button>
                                </form>
                                <Dialog
                                    open={rejectOpen}
                                    onOpenChange={setRejectOpen}
                                >
                                    <DialogTrigger asChild>
                                        <Button variant="destructive">
                                            Reject
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>
                                                Reject application
                                            </DialogTitle>
                                            <DialogDescription>
                                                Please provide a clear reason.
                                                This will be sent to the
                                                applicant.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <Box
                                            component="form"
                                            onSubmit={onReject}
                                        >
                                            <Stack spacing={2}>
                                                <Textarea
                                                    rows={4}
                                                    placeholder="Reason for rejection…"
                                                    value={
                                                        rejectForm.data
                                                            .rejection_reason
                                                    }
                                                    onChange={(e) =>
                                                        rejectForm.setData(
                                                            'rejection_reason',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {rejectForm.errors
                                                    .rejection_reason && (
                                                    <Typography
                                                        variant="caption"
                                                        color="error"
                                                    >
                                                        {
                                                            rejectForm.errors
                                                                .rejection_reason
                                                        }
                                                    </Typography>
                                                )}
                                                <DialogFooter>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() =>
                                                            setRejectOpen(false)
                                                        }
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        type="submit"
                                                        variant="destructive"
                                                        disabled={
                                                            rejectForm.processing
                                                        }
                                                    >
                                                        Confirm rejection
                                                    </Button>
                                                </DialogFooter>
                                            </Stack>
                                        </Box>
                                    </DialogContent>
                                </Dialog>
                            </Stack>
                        )}
                    </Stack>

                    <Box
                        sx={{
                            display: 'grid',
                            gap: 2,
                            gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
                        }}
                    >
                        <Section title="Contact">
                            <Field label="Email" value={application.email} />
                            <Field
                                label="Phone"
                                value={application.contact_number}
                            />
                        </Section>
                        <Section title="Location">
                            <Field
                                label="Region"
                                value={application.region?.name ?? '—'}
                            />
                            <Field
                                label="City"
                                value={application.city?.name ?? '—'}
                            />
                            <Field
                                label="Landmark"
                                value={application.location}
                            />
                        </Section>
                        <Section title="Identity">
                            <Field
                                label="Ghana card number"
                                value={application.ghana_card_number}
                            />
                        </Section>
                        <Section title="Review">
                            <Field
                                label="Status"
                                value={
                                    STATUS_LABEL[application.status] ??
                                    application.status
                                }
                            />
                            {application.reviewer && (
                                <Field
                                    label="Reviewed by"
                                    value={application.reviewer.name}
                                />
                            )}
                            {application.reviewed_at && (
                                <Field
                                    label="Reviewed at"
                                    value={new Date(
                                        application.reviewed_at,
                                    ).toLocaleString()}
                                />
                            )}
                            {application.rejection_reason && (
                                <Field
                                    label="Reason"
                                    value={application.rejection_reason}
                                />
                            )}
                        </Section>
                    </Box>

                    <Box
                        sx={{
                            display: 'grid',
                            gap: 2,
                            gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' },
                        }}
                    >
                        <Figure
                            title="Ghana card — front"
                            url={application.ghana_card_image_url}
                        />
                        {application.ghana_card_back_image_url && (
                            <Figure
                                title="Ghana card — back"
                                url={application.ghana_card_back_image_url}
                            />
                        )}
                        <Figure title="Selfie" url={application.selfie_url} />
                    </Box>
                </Stack>
            </Box>
        </AppLayout>
    );
}

function Section({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <Paper variant="outlined" sx={{ borderRadius: 2, p: 2.5 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 600, mb: 1.5 }}>
                {title}
            </Typography>
            <Stack spacing={1}>{children}</Stack>
        </Paper>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <Box
            sx={{
                display: 'grid',
                gap: 1,
                gridTemplateColumns: { xs: '1fr', sm: '10rem 1fr' },
                alignItems: 'baseline',
            }}
        >
            <Typography variant="body2" color="text.secondary">
                {label}
            </Typography>
            <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                {value || '—'}
            </Typography>
        </Box>
    );
}

function Figure({ title, url }: { title: string; url: string }) {
    return (
        <Paper variant="outlined" sx={{ borderRadius: 2, p: 2.5 }}>
            <Typography variant="subtitle1" sx={{ fontWeight: 600, mb: 1.5 }}>
                {title}
            </Typography>
            <Box
                component="a"
                href={url}
                target="_blank"
                rel="noreferrer"
                sx={{ display: 'block', overflow: 'hidden', borderRadius: 1 }}
            >
                <Box
                    component="img"
                    src={url}
                    alt={title}
                    sx={{
                        display: 'block',
                        width: '100%',
                        maxHeight: 420,
                        objectFit: 'contain',
                        bgcolor: 'action.hover',
                    }}
                />
            </Box>
        </Paper>
    );
}
