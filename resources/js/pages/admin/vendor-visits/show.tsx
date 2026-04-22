import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useInactivityLock } from '@/hooks/use-inactivity-lock';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft, CheckCircle, IdCard, MapPin, Store, User } from 'lucide-react';

interface Visit {
    id: string;
    status: string;
    submitted_at: string | null;
    ghana_card_number: string | null;
    tin_number: string | null;
    has_shop: boolean;
    shop_location: string | null;
    primary_business_address: string | null;
    storefront_photo: string | null;
    owner_photo: string | null;
    field_agent: {
        name: string;
    };
    application: {
        id: number;
        status: string;
        user: {
            name: string;
            email: string;
            phone: string | null;
        };
        is_registered_vendor: boolean;
        ghana_card_front: string | null;
        ghana_card_back: string | null;
    };
}

interface Props {
    visit: Visit;
}

export default function VendorVisitShow({ visit }: Props) {
    useInactivityLock();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Vendor Visits',
            href: '/dashboard/vendor-visits',
        },
        {
            title: `Review: ${visit.application?.user?.name || 'Unknown'}`,
            href: `/dashboard/vendor-visits/${visit.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Review Visit - ${visit.application?.user?.name || 'Unknown'}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2, overflow: 'auto' }}>
                <Box sx={{ display: 'flex', itemsCenter: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/dashboard/vendor-visits">
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Back to Visits
                        </Link>
                    </Button>
                    {visit.application && (
                        <Button asChild>
                            <Link href={`/dashboard/vendor-applications/${visit.application.id}`}>
                                Go to Full Application
                            </Link>
                        </Button>
                    )}
                </Box>

                <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: '1fr 1fr' }, gap: 3 }}>
                    {/* Left: Field Agent Questionnaire Results */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle className="w-5 h-5 text-primary" />
                                    Field Agent Questionnaire
                                </CardTitle>
                                <CardDescription>
                                    Submitted by {visit.field_agent.name} on {visit.submitted_at ? new Date(visit.submitted_at).toLocaleString() : 'N/A'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2 }}>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Verified Ghana Card</Typography>
                                        <Typography sx={{ fontWeight: 500 }}>{visit.ghana_card_number}</Typography>
                                    </Box>
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Verified TIN</Typography>
                                        <Typography sx={{ fontWeight: 500 }}>{visit.tin_number || 'N/A'}</Typography>
                                    </Box>
                                </Box>

                                <Box sx={{ spaceY: 2, borderTop: 1, borderColor: 'divider', pt: 2 }}>
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                                        {visit.has_shop ? <Store className="w-4 h-4 text-blue-500" /> : <MapPin className="w-4 h-4 text-orange-500" />}
                                        <Typography sx={{ fontWeight: 600 }}>
                                            {visit.has_shop ? 'Physical Shop/Storefront' : 'Home-based / Online Only'}
                                        </Typography>
                                    </Box>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        {visit.has_shop ? 'Shop Location:' : 'Business Address:'}
                                    </Typography>
                                    <Typography sx={{ fontWeight: 500, fontStyle: 'italic' }}>
                                        {visit.has_shop ? visit.shop_location : visit.primary_business_address}
                                    </Typography>
                                </Box>

                                {visit.storefront_photo && (
                                    <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 2 }}>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary', mb: 1.5 }}>Storefront / Evidence Photo</Typography>
                                        <Box sx={{ borderRadius: 2, overflow: 'hidden', border: 1, borderColor: 'divider' }}>
                                            <img src={visit.storefront_photo} alt="Storefront" className="w-full h-auto object-cover max-h-[400px]" />
                                        </Box>
                                    </Box>
                                )}
                            </CardContent>
                        </Card>
                    </Box>

                    {/* Right: Original Application Data (Comparison) */}
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                        {visit.application ? (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="w-5 h-5" />
                                        Vendor Application Details
                                    </CardTitle>
                                    <CardDescription>
                                        Data provided by the vendor during registration
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <Box>
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>Business Name / Name</Typography>
                                        <Typography sx={{ fontWeight: 600, fontSize: '1.125rem' }}>{visit.application.user.name}</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{visit.application.user.email} • {visit.application.user.phone}</Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', gap: 1 }}>
                                        <Badge variant="outline">{visit.application.is_registered_vendor ? 'Registered' : 'Individual'}</Badge>
                                        <Badge variant="secondary">Status: {visit.application.status}</Badge>
                                    </Box>

                                    <Box sx={{ borderTop: 1, borderColor: 'divider', pt: 2, spaceY: 3 }}>
                                        <Typography sx={{ fontSize: '0.875rem', fontWeight: 600, display: 'flex', alignItems: 'center', gap: 1, mb: 2 }}>
                                            <IdCard className="w-4 h-4" />
                                            Uploaded Ghana Card Photos
                                        </Typography>
                                        <Box sx={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 2 }}>
                                            {visit.application.ghana_card_front && (
                                                <Box>
                                                    <Typography sx={{ fontSize: '0.625rem', textTransform: 'uppercase', color: 'text.secondary', mb: 0.5 }}>Front</Typography>
                                                    <Box sx={{ borderRadius: 1, border: 1, borderColor: 'divider', overflow: 'hidden' }}>
                                                        <img src={visit.application.ghana_card_front} alt="Card Front" className="w-full h-auto object-cover aspect-video" />
                                                    </Box>
                                                </Box>
                                            )}
                                            {visit.application.ghana_card_back && (
                                                <Box>
                                                    <Typography sx={{ fontSize: '0.625rem', textTransform: 'uppercase', color: 'text.secondary', mb: 0.5 }}>Back</Typography>
                                                    <Box sx={{ borderRadius: 1, border: 1, borderColor: 'divider', overflow: 'hidden' }}>
                                                        <img src={visit.application.ghana_card_back} alt="Card Back" className="w-full h-auto object-cover aspect-video" />
                                                    </Box>
                                                </Box>
                                            )}
                                        </Box>
                                    </Box>
                                </CardContent>
                            </Card>
                        ) : (
                            <Card className="bg-muted/50 border-dashed">
                                <CardHeader>
                                    <CardTitle>Missing Application</CardTitle>
                                    <CardDescription>
                                        This visit questionnaire is not linked to any active vendor application.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        This might happen if the original application was deleted or if the visit record was created for testing purposes.
                                    </Typography>
                                </CardContent>
                            </Card>
                        )}
                    </Box>
                </Box>
            </Box>
        </AppLayout>
    );
}
