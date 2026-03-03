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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import {
    ArrowLeft,
    Briefcase,
    CheckCircle,
    CreditCard,
    Download,
    Eye,
    IdCard,
    Package,
    User as UserIcon,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

function isImageUrl(url: string): boolean {
    return /\.(jpe?g|png|gif|webp)(\?|$)/i.test(url);
}

interface BespokeService {
    id: number;
    name: string;
    description: string | null;
    image: string | null;
}

interface Application {
    id: number;
    status: string;
    current_step: number;
    completed_step: number;
    is_registered_vendor: boolean;
    user: {
        id: number;
        name: string;
        email: string;
        phone: string | null;
        role: string;
    };
    ghana_card_front: string | null;
    ghana_card_back: string | null;
    has_business_certificate: boolean;
    has_tin: boolean;
    business_certificate_document: string | null;
    tin_document: string | null;
    selfie_image: string | null;
    proof_of_business: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;
    facebook_handle: string | null;
    instagram_handle: string | null;
    twitter_handle: string | null;
    bespoke_services: BespokeService[];
    submitted_at: string | null;
    reviewed_at: string | null;
    reviewed_by: {
        id: number;
        name: string;
    } | null;
    rejection_reason: string | null;
    payment_required: boolean;
    payment_completed: boolean;
    payment_completed_at: string | null;
    onboarding_fee: number | null;
    discount_amount: number | null;
    final_amount: number | null;
    payment: {
        status: string;
        amount: number;
        currency: string;
        channel: string | null;
        reference: string;
        card_last4: string | null;
        card_bank: string | null;
        mobile_money_number: string | null;
        mobile_money_provider: string | null;
        paid_at: string | null;
        failure_reason: string | null;
    } | null;
    can_be_reviewed: boolean;
}

interface Props {
    application: Application;
}

const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: any; label: string }> = {
        pending: { variant: 'secondary', label: 'Pending Review' },
        under_review: { variant: 'default', label: 'Under Review' },
        approved: { variant: 'default', label: 'Approved' },
        rejected: { variant: 'destructive', label: 'Rejected' },
    };

    const config = variants[status] || variants.pending;

    return <Badge variant={config.variant}>{config.label}</Badge>;
};

export default function VendorApplicationShow({ application }: Props) {
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const [previewDoc, setPreviewDoc] = useState<{ url: string; title: string } | null>(null);
    const { data, setData, post, processing } = useForm({
        rejection_reason: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Vendor Applications',
            href: '/dashboard/vendor-applications',
        },
        {
            title: application.user.name,
            href: `/dashboard/vendor-applications/${application.id}`,
        },
    ];

    const handleApprove = () => {
        if (
            confirm('Are you sure you want to approve this vendor application?')
        ) {
            router.post(
                `/dashboard/vendor-applications/${application.id}/approve`,
            );
        }
    };

    const handleReject = () => {
        if (data.rejection_reason.trim().length < 10) {
            alert(
                'Please provide a detailed rejection reason (at least 10 characters).',
            );
            return;
        }
        post(`/dashboard/vendor-applications/${application.id}/reject`, {
            onSuccess: () => setShowRejectDialog(false),
        });
    };

    const handleMarkUnderReview = () => {
        router.post(
            `/dashboard/vendor-applications/${application.id}/under-review`,
        );
    };

    const canApproveOrReject = application.can_be_reviewed
        && ['pending', 'under_review'].includes(application.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Vendor Application - ${application.user.name}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, overflow: 'auto', p: 2 }}>
                {/* Header with actions */}
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/dashboard/vendor-applications">
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Applications
                        </Link>
                    </Button>
                    {canApproveOrReject && (
                        <Box sx={{ display: 'flex', gap: 1 }}>
                            {application.status === 'pending' && (
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={handleMarkUnderReview}
                                >
                                    Mark Under Review
                                </Button>
                            )}
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => setShowRejectDialog(true)}
                            >
                                <XCircle style={{ marginRight: 8, width: 16, height: 16 }} />
                                Reject
                            </Button>
                            <Button
                                variant="default"
                                size="sm"
                                onClick={handleApprove}
                            >
                                <CheckCircle style={{ marginRight: 8, width: 16, height: 16 }} />
                                Approve
                            </Button>
                        </Box>
                    )}
                    {!application.can_be_reviewed && ['pending', 'under_review'].includes(application.status) && (
                        <Box sx={{ bgcolor: 'warning.light', color: 'warning.dark', borderRadius: 2, p: 1.5, fontSize: '0.875rem' }}>
                            <Typography sx={{ fontWeight: 600, fontSize: '0.875rem', mb: 0.5 }}>
                                Application Not Ready for Review
                            </Typography>
                            <Box component="ul" sx={{ m: 0, pl: 2 }}>
                                {application.completed_step < 4 && (
                                    <li>Steps incomplete: {application.completed_step}/4 completed</li>
                                )}
                                {application.payment_required && !application.payment_completed && (
                                    <li>Payment not completed</li>
                                )}
                                {!application.submitted_at && (
                                    <li>Application not submitted</li>
                                )}
                            </Box>
                        </Box>
                    )}
                </Box>

                {/* Application Summary */}
                <Card>
                    <CardHeader>
                        <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                            <Box>
                                <CardTitle>{application.user.name}</CardTitle>
                                <CardDescription>
                                    {application.user.email}
                                    {application.user.phone && (
                                        <> • {application.user.phone}</>
                                    )}
                                </CardDescription>
                            </Box>
                            <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 1 }}>
                                {getStatusBadge(application.status)}
                                <Badge variant="outline">
                                    {application.is_registered_vendor
                                        ? 'Registered Business'
                                        : 'Individual Vendor'}
                                </Badge>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                            <Box>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Current Role
                                </Typography>
                                <Typography sx={{ fontWeight: 500, textTransform: 'capitalize' }}>
                                    {application.user.role}
                                </Typography>
                            </Box>
                            <Box>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Progress
                                </Typography>
                                <Typography sx={{ fontWeight: 500 }}>
                                    Step {application.completed_step} of 4
                                    completed
                                </Typography>
                            </Box>
                            <Box>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Submitted
                                </Typography>
                                <Typography sx={{ fontWeight: 500 }}>
                                    {application.submitted_at
                                        ? new Date(
                                              application.submitted_at,
                                          ).toLocaleDateString()
                                        : 'Not submitted'}
                                </Typography>
                            </Box>
                        </Box>
                        {application.reviewed_at && (
                            <Box sx={{ mt: 2, borderRadius: 2, bgcolor: 'action.hover', p: 1.5 }}>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Reviewed by {application.reviewed_by?.name}{' '}
                                    on{' '}
                                    {new Date(
                                        application.reviewed_at,
                                    ).toLocaleDateString()}
                                </Typography>
                            </Box>
                        )}
                        {application.rejection_reason && (
                            <Box sx={{ mt: 2, borderRadius: 2, bgcolor: 'error.light', opacity: 0.1, p: 1.5 }}>
                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'error.main' }}>
                                    Rejection Reason:
                                </Typography>
                                <Typography sx={{ mt: 0.5, fontSize: '0.875rem', color: 'error.main', opacity: 0.8 }}>
                                    {application.rejection_reason}
                                </Typography>
                            </Box>
                        )}
                    </CardContent>
                </Card>

                {/* Payment Information */}
                <Card>
                    <CardHeader>
                        <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                            <CreditCard style={{ width: 20, height: 20 }} />
                            Payment Information
                        </CardTitle>
                        <CardDescription>
                            Onboarding fee payment status
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!application.payment_required ? (
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Chip label="Not Required" color="info" size="small" variant="outlined" />
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    Payment is not required for this application
                                </Typography>
                            </Box>
                        ) : application.payment ? (
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Status
                                        </Typography>
                                        <Box sx={{ mt: 0.5 }}>
                                            <Chip
                                                label={application.payment.status.charAt(0).toUpperCase() + application.payment.status.slice(1)}
                                                color={
                                                    application.payment.status === 'success' ? 'success'
                                                    : application.payment.status === 'failed' ? 'error'
                                                    : application.payment.status === 'pending' ? 'warning'
                                                    : 'default'
                                                }
                                                size="small"
                                            />
                                        </Box>
                                    </Box>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Amount
                                        </Typography>
                                        <Typography sx={{ fontWeight: 500 }}>
                                            {application.payment.currency} {Number(application.payment.amount).toFixed(2)}
                                        </Typography>
                                    </Box>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Date Paid
                                        </Typography>
                                        <Typography sx={{ fontWeight: 500 }}>
                                            {application.payment.paid_at
                                                ? new Date(application.payment.paid_at).toLocaleDateString()
                                                : 'Not yet paid'}
                                        </Typography>
                                    </Box>
                                </Box>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Payment Method
                                        </Typography>
                                        <Typography sx={{ fontWeight: 500, textTransform: 'capitalize' }}>
                                            {application.payment.channel
                                                ? application.payment.channel.replace('_', ' ')
                                                : 'N/A'}
                                        </Typography>
                                    </Box>
                                    {application.payment.card_last4 && (
                                        <Box>
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                Card
                                            </Typography>
                                            <Typography sx={{ fontWeight: 500 }}>
                                                **** {application.payment.card_last4}
                                                {application.payment.card_bank && ` (${application.payment.card_bank})`}
                                            </Typography>
                                        </Box>
                                    )}
                                    {application.payment.mobile_money_number && (
                                        <Box>
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                Mobile Money
                                            </Typography>
                                            <Typography sx={{ fontWeight: 500 }}>
                                                {application.payment.mobile_money_number}
                                                {application.payment.mobile_money_provider && (
                                                    <Badge variant="outline" style={{ marginLeft: 8 }}>
                                                        {application.payment.mobile_money_provider.toUpperCase()}
                                                    </Badge>
                                                )}
                                            </Typography>
                                        </Box>
                                    )}
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Reference
                                        </Typography>
                                        <Typography sx={{ fontWeight: 500, fontFamily: 'monospace', fontSize: '0.8rem' }}>
                                            {application.payment.reference}
                                        </Typography>
                                    </Box>
                                </Box>
                                {application.payment.failure_reason && (
                                    <Box sx={{ borderRadius: 2, bgcolor: 'error.light', opacity: 0.1, p: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            Failure Reason: {application.payment.failure_reason}
                                        </Typography>
                                    </Box>
                                )}
                                {application.discount_amount && Number(application.discount_amount) > 0 && (
                                    <Box sx={{ borderRadius: 2, bgcolor: 'action.hover', p: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                            Original Fee: {application.payment.currency} {Number(application.onboarding_fee).toFixed(2)}
                                            {' · '}Discount: -{application.payment.currency} {Number(application.discount_amount).toFixed(2)}
                                            {' · '}Final: {application.payment.currency} {Number(application.final_amount).toFixed(2)}
                                        </Typography>
                                    </Box>
                                )}
                            </Box>
                        ) : (
                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                <Chip label="Unpaid" color="warning" size="small" />
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    No payment has been made yet
                                </Typography>
                            </Box>
                        )}
                    </CardContent>
                </Card>

                {/* Ghana Card Documents */}
                {(application.ghana_card_front ||
                    application.ghana_card_back) && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <IdCard style={{ width: 20, height: 20 }} />
                                Ghana Card
                            </CardTitle>
                            <CardDescription>
                                National identification documents
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                {application.ghana_card_front && (
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Front of Ghana Card
                                        </Typography>
                                        <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                            <Box
                                                component="img"
                                                src={
                                                    application.ghana_card_front
                                                }
                                                alt="Ghana Card Front"
                                                sx={{ height: 'auto', width: '100%', objectFit: 'cover' }}
                                            />
                                        </Box>
                                    </Box>
                                )}
                                {application.ghana_card_back && (
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Back of Ghana Card
                                        </Typography>
                                        <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                            <Box
                                                component="img"
                                                src={
                                                    application.ghana_card_back
                                                }
                                                alt="Ghana Card Back"
                                                sx={{ height: 'auto', width: '100%', objectFit: 'cover' }}
                                            />
                                        </Box>
                                    </Box>
                                )}
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Business Documents (Registered Vendors) */}
                {application.is_registered_vendor &&
                    (application.business_certificate_document ||
                        application.tin_document) && (
                        <Card>
                            <CardHeader>
                                <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                    <Briefcase style={{ width: 20, height: 20 }} />
                                    Business Documents
                                </CardTitle>
                                <CardDescription>
                                    Business registration and tax documents
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    {application.business_certificate_document && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Business Certificate
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: application.business_certificate_document!, title: 'Business Certificate' })}
                                                    sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.875rem', color: 'primary.main', bgcolor: 'transparent', border: 'none', cursor: 'pointer', p: 0, '&:hover': { textDecoration: 'underline' } }}
                                                >
                                                    <Eye style={{ width: 16, height: 16 }} />
                                                    View Business Certificate
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}
                                    {application.tin_document && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                TIN Document
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: application.tin_document!, title: 'TIN Document' })}
                                                    sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.875rem', color: 'primary.main', bgcolor: 'transparent', border: 'none', cursor: 'pointer', p: 0, '&:hover': { textDecoration: 'underline' } }}
                                                >
                                                    <Eye style={{ width: 16, height: 16 }} />
                                                    View TIN Document
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    )}

                {/* Unregistered Vendor Verification */}
                {!application.is_registered_vendor &&
                    (application.selfie_image ||
                        application.proof_of_business ||
                        application.mobile_money_number) && (
                        <Card>
                            <CardHeader>
                                <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                    <UserIcon style={{ width: 20, height: 20 }} />
                                    Individual Vendor Verification
                                </CardTitle>
                                <CardDescription>
                                    Verification documents and payment details
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {application.selfie_image && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Selfie Verification
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                                <Box
                                                    component="img"
                                                    src={
                                                        application.selfie_image
                                                    }
                                                    alt="Vendor Selfie"
                                                    sx={{ height: 'auto', maxWidth: 384, objectFit: 'cover' }}
                                                />
                                            </Box>
                                        </Box>
                                    )}
                                    {application.proof_of_business && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Proof of Business
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: application.proof_of_business!, title: 'Proof of Business' })}
                                                    sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.875rem', color: 'primary.main', bgcolor: 'transparent', border: 'none', cursor: 'pointer', p: 0, '&:hover': { textDecoration: 'underline' } }}
                                                >
                                                    <Eye style={{ width: 16, height: 16 }} />
                                                    View Proof of Business
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}
                                    {application.mobile_money_number && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Mobile Money Details
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                                    <Typography sx={{ fontSize: '0.875rem' }}>
                                                        <Box component="span" sx={{ fontWeight: 500 }}>
                                                            Number:
                                                        </Box>{' '}
                                                        {
                                                            application.mobile_money_number
                                                        }
                                                    </Typography>
                                                    {application.mobile_money_provider && (
                                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                                            <Box component="span" sx={{ fontWeight: 500 }}>
                                                                Provider:
                                                            </Box>{' '}
                                                            <Badge variant="outline">
                                                                {application.mobile_money_provider.toUpperCase()}
                                                            </Badge>
                                                        </Typography>
                                                    )}
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    )}

                {/* Social Media */}
                {(application.facebook_handle ||
                    application.instagram_handle ||
                    application.twitter_handle) && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <Users style={{ width: 20, height: 20 }} />
                                Social Media
                            </CardTitle>
                            <CardDescription>
                                Business social media profiles
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                                {application.facebook_handle && (
                                    <Box sx={{ borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                            Facebook
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            {application.facebook_handle}
                                        </Typography>
                                    </Box>
                                )}
                                {application.instagram_handle && (
                                    <Box sx={{ borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                            Instagram
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            {application.instagram_handle}
                                        </Typography>
                                    </Box>
                                )}
                                {application.twitter_handle && (
                                    <Box sx={{ borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                            Twitter/X
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            {application.twitter_handle}
                                        </Typography>
                                    </Box>
                                )}
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Bespoke Services */}
                {application.bespoke_services.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <Package style={{ width: 20, height: 20 }} />
                                Selected Bespoke Services
                            </CardTitle>
                            <CardDescription>
                                Services this vendor plans to offer
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'grid', gap: 1, gridTemplateColumns: { md: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' } }}>
                                {application.bespoke_services.map((service) => (
                                    <Box
                                        key={service.id}
                                        sx={{ borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}
                                    >
                                        <Typography sx={{ fontWeight: 500 }}>
                                            {service.name}
                                        </Typography>
                                        {service.description && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {service.description}
                                            </Typography>
                                        )}
                                    </Box>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                )}
            </Box>

            {/* Document Preview Dialog */}
            <Dialog open={!!previewDoc} onOpenChange={(open) => { if (!open) { setPreviewDoc(null); } }}>
                <DialogContent style={{ maxWidth: 900, width: '90vw' }}>
                    <DialogHeader>
                        <DialogTitle>{previewDoc?.title}</DialogTitle>
                        <DialogDescription>
                            Preview the uploaded document below
                        </DialogDescription>
                    </DialogHeader>
                    {previewDoc && (
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                            <Box sx={{ overflow: 'auto', borderRadius: 2, border: 1, borderColor: 'divider', bgcolor: 'action.hover', display: 'flex', alignItems: 'center', justifyContent: 'center', minHeight: 300, maxHeight: '60vh' }}>
                                {isImageUrl(previewDoc.url) ? (
                                    <Box
                                        component="img"
                                        src={previewDoc.url}
                                        alt={previewDoc.title}
                                        sx={{ maxWidth: '100%', maxHeight: '60vh', objectFit: 'contain' }}
                                    />
                                ) : (
                                    <Box
                                        component="iframe"
                                        src={previewDoc.url}
                                        title={previewDoc.title}
                                        sx={{ width: '100%', height: '60vh', border: 'none' }}
                                    />
                                )}
                            </Box>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setPreviewDoc(null)}>
                                    Close
                                </Button>
                                <Button asChild>
                                    <a href={previewDoc.url} download target="_blank" rel="noopener noreferrer">
                                        <Download style={{ marginRight: 8, width: 16, height: 16 }} />
                                        Download
                                    </a>
                                </Button>
                            </DialogFooter>
                        </Box>
                    )}
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <Dialog open={showRejectDialog} onOpenChange={setShowRejectDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Vendor Application</DialogTitle>
                        <DialogDescription>
                            Please provide a detailed reason for rejecting this
                            application. The applicant will be able to see this
                            message.
                        </DialogDescription>
                    </DialogHeader>
                    <Box sx={{ py: 2 }}>
                        <Textarea
                            placeholder="Explain why this application is being rejected..."
                            value={data.rejection_reason}
                            onChange={(e) =>
                                setData('rejection_reason', e.target.value)
                            }
                            rows={5}
                            style={{ resize: 'none' }}
                        />
                        {data.rejection_reason &&
                            data.rejection_reason.length < 10 && (
                                <Typography sx={{ mt: 1, fontSize: '0.875rem', color: 'error.main' }}>
                                    Please provide at least 10 characters
                                </Typography>
                            )}
                    </Box>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowRejectDialog(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleReject}
                            disabled={
                                processing || data.rejection_reason.length < 10
                            }
                        >
                            Reject Application
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
