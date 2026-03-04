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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import {
    ArrowLeft,
    Briefcase,
    Calendar,
    CheckCircle,
    Download,
    Eye,
    Globe,
    Heart,
    Mail,
    MapPin,
    Music,
    Package,
    Palette,
    Pencil,
    Phone,
    ShoppingCart,
    Star,
    Store,
    Trash2,
    User as UserIcon,
    Users,
    XCircle,
} from 'lucide-react';
import { useState } from 'react';

function isImageUrl(url: string): boolean {
    return /\.(jpe?g|png|gif|webp)(\?|$)/i.test(url);
}

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

interface UserAddress {
    id: number;
    label: string | null;
    name: string | null;
    address_line_1: string;
    city: string;
    state: string | null;
    postal_code: string | null;
    country: string;
    is_default: boolean;
}

interface RecentOrder {
    id: number;
    order_number: string;
    status: string;
    total: number;
    currency: string;
    created_at: string;
}

interface RecentReview {
    id: number;
    rating: number;
    comment: string | null;
    created_at: string;
}

interface MusicGenre {
    id: number;
    name: string;
}

interface Props {
    user: User & {
        favorite_color?: string;
        favorite_music_genre?: string;
        avatar?: string;
        provider?: string | null;
        interests?: Interest[];
        personality_traits?: Interest[];
        music_genres?: MusicGenre[];
        addresses?: UserAddress[];
        shops?: Shop[];
        products_count?: number;
        services_count?: number;
        orders_count?: number;
        reviews_count?: number;
        wishlists_count?: number;
        total_spent?: number;
        avg_rating?: number | null;
        recent_orders?: RecentOrder[];
        recent_reviews?: RecentReview[];
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
    const [previewDoc, setPreviewDoc] = useState<{ url: string; title: string } | null>(null);
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, overflow: 'auto', p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={usersIndex.url()}>
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Users
                        </Link>
                    </Button>
                    <Box sx={{ display: 'flex', gap: 1 }}>
                        <Button variant="outline" size="sm" asChild>
                            <Link href={userEdit.url(user.id)}>
                                <Pencil style={{ marginRight: 8, width: 16, height: 16 }} />
                                Edit
                            </Link>
                        </Button>
                        {canDelete && (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={handleDelete}
                            >
                                <Trash2 style={{ marginRight: 8, width: 16, height: 16 }} />
                                Delete
                            </Button>
                        )}
                    </Box>
                </Box>

                {/* User Profile Header */}
                <Card>
                    <CardHeader style={{ paddingBottom: 16 }}>
                        <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 2 }}>
                            {user.avatar ? (
                                <Box
                                    component="img"
                                    src={user.avatar}
                                    alt={user.name}
                                    sx={{ width: 64, height: 64, borderRadius: '50%', objectFit: 'cover' }}
                                />
                            ) : (
                                <Box sx={{ display: 'flex', width: 64, height: 64, alignItems: 'center', justifyContent: 'center', borderRadius: '50%', bgcolor: 'primary.light', color: 'primary.main' }}>
                                    <UserIcon style={{ width: 32, height: 32 }} />
                                </Box>
                            )}
                            <Box sx={{ flex: 1 }}>
                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
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
                                            style={{ gap: 4 }}
                                        >
                                            <Heart style={{ width: 12, height: 12, fill: 'currentColor' }} />
                                            Popular
                                        </Badge>
                                    )}
                                </Box>
                                <CardDescription style={{ marginTop: 4 }}>
                                    {user.email}
                                </CardDescription>
                            </Box>
                        </Box>
                    </CardHeader>
                </Card>

                {/* Basic Information */}
                <Card>
                    <CardHeader>
                        <CardTitle style={{ fontSize: '1.125rem' }}>
                            Basic Information
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' } }}>
                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <Mail style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Email
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {user.email}
                                    </Typography>
                                    {user.email_verified_at ? (
                                        <Badge
                                            variant="outline"
                                            style={{ marginTop: 4, gap: 4, fontSize: '0.75rem' }}
                                        >
                                            <CheckCircle style={{ width: 12, height: 12 }} />{' '}
                                            Verified
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            style={{ marginTop: 4, gap: 4, fontSize: '0.75rem' }}
                                        >
                                            <XCircle style={{ width: 12, height: 12 }} /> Not
                                            Verified
                                        </Badge>
                                    )}
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <Phone style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Phone
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {user.phone || 'Not provided'}
                                    </Typography>
                                    {user.phone && user.phone_verified_at && (
                                        <Badge
                                            variant="outline"
                                            style={{ marginTop: 4, gap: 4, fontSize: '0.75rem' }}
                                        >
                                            <CheckCircle style={{ width: 12, height: 12 }} />{' '}
                                            Verified
                                        </Badge>
                                    )}
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <Calendar style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Date of Birth
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {user.date_of_birth
                                            ? new Date(
                                                  user.date_of_birth,
                                              ).toLocaleDateString()
                                            : 'Not provided'}
                                    </Typography>
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <UserIcon style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Gender
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', textTransform: 'capitalize' }}>
                                        {user.gender || 'Not provided'}
                                    </Typography>
                                </Box>
                            </Box>

                            {user.favorite_color && (
                                <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                    <Palette style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                    <Box>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Favorite Color
                                        </Typography>
                                        <Box sx={{ mt: 0.5, display: 'flex', alignItems: 'center', gap: 1 }}>
                                            <Box
                                                sx={{ width: 16, height: 16, borderRadius: 1, border: 1, borderColor: 'divider' }}
                                                style={{
                                                    backgroundColor:
                                                        user.favorite_color,
                                                }}
                                            />
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', textTransform: 'capitalize' }}>
                                                {user.favorite_color}
                                            </Typography>
                                        </Box>
                                    </Box>
                                </Box>
                            )}

                            {user.favorite_music_genre && (
                                <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                    <Music style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                    <Box>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Favorite Music Genre
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', textTransform: 'capitalize' }}>
                                            {user.favorite_music_genre}
                                        </Typography>
                                    </Box>
                                </Box>
                            )}

                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <Calendar style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Joined
                                    </Typography>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {new Date(
                                            user.created_at,
                                        ).toLocaleDateString()}
                                    </Typography>
                                </Box>
                            </Box>

                            <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                <Heart style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                <Box>
                                    <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                        Popular
                                    </Typography>
                                    {user.is_popular ? (
                                        <Badge
                                            variant="outline"
                                            style={{ marginTop: 4, gap: 4, fontSize: '0.75rem' }}
                                        >
                                            <CheckCircle style={{ width: 12, height: 12 }} />
                                            Yes
                                        </Badge>
                                    ) : (
                                        <Badge
                                            variant="secondary"
                                            style={{ marginTop: 4, gap: 4, fontSize: '0.75rem' }}
                                        >
                                            <XCircle style={{ width: 12, height: 12 }} />
                                            No
                                        </Badge>
                                    )}
                                </Box>
                            </Box>

                            {user.provider && (
                                <Box sx={{ display: 'flex', alignItems: 'flex-start', gap: 1.5 }}>
                                    <Globe style={{ marginTop: 2, width: 16, height: 16, color: 'var(--muted-foreground)' }} />
                                    <Box>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Sign-in Provider
                                        </Typography>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', textTransform: 'capitalize' }}>
                                            Signed in via {user.provider}
                                        </Typography>
                                    </Box>
                                </Box>
                            )}
                        </Box>

                        {user.bio && (
                            <Box sx={{ mt: 2, borderTop: 1, borderColor: 'divider', pt: 2 }}>
                                <Typography variant="h6" sx={{ mb: 1, fontSize: '0.875rem', fontWeight: 500 }}>
                                    Bio
                                </Typography>
                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    {user.bio}
                                </Typography>
                            </Box>
                        )}
                    </CardContent>
                </Card>

                {/* Interests & Personality Traits */}
                {(user.interests && user.interests.length > 0) ||
                (user.personality_traits &&
                    user.personality_traits.length > 0) ? (
                    <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                        {user.interests && user.interests.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                        <Heart style={{ width: 20, height: 20 }} />
                                        Interests
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                        {user.interests.map((interest) => (
                                            <Badge
                                                key={interest.id}
                                                variant="secondary"
                                                style={{ gap: 4 }}
                                            >
                                                {interest.icon && (
                                                    <span>{interest.icon}</span>
                                                )}
                                                {interest.name}
                                            </Badge>
                                        ))}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}

                        {user.personality_traits &&
                            user.personality_traits.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                            <UserIcon style={{ width: 20, height: 20 }} />
                                            Personality Traits
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                            {user.personality_traits.map(
                                                (trait) => (
                                                    <Badge
                                                        key={trait.id}
                                                        variant="outline"
                                                        style={{ gap: 4 }}
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
                                        </Box>
                                    </CardContent>
                                </Card>
                            )}
                    </Box>
                ) : null}

                {/* Activity Summary */}
                {(user.orders_count !== undefined || user.reviews_count !== undefined || user.wishlists_count !== undefined) && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <ShoppingCart style={{ width: 20, height: 20 }} />
                                Activity Summary
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: 'repeat(2, 1fr)', md: 'repeat(5, 1fr)' } }}>
                                <Box sx={{ textAlign: 'center', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {user.orders_count ?? 0}
                                    </Box>
                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Orders</Typography>
                                </Box>
                                <Box sx={{ textAlign: 'center', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        GHS {(user.total_spent ?? 0).toFixed(2)}
                                    </Box>
                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Total Spent</Typography>
                                </Box>
                                <Box sx={{ textAlign: 'center', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {user.reviews_count ?? 0}
                                    </Box>
                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Reviews</Typography>
                                </Box>
                                <Box sx={{ textAlign: 'center', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {user.avg_rating ? `${user.avg_rating}/5` : 'N/A'}
                                    </Box>
                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Avg Rating</Typography>
                                </Box>
                                <Box sx={{ textAlign: 'center', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                        {user.wishlists_count ?? 0}
                                    </Box>
                                    <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Wishlist</Typography>
                                </Box>
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Music Genres */}
                {user.music_genres && user.music_genres.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <Music style={{ width: 20, height: 20 }} />
                                Music Genres
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexWrap: 'wrap', gap: 1 }}>
                                {user.music_genres.map((genre) => (
                                    <Badge key={genre.id} variant="secondary" style={{ gap: 4 }}>
                                        {genre.name}
                                    </Badge>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Saved Addresses */}
                {user.addresses && user.addresses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <MapPin style={{ width: 20, height: 20 }} />
                                Saved Addresses
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                {user.addresses.map((address) => (
                                    <Box key={address.id} sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                        <Box>
                                            {address.label && (
                                                <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {address.label}
                                                </Typography>
                                            )}
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {[address.name, address.address_line_1, address.city, address.state, address.postal_code, address.country].filter(Boolean).join(', ')}
                                            </Typography>
                                        </Box>
                                        {address.is_default && (
                                            <Badge variant="outline" style={{ gap: 4 }}>
                                                <CheckCircle style={{ width: 12, height: 12 }} />
                                                Default
                                            </Badge>
                                        )}
                                    </Box>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Recent Orders */}
                {user.recent_orders && user.recent_orders.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <ShoppingCart style={{ width: 20, height: 20 }} />
                                Recent Orders
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ overflowX: 'auto' }}>
                                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.875rem' }}>
                                    <thead>
                                        <tr style={{ borderBottom: '1px solid var(--border)' }}>
                                            <th style={{ textAlign: 'left', padding: '8px 12px', fontWeight: 500 }}>Order #</th>
                                            <th style={{ textAlign: 'left', padding: '8px 12px', fontWeight: 500 }}>Status</th>
                                            <th style={{ textAlign: 'right', padding: '8px 12px', fontWeight: 500 }}>Total</th>
                                            <th style={{ textAlign: 'right', padding: '8px 12px', fontWeight: 500 }}>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {user.recent_orders.map((order) => (
                                            <tr key={order.id} style={{ borderBottom: '1px solid var(--border)' }}>
                                                <td style={{ padding: '8px 12px' }}>{order.order_number}</td>
                                                <td style={{ padding: '8px 12px' }}>
                                                    <Badge variant={order.status === 'delivered' ? 'default' : order.status === 'pending' ? 'outline' : 'secondary'}>
                                                        {order.status}
                                                    </Badge>
                                                </td>
                                                <td style={{ padding: '8px 12px', textAlign: 'right' }}>
                                                    {order.currency} {Number(order.total).toFixed(2)}
                                                </td>
                                                <td style={{ padding: '8px 12px', textAlign: 'right', color: 'var(--muted-foreground)' }}>
                                                    {new Date(order.created_at).toLocaleDateString()}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Recent Reviews */}
                {user.recent_reviews && user.recent_reviews.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                <Star style={{ width: 20, height: 20 }} />
                                Recent Reviews
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                {user.recent_reviews.map((review) => (
                                    <Box key={review.id} sx={{ borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                        <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', mb: 0.5 }}>
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                {Array.from({ length: 5 }, (_, i) => (
                                                    <Star
                                                        key={i}
                                                        style={{
                                                            width: 14,
                                                            height: 14,
                                                            fill: i < review.rating ? 'var(--warning, #f59e0b)' : 'none',
                                                            color: i < review.rating ? 'var(--warning, #f59e0b)' : 'var(--muted-foreground)',
                                                        }}
                                                    />
                                                ))}
                                                <Typography sx={{ ml: 0.5, fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {review.rating}/5
                                                </Typography>
                                            </Box>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                {new Date(review.created_at).toLocaleDateString()}
                                            </Typography>
                                        </Box>
                                        {review.comment && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                {review.comment.length > 150 ? `${review.comment.slice(0, 150)}...` : review.comment}
                                            </Typography>
                                        )}
                                    </Box>
                                ))}
                            </Box>
                        </CardContent>
                    </Card>
                )}

                {/* Ghana Card Images */}
                {user.vendor_application?.ghana_card_front &&
                    user.vendor_application?.ghana_card_back && (
                        <Card>
                            <CardHeader>
                                <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                    <UserIcon style={{ width: 20, height: 20 }} />
                                    Ghana Card Images
                                </CardTitle>
                                <CardDescription>
                                    Uploaded identification documents for vendor
                                    application
                                    {user.vendor_application.submitted_at && (
                                        <Box component="span" sx={{ ml: 0.5 }}>
                                            (Submitted on{' '}
                                            {new Date(
                                                user.vendor_application.submitted_at,
                                            ).toLocaleDateString()}
                                            )
                                        </Box>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {/* Application Status Banner */}
                                <Box sx={{ mb: 2, borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between' }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 0.5 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Application Status
                                            </Typography>
                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
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
                                                <Box component="span" sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                    Step{' '}
                                                    {
                                                        user.vendor_application
                                                            .completed_step
                                                    }{' '}
                                                    of 4 completed
                                                </Box>
                                            </Box>
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 1 }}>
                                            {user.vendor_application
                                                .reviewed_at && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                                    Reviewed:{' '}
                                                    {new Date(
                                                        user.vendor_application.reviewed_at,
                                                    ).toLocaleDateString()}
                                                </Typography>
                                            )}
                                            {canApproveOrReject && (
                                                <Box sx={{ display: 'flex', gap: 1 }}>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            setShowRejectDialog(
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <XCircle style={{ marginRight: 8, width: 16, height: 16 }} />
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
                                                        <CheckCircle style={{ marginRight: 8, width: 16, height: 16 }} />
                                                        Approve
                                                    </Button>
                                                </Box>
                                            )}
                                        </Box>
                                    </Box>
                                    {user.vendor_application
                                        .rejection_reason && (
                                        <Box sx={{ mt: 1.5, borderRadius: 1.5, bgcolor: 'error.light', opacity: 0.1, p: 1.5 }}>
                                            <Typography sx={{ fontSize: '0.875rem', fontWeight: 500, color: 'error.main' }}>
                                                Rejection Reason:
                                            </Typography>
                                            <Typography sx={{ mt: 0.5, fontSize: '0.875rem', color: 'error.main', opacity: 0.8 }}>
                                                {
                                                    user.vendor_application
                                                        .rejection_reason
                                                }
                                            </Typography>
                                        </Box>
                                    )}
                                    <Box sx={{ mt: 1.5 }}>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                            Vendor Type:{' '}
                                            <Badge variant="outline">
                                                {user.vendor_application
                                                    .is_registered_vendor
                                                    ? 'Registered Business'
                                                    : 'Unregistered Vendor'}
                                            </Badge>
                                        </Typography>
                                    </Box>
                                </Box>

                                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Front of Ghana Card
                                        </Typography>
                                        <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                            <Box
                                                component="img"
                                                src={
                                                    user.vendor_application
                                                        .ghana_card_front
                                                }
                                                alt="Ghana Card Front"
                                                sx={{ height: 'auto', width: '100%', objectFit: 'cover' }}
                                            />
                                        </Box>
                                    </Box>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                            Back of Ghana Card
                                        </Typography>
                                        <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                            <Box
                                                component="img"
                                                src={
                                                    user.vendor_application
                                                        .ghana_card_back
                                                }
                                                alt="Ghana Card Back"
                                                sx={{ height: 'auto', width: '100%', objectFit: 'cover' }}
                                            />
                                        </Box>
                                    </Box>
                                </Box>
                            </CardContent>
                        </Card>
                    )}

                {/* Business Documents */}
                {user.vendor_application &&
                    (user.vendor_application.business_certificate_document ||
                        user.vendor_application.tin_document) && (
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
                                    {user.vendor_application
                                        .business_certificate_document && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Business Certificate
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: user.vendor_application!.business_certificate_document!, title: 'Business Certificate' })}
                                                    sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.875rem', color: 'primary.main', bgcolor: 'transparent', border: 'none', cursor: 'pointer', p: 0, '&:hover': { textDecoration: 'underline' } }}
                                                >
                                                    <Eye style={{ width: 16, height: 16 }} />
                                                    View Business Certificate
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}
                                    {user.vendor_application.tin_document && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                TIN Document
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: user.vendor_application!.tin_document!, title: 'TIN Document' })}
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

                {/* Unregistered Vendor Documents */}
                {user.vendor_application &&
                    !user.vendor_application.is_registered_vendor &&
                    (user.vendor_application.selfie_image ||
                        user.vendor_application.proof_of_business ||
                        user.vendor_application.mobile_money_number) && (
                        <Card>
                            <CardHeader>
                                <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: '1.125rem' }}>
                                    <UserIcon style={{ width: 20, height: 20 }} />
                                    Unregistered Vendor Verification
                                </CardTitle>
                                <CardDescription>
                                    Verification documents and payment details
                                    for unregistered vendor
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* Selfie Image */}
                                    {user.vendor_application.selfie_image && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Selfie Verification
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider' }}>
                                                <Box
                                                    component="img"
                                                    src={
                                                        user.vendor_application
                                                            .selfie_image
                                                    }
                                                    alt="Vendor Selfie"
                                                    sx={{ height: 'auto', maxWidth: 384, objectFit: 'cover' }}
                                                />
                                            </Box>
                                        </Box>
                                    )}

                                    {/* Proof of Business */}
                                    {user.vendor_application
                                        .proof_of_business && (
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                            <Typography variant="h6" sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                Proof of Business
                                            </Typography>
                                            <Box sx={{ overflow: 'hidden', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}>
                                                <Box
                                                    component="button"
                                                    onClick={() => setPreviewDoc({ url: user.vendor_application!.proof_of_business!, title: 'Proof of Business' })}
                                                    sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '0.875rem', color: 'primary.main', bgcolor: 'transparent', border: 'none', cursor: 'pointer', p: 0, '&:hover': { textDecoration: 'underline' } }}
                                                >
                                                    <Eye style={{ width: 16, height: 16 }} />
                                                    View Proof of Business
                                                </Box>
                                            </Box>
                                        </Box>
                                    )}

                                    {/* Mobile Money Details */}
                                    {user.vendor_application
                                        .mobile_money_number && (
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
                                                            user
                                                                .vendor_application
                                                                .mobile_money_number
                                                        }
                                                    </Typography>
                                                    {user.vendor_application
                                                        .mobile_money_provider && (
                                                        <Typography sx={{ fontSize: '0.875rem' }}>
                                                            <Box component="span" sx={{ fontWeight: 500 }}>
                                                                Provider:
                                                            </Box>{' '}
                                                            <Badge variant="outline">
                                                                {user.vendor_application.mobile_money_provider.toUpperCase()}
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

                {/* Social Media Handles */}
                {user.vendor_application &&
                    (user.vendor_application.facebook_handle ||
                        user.vendor_application.instagram_handle ||
                        user.vendor_application.twitter_handle) && (
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
                                <Box sx={{ display: 'grid', gap: 1.5, gridTemplateColumns: { md: 'repeat(2, 1fr)', lg: 'repeat(3, 1fr)' } }}>
                                    {user.vendor_application
                                        .facebook_handle && (
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                            <Package style={{ width: 16, height: 16, color: 'var(--primary)' }} />
                                            <Box>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                    Facebook
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {
                                                        user.vendor_application
                                                            .facebook_handle
                                                    }
                                                </Typography>
                                            </Box>
                                        </Box>
                                    )}
                                    {user.vendor_application
                                        .instagram_handle && (
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                            <Package style={{ width: 16, height: 16, color: 'var(--accent)' }} />
                                            <Box>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                    Instagram
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {
                                                        user.vendor_application
                                                            .instagram_handle
                                                    }
                                                </Typography>
                                            </Box>
                                        </Box>
                                    )}
                                    {user.vendor_application.twitter_handle && (
                                        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, borderRadius: 2, border: 1, borderColor: 'divider', p: 1.5 }}>
                                            <Package style={{ width: 16, height: 16, color: 'var(--success)' }} />
                                            <Box>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>
                                                    Twitter/X
                                                </Typography>
                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                    {
                                                        user.vendor_application
                                                            .twitter_handle
                                                    }
                                                </Typography>
                                            </Box>
                                        </Box>
                                    )}
                                </Box>
                            </CardContent>
                        </Card>
                    )}

                {/* Vendor Information */}
                {user.role === 'vendor' && (
                    <Box sx={{ display: 'grid', gap: 2 }}>
                        {/* Stats Overview */}
                        <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { md: 'repeat(3, 1fr)' } }}>
                            <Card>
                                <CardHeader style={{ paddingBottom: 12 }}>
                                    <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500, color: 'gray' }}>
                                        Total Shops
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Store style={{ width: 20, height: 20, color: 'var(--primary)' }} />
                                        <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                            {user.shops?.length || 0}
                                        </Box>
                                    </Box>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader style={{ paddingBottom: 12 }}>
                                    <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500, color: 'gray' }}>
                                        Total Products
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Package style={{ width: 20, height: 20, color: 'var(--accent)' }} />
                                        <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                            {user.products_count || 0}
                                        </Box>
                                    </Box>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader style={{ paddingBottom: 12 }}>
                                    <CardTitle style={{ fontSize: '0.875rem', fontWeight: 500, color: 'gray' }}>
                                        Total Services
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Briefcase style={{ width: 20, height: 20, color: 'var(--success)' }} />
                                        <Box component="span" sx={{ fontSize: '1.5rem', fontWeight: 700 }}>
                                            {user.services_count || 0}
                                        </Box>
                                    </Box>
                                </CardContent>
                            </Card>
                        </Box>

                        {/* Shops List */}
                        {user.shops && user.shops.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                                        <Store style={{ width: 20, height: 20 }} />
                                        Shops
                                    </CardTitle>
                                    <CardDescription>
                                        All shops owned by this vendor
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                        {user.shops.map((shop) => (
                                            <Box
                                                key={shop.id}
                                                sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderRadius: 2, border: 1, borderColor: 'divider', p: 2 }}
                                            >
                                                <Box sx={{ flex: 1 }}>
                                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                                        <Typography variant="h6" sx={{ fontWeight: 500 }}>
                                                            {shop.name}
                                                        </Typography>
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
                                                    </Box>
                                                    {shop.location && (
                                                        <Typography sx={{ mt: 0.5, display: 'flex', alignItems: 'center', gap: 0.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                            <MapPin style={{ width: 12, height: 12 }} />
                                                            {shop.location}
                                                        </Typography>
                                                    )}
                                                    <Box sx={{ mt: 1, display: 'flex', gap: 2, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        <Box component="span" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                            <Package style={{ width: 12, height: 12 }} />
                                                            {
                                                                shop.products_count
                                                            }{' '}
                                                            products
                                                        </Box>
                                                        <Box component="span" sx={{ display: 'flex', alignItems: 'center', gap: 0.5 }}>
                                                            <Briefcase style={{ width: 12, height: 12 }} />
                                                            {
                                                                shop.services_count
                                                            }{' '}
                                                            services
                                                        </Box>
                                                    </Box>
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                </CardContent>
                            </Card>
                        )}
                    </Box>
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
