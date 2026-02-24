import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { index } from '@/routes/settings/vendor-onboarding';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/react';

interface Settings {
    vendor_tier1_onboarding_fee?: {
        value: string;
        type: string;
        description: string;
    };
    vendor_tier2_onboarding_fee?: {
        value: string;
        type: string;
        description: string;
    };
    vendor_tier1_commission_rate?: {
        value: string;
        type: string;
        description: string;
    };
    vendor_tier2_commission_rate?: {
        value: string;
        type: string;
        description: string;
    };
}

interface Props {
    settings: Settings;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Vendor onboarding settings',
        href: index().url,
    },
];

export default function VendorOnboarding({ settings }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Vendor onboarding settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Vendor onboarding settings"
                        description="Configure vendor tier fees and commission rates"
                    />

                    <Form
                        action="/settings/vendor-onboarding"
                        method="post"
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                {/* Tier 1 Settings Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Tier 1 Vendors (With Business
                                            Certificates)
                                        </CardTitle>
                                        <CardDescription>
                                            Premium vendors with verified
                                            business documentation
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_tier1_onboarding_fee">
                                                Onboarding Fee (GHS)
                                            </Label>

                                            <Input
                                                id="vendor_tier1_onboarding_fee"
                                                name="vendor_tier1_onboarding_fee"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="mt-1 block w-full"
                                                defaultValue={
                                                    settings
                                                        .vendor_tier1_onboarding_fee
                                                        ?.value || '150.00'
                                                }
                                                required
                                            />

                                            <p className="text-sm text-muted-foreground">
                                                {settings
                                                    .vendor_tier1_onboarding_fee
                                                    ?.description ||
                                                    'Tier 1 vendor onboarding fee'}
                                            </p>

                                            <InputError
                                                className="mt-2"
                                                message={
                                                    errors.vendor_tier1_onboarding_fee
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_tier1_commission_rate">
                                                Commission Rate (%)
                                            </Label>

                                            <Input
                                                id="vendor_tier1_commission_rate"
                                                name="vendor_tier1_commission_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                className="mt-1 block w-full"
                                                defaultValue={
                                                    settings
                                                        .vendor_tier1_commission_rate
                                                        ?.value || '12.00'
                                                }
                                                required
                                            />

                                            <p className="text-sm text-muted-foreground">
                                                {settings
                                                    .vendor_tier1_commission_rate
                                                    ?.description ||
                                                    'Tier 1 vendor commission rate percentage'}
                                            </p>

                                            <InputError
                                                className="mt-2"
                                                message={
                                                    errors.vendor_tier1_commission_rate
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Tier 2 Settings Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Tier 2 Vendors (Without Business
                                            Certificates)
                                        </CardTitle>
                                        <CardDescription>
                                            Basic vendors without verified
                                            business documentation
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_tier2_onboarding_fee">
                                                Onboarding Fee (GHS)
                                            </Label>

                                            <Input
                                                id="vendor_tier2_onboarding_fee"
                                                name="vendor_tier2_onboarding_fee"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                className="mt-1 block w-full"
                                                defaultValue={
                                                    settings
                                                        .vendor_tier2_onboarding_fee
                                                        ?.value || '100.00'
                                                }
                                                required
                                            />

                                            <p className="text-sm text-muted-foreground">
                                                {settings
                                                    .vendor_tier2_onboarding_fee
                                                    ?.description ||
                                                    'Tier 2 vendor onboarding fee'}
                                            </p>

                                            <InputError
                                                className="mt-2"
                                                message={
                                                    errors.vendor_tier2_onboarding_fee
                                                }
                                            />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_tier2_commission_rate">
                                                Commission Rate (%)
                                            </Label>

                                            <Input
                                                id="vendor_tier2_commission_rate"
                                                name="vendor_tier2_commission_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                className="mt-1 block w-full"
                                                defaultValue={
                                                    settings
                                                        .vendor_tier2_commission_rate
                                                        ?.value || '8.00'
                                                }
                                                required
                                            />

                                            <p className="text-sm text-muted-foreground">
                                                {settings
                                                    .vendor_tier2_commission_rate
                                                    ?.description ||
                                                    'Tier 2 vendor commission rate percentage'}
                                            </p>

                                            <InputError
                                                className="mt-2"
                                                message={
                                                    errors.vendor_tier2_commission_rate
                                                }
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                <div className="flex items-center gap-4">
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save'}
                                    </Button>

                                    {recentlySuccessful && (
                                        <p className="text-sm text-muted-foreground">
                                            Saved.
                                        </p>
                                    )}
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
