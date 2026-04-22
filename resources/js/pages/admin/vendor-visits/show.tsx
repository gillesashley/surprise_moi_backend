import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
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
            <div className="flex h-full flex-1 flex-col gap-4 p-4 overflow-auto">
                <div className="flex items-center justify-between">
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
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Left: Field Agent Questionnaire Results */}
                    <div className="space-y-6">
                        <Card className="border-primary/20">
                            <CardHeader className="bg-primary/5">
                                <CardTitle className="flex items-center gap-2">
                                    <CheckCircle className="w-5 h-5 text-primary" />
                                    Field Agent Questionnaire
                                </CardTitle>
                                <CardDescription>
                                    Submitted by {visit.field_agent.name} on {visit.submitted_at ? new Date(visit.submitted_at).toLocaleString() : 'N/A'}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="pt-6 space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Verified Ghana Card</p>
                                        <p className="font-medium">{visit.ghana_card_number}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Verified TIN</p>
                                        <p className="font-medium">{visit.tin_number || 'N/A'}</p>
                                    </div>
                                </div>

                                <div className="space-y-2 border-t pt-4">
                                    <div className="flex items-center gap-2">
                                        {visit.has_shop ? <Store className="w-4 h-4 text-blue-500" /> : <MapPin className="w-4 h-4 text-orange-500" />}
                                        <span className="font-semibold">
                                            {visit.has_shop ? 'Physical Shop/Storefront' : 'Home-based / Online Only'}
                                        </span>
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        {visit.has_shop ? 'Shop Location:' : 'Business Address:'}
                                    </p>
                                    <p className="font-medium italic">
                                        {visit.has_shop ? visit.shop_location : visit.primary_business_address}
                                    </p>
                                </div>

                                {visit.storefront_photo && (
                                    <div className="border-t pt-4">
                                        <p className="text-sm text-muted-foreground mb-2">Storefront / Evidence Photo</p>
                                        <div className="rounded-lg overflow-hidden border">
                                            <img src={visit.storefront_photo} alt="Storefront" className="w-full h-auto object-cover max-h-[300px]" />
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right: Original Application Data (Comparison) */}
                    <div className="space-y-6">
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
                                    <div>
                                        <p className="text-sm text-muted-foreground">Business Name / Name</p>
                                        <p className="font-medium text-lg">{visit.application.user.name}</p>
                                        <p className="text-sm">{visit.application.user.email} • {visit.application.user.phone}</p>
                                    </div>

                                    <div className="flex gap-2">
                                        <Badge variant="outline">{visit.application.is_registered_vendor ? 'Registered' : 'Individual'}</Badge>
                                        <Badge variant="secondary">Status: {visit.application.status}</Badge>
                                    </div>

                                    <div className="border-t pt-4 space-y-4">
                                        <p className="text-sm font-semibold flex items-center gap-2">
                                            <IdCard className="w-4 h-4" />
                                            Uploaded Ghana Card Photos
                                        </p>
                                        <div className="grid grid-cols-2 gap-4">
                                            {visit.application.ghana_card_front && (
                                                <div className="space-y-1">
                                                    <p className="text-[10px] uppercase text-muted-foreground">Front</p>
                                                    <img src={visit.application.ghana_card_front} alt="Card Front" className="rounded border w-full h-auto object-cover aspect-video" />
                                                </div>
                                            )}
                                            {visit.application.ghana_card_back && (
                                                <div className="space-y-1">
                                                    <p className="text-[10px] uppercase text-muted-foreground">Back</p>
                                                    <img src={visit.application.ghana_card_back} alt="Card Back" className="rounded border w-full h-auto object-cover aspect-video" />
                                                </div>
                                            )}
                                        </div>
                                    </div>
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
                                    <p className="text-sm text-muted-foreground">
                                        This might happen if the original application was deleted or if the visit record was created for testing purposes.
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
