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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
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
                        style={{ display: 'flex', flexDirection: 'column', gap: 24 }}
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
                                    <CardContent>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                            <Box sx={{ display: 'grid', gap: 1 }}>
                                                <Label htmlFor="vendor_tier1_onboarding_fee">
                                                    Onboarding Fee (GHS)
                                                </Label>

                                                <Input
                                                    id="vendor_tier1_onboarding_fee"
                                                    name="vendor_tier1_onboarding_fee"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    defaultValue={
                                                        settings
                                                            .vendor_tier1_onboarding_fee
                                                            ?.value || '150.00'
                                                    }
                                                    required
                                                />

                                                <Typography variant="body2" color="text.secondary">
                                                    {settings
                                                        .vendor_tier1_onboarding_fee
                                                        ?.description ||
                                                        'Tier 1 vendor onboarding fee'}
                                                </Typography>

                                                <InputError
                                                    message={
                                                        errors.vendor_tier1_onboarding_fee
                                                    }
                                                />
                                            </Box>

                                            <Box sx={{ display: 'grid', gap: 1 }}>
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
                                                    defaultValue={
                                                        settings
                                                            .vendor_tier1_commission_rate
                                                            ?.value || '12.00'
                                                    }
                                                    required
                                                />

                                                <Typography variant="body2" color="text.secondary">
                                                    {settings
                                                        .vendor_tier1_commission_rate
                                                        ?.description ||
                                                        'Tier 1 vendor commission rate percentage'}
                                                </Typography>

                                                <InputError
                                                    message={
                                                        errors.vendor_tier1_commission_rate
                                                    }
                                                />
                                            </Box>
                                        </Box>
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
                                    <CardContent>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                            <Box sx={{ display: 'grid', gap: 1 }}>
                                                <Label htmlFor="vendor_tier2_onboarding_fee">
                                                    Onboarding Fee (GHS)
                                                </Label>

                                                <Input
                                                    id="vendor_tier2_onboarding_fee"
                                                    name="vendor_tier2_onboarding_fee"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    defaultValue={
                                                        settings
                                                            .vendor_tier2_onboarding_fee
                                                            ?.value || '100.00'
                                                    }
                                                    required
                                                />

                                                <Typography variant="body2" color="text.secondary">
                                                    {settings
                                                        .vendor_tier2_onboarding_fee
                                                        ?.description ||
                                                        'Tier 2 vendor onboarding fee'}
                                                </Typography>

                                                <InputError
                                                    message={
                                                        errors.vendor_tier2_onboarding_fee
                                                    }
                                                />
                                            </Box>

                                            <Box sx={{ display: 'grid', gap: 1 }}>
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
                                                    defaultValue={
                                                        settings
                                                            .vendor_tier2_commission_rate
                                                            ?.value || '8.00'
                                                    }
                                                    required
                                                />

                                                <Typography variant="body2" color="text.secondary">
                                                    {settings
                                                        .vendor_tier2_commission_rate
                                                        ?.description ||
                                                        'Tier 2 vendor commission rate percentage'}
                                                </Typography>

                                                <InputError
                                                    message={
                                                        errors.vendor_tier2_commission_rate
                                                    }
                                                />
                                            </Box>
                                        </Box>
                                    </CardContent>
                                </Card>

                                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save'}
                                    </Button>

                                    {recentlySuccessful && (
                                        <Typography variant="body2" color="text.secondary">
                                            Saved.
                                        </Typography>
                                    )}
                                </Box>
                            </>
                        )}
                    </Form>
                </Box>
            </SettingsLayout>
        </AppLayout>
    );
}
