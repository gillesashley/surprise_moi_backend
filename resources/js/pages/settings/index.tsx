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

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-3xl font-bold">System Settings</h1>
                    <p className="text-muted-foreground">
                        Manage application-wide settings
                    </p>
                </div>

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
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
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
                                        <p className="text-sm text-muted-foreground">
                                            {
                                                settings.vendor_onboarding_fee
                                                    .description
                                            }
                                        </p>
                                        {errors.vendor_onboarding_fee && (
                                            <p className="text-sm text-red-600">
                                                {errors.vendor_onboarding_fee}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Saving...'
                                                : 'Save Settings'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
