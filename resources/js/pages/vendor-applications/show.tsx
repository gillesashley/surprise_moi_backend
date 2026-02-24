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
import {
    ArrowLeft,
    Briefcase,
    CheckCircle,
    IdCard,
    Package,
    User as UserIcon,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

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

    const canApproveOrReject = ['pending', 'under_review'].includes(
        application.status,
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Vendor Application - ${application.user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-auto p-4">
                {/* Header with actions */}
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/dashboard/vendor-applications">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Applications
                        </Link>
                    </Button>
                    {canApproveOrReject && (
                        <div className="flex gap-2">
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
                                <XCircle className="mr-2 size-4" />
                                Reject
                            </Button>
                            <Button
                                variant="default"
                                size="sm"
                                onClick={handleApprove}
                            >
                                <CheckCircle className="mr-2 size-4" />
                                Approve
                            </Button>
                        </div>
                    )}
                </div>

                {/* Application Summary */}
                <Card>
                    <CardHeader>
                        <div className="flex items-start justify-between">
                            <div>
                                <CardTitle>{application.user.name}</CardTitle>
                                <CardDescription>
                                    {application.user.email}
                                    {application.user.phone && (
                                        <> • {application.user.phone}</>
                                    )}
                                </CardDescription>
                            </div>
                            <div className="flex flex-col items-end gap-2">
                                {getStatusBadge(application.status)}
                                <Badge variant="outline">
                                    {application.is_registered_vendor
                                        ? 'Registered Business'
                                        : 'Individual Vendor'}
                                </Badge>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Current Role
                                </p>
                                <p className="font-medium capitalize">
                                    {application.user.role}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Progress
                                </p>
                                <p className="font-medium">
                                    Step {application.completed_step} of 4
                                    completed
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">
                                    Submitted
                                </p>
                                <p className="font-medium">
                                    {application.submitted_at
                                        ? new Date(
                                              application.submitted_at,
                                          ).toLocaleDateString()
                                        : 'Not submitted'}
                                </p>
                            </div>
                        </div>
                        {application.reviewed_at && (
                            <div className="mt-4 rounded-lg bg-muted p-3">
                                <p className="text-sm text-muted-foreground">
                                    Reviewed by {application.reviewed_by?.name}{' '}
                                    on{' '}
                                    {new Date(
                                        application.reviewed_at,
                                    ).toLocaleDateString()}
                                </p>
                            </div>
                        )}
                        {application.rejection_reason && (
                            <div className="mt-4 rounded-lg bg-destructive/10 p-3">
                                <p className="text-sm font-medium text-destructive">
                                    Rejection Reason:
                                </p>
                                <p className="mt-1 text-sm text-destructive/80">
                                    {application.rejection_reason}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Ghana Card Documents */}
                {(application.ghana_card_front ||
                    application.ghana_card_back) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <IdCard className="h-5 w-5" />
                                Ghana Card
                            </CardTitle>
                            <CardDescription>
                                National identification documents
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-2">
                                {application.ghana_card_front && (
                                    <div className="space-y-2">
                                        <h3 className="text-sm font-medium">
                                            Front of Ghana Card
                                        </h3>
                                        <div className="overflow-hidden rounded-lg border">
                                            <img
                                                src={
                                                    application.ghana_card_front
                                                }
                                                alt="Ghana Card Front"
                                                className="h-auto w-full object-cover"
                                            />
                                        </div>
                                    </div>
                                )}
                                {application.ghana_card_back && (
                                    <div className="space-y-2">
                                        <h3 className="text-sm font-medium">
                                            Back of Ghana Card
                                        </h3>
                                        <div className="overflow-hidden rounded-lg border">
                                            <img
                                                src={
                                                    application.ghana_card_back
                                                }
                                                alt="Ghana Card Back"
                                                className="h-auto w-full object-cover"
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Business Documents (Registered Vendors) */}
                {application.is_registered_vendor &&
                    (application.business_certificate_document ||
                        application.tin_document) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Briefcase className="h-5 w-5" />
                                    Business Documents
                                </CardTitle>
                                <CardDescription>
                                    Business registration and tax documents
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {application.business_certificate_document && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Business Certificate
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        application.business_certificate_document
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View Business Certificate
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                    {application.tin_document && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                TIN Document
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        application.tin_document
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View TIN Document
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                </div>
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
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <UserIcon className="h-5 w-5" />
                                    Individual Vendor Verification
                                </CardTitle>
                                <CardDescription>
                                    Verification documents and payment details
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {application.selfie_image && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Selfie Verification
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border">
                                                <img
                                                    src={
                                                        application.selfie_image
                                                    }
                                                    alt="Vendor Selfie"
                                                    className="h-auto max-w-sm object-cover"
                                                />
                                            </div>
                                        </div>
                                    )}
                                    {application.proof_of_business && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Proof of Business
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        application.proof_of_business
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View Proof of Business
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                    {application.mobile_money_number && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Mobile Money Details
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <div className="space-y-1">
                                                    <p className="text-sm">
                                                        <span className="font-medium">
                                                            Number:
                                                        </span>{' '}
                                                        {
                                                            application.mobile_money_number
                                                        }
                                                    </p>
                                                    {application.mobile_money_provider && (
                                                        <p className="text-sm">
                                                            <span className="font-medium">
                                                                Provider:
                                                            </span>{' '}
                                                            <Badge variant="outline">
                                                                {application.mobile_money_provider.toUpperCase()}
                                                            </Badge>
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                {/* Social Media */}
                {(application.facebook_handle ||
                    application.instagram_handle ||
                    application.twitter_handle) && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Users className="h-5 w-5" />
                                Social Media
                            </CardTitle>
                            <CardDescription>
                                Business social media profiles
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-3 md:grid-cols-3">
                                {application.facebook_handle && (
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Facebook
                                        </p>
                                        <p className="text-sm font-medium">
                                            {application.facebook_handle}
                                        </p>
                                    </div>
                                )}
                                {application.instagram_handle && (
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Instagram
                                        </p>
                                        <p className="text-sm font-medium">
                                            {application.instagram_handle}
                                        </p>
                                    </div>
                                )}
                                {application.twitter_handle && (
                                    <div className="rounded-lg border p-3">
                                        <p className="text-xs text-muted-foreground">
                                            Twitter/X
                                        </p>
                                        <p className="text-sm font-medium">
                                            {application.twitter_handle}
                                        </p>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Bespoke Services */}
                {application.bespoke_services.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-lg">
                                <Package className="h-5 w-5" />
                                Selected Bespoke Services
                            </CardTitle>
                            <CardDescription>
                                Services this vendor plans to offer
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
                                {application.bespoke_services.map((service) => (
                                    <div
                                        key={service.id}
                                        className="rounded-lg border p-3"
                                    >
                                        <p className="font-medium">
                                            {service.name}
                                        </p>
                                        {service.description && (
                                            <p className="text-sm text-muted-foreground">
                                                {service.description}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>

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
                    <div className="py-4">
                        <Textarea
                            placeholder="Explain why this application is being rejected..."
                            value={data.rejection_reason}
                            onChange={(e) =>
                                setData('rejection_reason', e.target.value)
                            }
                            rows={5}
                            className="resize-none"
                        />
                        {data.rejection_reason &&
                            data.rejection_reason.length < 10 && (
                                <p className="mt-2 text-sm text-destructive">
                                    Please provide at least 10 characters
                                </p>
                            )}
                    </div>
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
