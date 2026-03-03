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
import { type BreadcrumbItem } from '@/types';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Form, Head } from '@inertiajs/react';

interface Settings {
    vendor_onboarding_fee: {
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
        title: 'Settings',
        href: '/dashboard/settings',
    },
];

export default function SettingsIndex({ settings }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="System Settings" />

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box>
                    <Typography variant="h4" fontWeight={700}>System Settings</Typography>
                    <Typography variant="body2" color="text.secondary">
                        Manage application-wide settings
                    </Typography>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Vendor Onboarding</CardTitle>
                        <CardDescription>
                            Configure vendor registration settings
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/dashboard/settings"
                            method="post"
                            style={{ display: 'flex', flexDirection: 'column', gap: 24 }}
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="vendor_onboarding_fee">
                                            Vendor Onboarding Fee (GHS) *
                                        </Label>
                                        <Input
                                            id="vendor_onboarding_fee"
                                            name="vendor_onboarding_fee"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            defaultValue={
                                                settings.vendor_onboarding_fee
                                                    .value
                                            }
                                            required
                                        />
                                        <Typography variant="body2" color="text.secondary">
                                            {
                                                settings.vendor_onboarding_fee
                                                    .description
                                            }
                                        </Typography>
                                        {errors.vendor_onboarding_fee && (
                                            <Typography variant="body2" color="error.main">
                                                {errors.vendor_onboarding_fee}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', justifyContent: 'flex-end' }}>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Save Settings'}
                                        </Button>
                                    </Box>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
