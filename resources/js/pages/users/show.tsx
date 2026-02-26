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
import {
    edit as userEdit,
    show as userShow,
    index as usersIndex,
} from '@/routes/users';
import { type BreadcrumbItem, type User } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    Briefcase,
    Calendar,
    CheckCircle,
    Heart,
    Mail,
    MapPin,
    Music,
    Package,
    Palette,
    Pencil,
    Phone,
    Store,
    Trash2,
    User as UserIcon,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

interface Shop {
    id: number;
    name: string;
    location: string;
    is_active: boolean;
    products_count: number;
    services_count: number;
}

interface Interest {
    id: number;
    name: string;
    icon: string;
}

interface VendorApplication {
    id: number;
    status: string;
    current_step: number;
    completed_step: number;
    is_registered_vendor: boolean;

    // Step 1: Ghana Card
    ghana_card_front: string | null;
    ghana_card_back: string | null;

    // Step 2: Business Registration Flags
    has_business_certificate: boolean;
    has_tin: boolean;

    // Step 3A: Registered Vendor Documents
    business_certificate_document: string | null;
    tin_document: string | null;

    // Step 3B: Unregistered Vendor Documents
    selfie_image: string | null;
    proof_of_business: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;

    // Social Media
    facebook_handle: string | null;
    instagram_handle: string | null;
    twitter_handle: string | null;

    // Review Details
    submitted_at: string | null;
    reviewed_at: string | null;
    rejection_reason: string | null;
}

interface Props {
    user: User & {
        favorite_color?: string;
        favorite_music_genre?: string;
        avatar?: string;
        interests?: Interest[];
        personality_traits?: Interest[];
        shops?: Shop[];
        products_count?: number;
        services_count?: number;
        phone_verified_at?: string;
        vendor_application?: VendorApplication;
    };
    canDelete: boolean;
}

const breadcrumbs = (user: User): BreadcrumbItem[] => [
    {
        title: 'Users',
        href: usersIndex().url,
    },
    {
        title: user.name,
        href: userShow.url(user.id),
    },
];

export default function UserShow({ user, canDelete }: Props) {
    const [showRejectDialog, setShowRejectDialog] = useState(false);
    const { data, setData, post, processing } = useForm({
        rejection_reason: '',
    });

    const handleDelete = () => {
        if (
            confirm(
                `Are you sure you want to delete ${user.name}? This action cannot be undone.`,
            )
        ) {
            router.delete(userShow.url(user.id), {
                onSuccess: () => router.visit(usersIndex.url()),
            });
        }
    };

    const handleApprove = (applicationId: number) => {
        if (
            confirm('Are you sure you want to approve this vendor application?')
        ) {
            router.post(
                `/dashboard/vendor-applications/${applicationId}/approve`,
                {},
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const handleReject = (applicationId: number) => {
        if (data.rejection_reason.trim().length < 10) {
            alert(
                'Please provide a detailed rejection reason (at least 10 characters).',
            );
            return;
        }
        post(`/dashboard/vendor-applications/${applicationId}/reject`, {
            onSuccess: () => setShowRejectDialog(false),
            preserveScroll: true,
        });
    };

    const canApproveOrReject =
        user.vendor_application &&
        ['pending', 'under_review'].includes(user.vendor_application.status);

    return (
        <AppLayout breadcrumbs={breadcrumbs(user)}>
            <Head title={`User: ${user.name}`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-auto p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={usersIndex.url()}>
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Users
                        </Link>
                    </Button>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={userEdit.url(user.id)}>
                                <Pencil className="mr-2 size-4" />
                                Edit
                            </Link>
                        </Button>
                        {canDelete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleDelete}
                            >
                                <Trash2 className="mr-2 size-4" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                {/* User Profile Header */}
                <Card>
                    <CardHeader className="pb-4">
                        <div className="flex items-start gap-4">
                            {user.avatar ? (
                                <img
                                    src={user.avatar}
                                    alt={user.name}
                                    className="h-16 w-16 rounded-full object-cover"
                                />
                            ) : (
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <UserIcon className="h-8 w-8" />
                                </div>
                            )}
                            <div className="flex-1">
                                <div className="flex items-center gap-2">
                                    <CardTitle>{user.name}</CardTitle>
                                    <Badge
                                        variant={
                                            user.role === 'vendor'
                                                ? 'default'
                                                : user.role === 'admin' ||
                                                    user.role === 'super_admin'
                                                  ? 'destructive'
                                                  : 'secondary'
                                        }
                                    >
                                        {user.role}
                                    </Badge>
                                    {user.is_popular && (
                                        <Badge
                                            variant="outline"
                                            className="gap-1"
                                        >
                                            <Heart className="h-3 w-3 fill-current" />
                                            Popular
                                        </Badge>
                                    )}
                                </div>
                                <CardDescription className="mt-1">
                                    {user.email}
                                </CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Basic Information */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">
                            Basic Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <div className="flex items-start gap-3">
                                <Mail className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Email
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {user.email}
                                    </p>
                                    {user.email_verified_at ? (
                                        <Badge
                                            variant="outline"
                                            className="mt-1 gap-1 text-xs"
                                        >
                                            <CheckCircle className="h-3 w-3" />{' '}
                                            Verified
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            className="mt-1 gap-1 text-xs"
                                        >
                                            <XCircle className="h-3 w-3" /> Not
                                            Verified
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <Phone className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Phone
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {user.phone || 'Not provided'}
                                    </p>
                                    {user.phone && user.phone_verified_at && (
                                        <Badge
                                            variant="outline"
                                            className="mt-1 gap-1 text-xs"
                                        >
                                            <CheckCircle className="h-3 w-3" />{' '}
                                            Verified
                                        </Badge>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <Calendar className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Date of Birth
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {user.date_of_birth
                                            ? new Date(
                                                  user.date_of_birth,
                                              ).toLocaleDateString()
                                            : 'Not provided'}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <UserIcon className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Gender
                                    </h3>
                                    <p className="text-sm text-muted-foreground capitalize">
                                        {user.gender || 'Not provided'}
                                    </p>
                                </div>
                            </div>

                            {user.favorite_color && (
                                <div className="flex items-start gap-3">
                                    <Palette className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Favorite Color
                                        </h3>
                                        <div className="mt-1 flex items-center gap-2">
                                            <div
                                                className="h-4 w-4 rounded border"
                                                style={{
                                                    backgroundColor:
                                                        user.favorite_color,
                                                }}
                                            />
                                            <p className="text-sm text-muted-foreground capitalize">
                                                {user.favorite_color}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {user.favorite_music_genre && (
                                <div className="flex items-start gap-3">
                                    <Music className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <h3 className="text-sm font-medium">
                                            Favorite Music Genre
                                        </h3>
                                        <p className="text-sm text-muted-foreground capitalize">
                                            {user.favorite_music_genre}
                                        </p>
                                    </div>
                                </div>
                            )}

                            <div className="flex items-start gap-3">
                                <Calendar className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Joined
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <Heart className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div>
                                    <h3 className="text-sm font-medium">
                                        Popular
                                    </h3>
                                    {user.is_popular ? (
                                        <Badge
                                            variant="outline"
                                            className="mt-1 gap-1 text-xs"
                                        >
                                            <CheckCircle className="h-3 w-3" />
                                            Yes
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            className="mt-1 gap-1 text-xs"
                                        >
                                            <XCircle className="h-3 w-3" />
                                            No
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>

                        {user.bio && (
                            <div className="mt-4 border-t pt-4">
                                <h3 className="mb-2 text-sm font-medium">
                                    Bio
                                </h3>
                                <p className="text-sm text-muted-foreground">
                                    {user.bio}
                                </p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Interests & Personality Traits */}
                {(user.interests && user.interests.length > 0) ||
                (user.personality_traits &&
                    user.personality_traits.length > 0) ? (
                    <div className="grid gap-4 md:grid-cols-2">
                        {user.interests && user.interests.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2 text-lg">
                                        <Heart className="h-5 w-5" />
                                        Interests
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex flex-wrap gap-2">
                                        {user.interests.map((interest) => (
                                            <Badge
                                                key={interest.id}
                                                variant="secondary"
                                                className="gap-1"
                                            >
                                                {interest.icon && (
                                                    <span>{interest.icon}</span>
                                                )}
                                                {interest.name}
                                            </Badge>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {user.personality_traits &&
                            user.personality_traits.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2 text-lg">
                                            <UserIcon className="h-5 w-5" />
                                            Personality Traits
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex flex-wrap gap-2">
                                            {user.personality_traits.map(
                                                (trait) => (
                                                    <Badge
                                                        key={trait.id}
                                                        variant="outline"
                                                        className="gap-1"
                                                    >
                                                        {trait.icon && (
                                                            <span>
                                                                {trait.icon}
                                                            </span>
                                                        )}
                                                        {trait.name}
                                                    </Badge>
                                                ),
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                    </div>
                ) : null}

                {/* Ghana Card Images */}
                {user.vendor_application?.ghana_card_front &&
                    user.vendor_application?.ghana_card_back && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <UserIcon className="h-5 w-5" />
                                    Ghana Card Images
                                </CardTitle>
                                <CardDescription>
                                    Uploaded identification documents for vendor
                                    application
                                    {user.vendor_application.submitted_at && (
                                        <span className="ml-1">
                                            (Submitted on{' '}
                                            {new Date(
                                                user.vendor_application.submitted_at,
                                            ).toLocaleDateString()}
                                            )
                                        </span>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {/* Application Status Banner */}
                                <div className="mb-4 rounded-lg border p-4">
                                    <div className="flex items-start justify-between">
                                        <div className="space-y-1">
                                            <h4 className="text-sm font-medium">
                                                Application Status
                                            </h4>
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        user.vendor_application
                                                            .status ===
                                                        'approved'
                                                            ? 'default'
                                                            : user
                                                                    .vendor_application
                                                                    .status ===
                                                                'rejected'
                                                              ? 'destructive'
                                                              : user
                                                                      .vendor_application
                                                                      .status ===
                                                                  'under_review'
                                                                ? 'secondary'
                                                                : 'outline'
                                                    }
                                                >
                                                    {user.vendor_application.status
                                                        .replace(/_/g, ' ')
                                                        .toUpperCase()}
                                                </Badge>
                                                <span className="text-sm text-muted-foreground">
                                                    Step{' '}
                                                    {
                                                        user.vendor_application
                                                            .completed_step
                                                    }{' '}
                                                    of 4 completed
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex flex-col items-end gap-2">
                                            {user.vendor_application
                                                .reviewed_at && (
                                                <p className="text-sm text-muted-foreground">
                                                    Reviewed:{' '}
                                                    {new Date(
                                                        user.vendor_application.reviewed_at,
                                                    ).toLocaleDateString()}
                                                </p>
                                            )}
                                            {canApproveOrReject && (
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            setShowRejectDialog(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <XCircle className="mr-2 size-4" />
                                                        Reject
                                                    </Button>
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleApprove(
                                                                user
                                                                    .vendor_application!
                                                                    .id,
                                                            )
                                                        }
                                                    >
                                                        <CheckCircle className="mr-2 size-4" />
                                                        Approve
                                                    </Button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                    {user.vendor_application
                                        .rejection_reason && (
                                        <div className="mt-3 rounded-md bg-destructive/10 p-3">
                                            <p className="text-sm font-medium text-destructive">
                                                Rejection Reason:
                                            </p>
                                            <p className="mt-1 text-sm text-destructive/80">
                                                {
                                                    user.vendor_application
                                                        .rejection_reason
                                                }
                                            </p>
                                        </div>
                                    )}
                                    <div className="mt-3">
                                        <p className="text-xs text-muted-foreground">
                                            Vendor Type:{' '}
                                            <Badge variant="outline">
                                                {user.vendor_application
                                                    .is_registered_vendor
                                                    ? 'Registered Business'
                                                    : 'Unregistered Vendor'}
                                            </Badge>
                                        </p>
                                    </div>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <h3 className="text-sm font-medium">
                                            Front of Ghana Card
                                        </h3>
                                        <div className="overflow-hidden rounded-lg border">
                                            <img
                                                src={
                                                    user.vendor_application
                                                        .ghana_card_front
                                                }
                                                alt="Ghana Card Front"
                                                className="h-auto w-full object-cover"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-2">
                                        <h3 className="text-sm font-medium">
                                            Back of Ghana Card
                                        </h3>
                                        <div className="overflow-hidden rounded-lg border">
                                            <img
                                                src={
                                                    user.vendor_application
                                                        .ghana_card_back
                                                }
                                                alt="Ghana Card Back"
                                                className="h-auto w-full object-cover"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                {/* Business Documents */}
                {user.vendor_application &&
                    (user.vendor_application.business_certificate_document ||
                        user.vendor_application.tin_document) && (
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
                                    {user.vendor_application
                                        .business_certificate_document && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Business Certificate
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        user.vendor_application
                                                            .business_certificate_document
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View Business Certificate
                                                    (PDF)
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                    {user.vendor_application.tin_document && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                TIN Document
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        user.vendor_application
                                                            .tin_document
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View TIN Document (PDF)
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                {/* Unregistered Vendor Documents */}
                {user.vendor_application &&
                    !user.vendor_application.is_registered_vendor &&
                    (user.vendor_application.selfie_image ||
                        user.vendor_application.proof_of_business ||
                        user.vendor_application.mobile_money_number) && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <UserIcon className="h-5 w-5" />
                                    Unregistered Vendor Verification
                                </CardTitle>
                                <CardDescription>
                                    Verification documents and payment details
                                    for unregistered vendor
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {/* Selfie Image */}
                                    {user.vendor_application.selfie_image && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Selfie Verification
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border">
                                                <img
                                                    src={
                                                        user.vendor_application
                                                            .selfie_image
                                                    }
                                                    alt="Vendor Selfie"
                                                    className="h-auto max-w-sm object-cover"
                                                />
                                            </div>
                                        </div>
                                    )}

                                    {/* Proof of Business */}
                                    {user.vendor_application
                                        .proof_of_business && (
                                        <div className="space-y-2">
                                            <h3 className="text-sm font-medium">
                                                Proof of Business
                                            </h3>
                                            <div className="overflow-hidden rounded-lg border p-4">
                                                <a
                                                    href={
                                                        user.vendor_application
                                                            .proof_of_business
                                                    }
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-2 text-sm text-primary hover:underline"
                                                >
                                                    <Package className="h-4 w-4" />
                                                    View Proof of Business
                                                    Document
                                                </a>
                                            </div>
                                        </div>
                                    )}

                                    {/* Mobile Money Details */}
                                    {user.vendor_application
                                        .mobile_money_number && (
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
                                                            user
                                                                .vendor_application
                                                                .mobile_money_number
                                                        }
                                                    </p>
                                                    {user.vendor_application
                                                        .mobile_money_provider && (
                                                        <p className="text-sm">
                                                            <span className="font-medium">
                                                                Provider:
                                                            </span>{' '}
                                                            <Badge variant="outline">
                                                                {user.vendor_application.mobile_money_provider.toUpperCase()}
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

                {/* Social Media Handles */}
                {user.vendor_application &&
                    (user.vendor_application.facebook_handle ||
                        user.vendor_application.instagram_handle ||
                        user.vendor_application.twitter_handle) && (
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
                                <div className="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                                    {user.vendor_application
                                        .facebook_handle && (
                                        <div className="flex items-center gap-2 rounded-lg border p-3">
                                            <Package className="h-4 w-4 text-primary" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Facebook
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {
                                                        user.vendor_application
                                                            .facebook_handle
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    {user.vendor_application
                                        .instagram_handle && (
                                        <div className="flex items-center gap-2 rounded-lg border p-3">
                                            <Package className="h-4 w-4 text-accent" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Instagram
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {
                                                        user.vendor_application
                                                            .instagram_handle
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                    {user.vendor_application.twitter_handle && (
                                        <div className="flex items-center gap-2 rounded-lg border p-3">
                                            <Package className="h-4 w-4 text-success" />
                                            <div>
                                                <p className="text-xs text-muted-foreground">
                                                    Twitter/X
                                                </p>
                                                <p className="text-sm font-medium">
                                                    {
                                                        user.vendor_application
                                                            .twitter_handle
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                {/* Vendor Information */}
                {user.role === 'vendor' && (
                    <div className="grid gap-4">
                        {/* Stats Overview */}
                        <div className="grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Total Shops
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <Store className="h-5 w-5 text-primary" />
                                        <span className="text-2xl font-bold">
                                            {user.shops?.length || 0}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Total Products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <Package className="h-5 w-5 text-accent" />
                                        <span className="text-2xl font-bold">
                                            {user.products_count || 0}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm font-medium text-muted-foreground">
                                        Total Services
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="flex items-center gap-2">
                                        <Briefcase className="h-5 w-5 text-success" />
                                        <span className="text-2xl font-bold">
                                            {user.services_count || 0}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Shops List */}
                        {user.shops && user.shops.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Store className="h-5 w-5" />
                                        Shops
                                    </CardTitle>
                                    <CardDescription>
                                        All shops owned by this vendor
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {user.shops.map((shop) => (
                                            <div
                                                key={shop.id}
                                                className="flex items-center justify-between rounded-lg border p-4"
                                            >
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <h4 className="font-medium">
                                                            {shop.name}
                                                        </h4>
                                                        <Badge
                                                            variant={
                                                                shop.is_active
                                                                    ? 'default'
                                                                    : 'secondary'
                                                            }
                                                        >
                                                            {shop.is_active
                                                                ? 'Active'
                                                                : 'Inactive'}
                                                        </Badge>
                                                    </div>
                                                    {shop.location && (
                                                        <p className="mt-1 flex items-center gap-1 text-sm text-muted-foreground">
                                                            <MapPin className="h-3 w-3" />
                                                            {shop.location}
                                                        </p>
                                                    )}
                                                    <div className="mt-2 flex gap-4 text-sm text-muted-foreground">
                                                        <span className="flex items-center gap-1">
                                                            <Package className="h-3 w-3" />
                                                            {
                                                                shop.products_count
                                                            }{' '}
                                                            products
                                                        </span>
                                                        <span className="flex items-center gap-1">
                                                            <Briefcase className="h-3 w-3" />
                                                            {
                                                                shop.services_count
                                                            }{' '}
                                                            services
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                )}
            </div>

            {/* Reject Dialog */}
            {user.vendor_application && (
                <Dialog
                    open={showRejectDialog}
                    onOpenChange={setShowRejectDialog}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reject Vendor Application</DialogTitle>
                            <DialogDescription>
                                Please provide a detailed reason for rejecting
                                this application. The applicant will be able to
                                see this message.
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
                                onClick={() =>
                                    handleReject(user.vendor_application!.id)
                                }
                                disabled={
                                    processing ||
                                    data.rejection_reason.length < 10
                                }
                            >
                                Reject Application
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
