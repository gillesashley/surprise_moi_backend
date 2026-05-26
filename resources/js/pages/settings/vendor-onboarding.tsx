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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { useState } from 'react';

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
    referral_bonus_customer_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_bonus_vendor_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_bonus_influencer_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_bonus_field_agent_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_bonus_employee_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_bonus_field_agent_team_pct?: {
        value: string;
        type: string;
        description: string;
    };
    vendor_onboarding_subsidy_pct?: {
        value: string;
        type: string;
        description: string;
    };
    referral_points_per_ghs?: {
        value: string;
        type: string;
        description: string;
    };
    referral_cashout_min_points?: {
        value: string;
        type: string;
        description: string;
    };
    payout_min_amount?: {
        value: string;
        type: string;
        description: string;
    };
    payout_max_amount?: {
        value: string;
        type: string;
        description: string;
    };
}

interface Props {
    settings: Settings;
}

const BONUS_CATEGORIES = [
    {
        key: 'referral_bonus_customer_pct',
        label: 'Customer',
        defaultValue: '15.00',
    },
    {
        key: 'referral_bonus_vendor_pct',
        label: 'Vendor',
        defaultValue: '20.00',
    },
    {
        key: 'referral_bonus_influencer_pct',
        label: 'Influencer',
        defaultValue: '25.00',
    },
    {
        key: 'referral_bonus_field_agent_pct',
        label: 'Field Agent',
        defaultValue: '30.00',
    },
    {
        key: 'referral_bonus_field_agent_team_pct',
        label: 'Field Agent (Team)',
        defaultValue: '35.00',
    },
    {
        key: 'referral_bonus_employee_pct',
        label: 'Employee',
        defaultValue: '20.00',
    },
] as const;

function ReferralBonusFields({
    settings,
    errors,
}: {
    settings: Settings;
    errors: Record<string, string>;
}) {
    const tier1Fee = parseFloat(
        settings.vendor_tier1_onboarding_fee?.value || '150',
    );
    const tier2Fee = parseFloat(
        settings.vendor_tier2_onboarding_fee?.value || '100',
    );
    const subsidyPct = parseFloat(
        settings.vendor_onboarding_subsidy_pct?.value || '25',
    );
    const postSubsidy = (fee: number) => fee * (1 - subsidyPct / 100);

    const [percentages, setPercentages] = useState<Record<string, string>>(
        () => {
            const initial: Record<string, string> = {};
            for (const cat of BONUS_CATEGORIES) {
                initial[cat.key] =
                    settings[cat.key as keyof Settings]?.value ||
                    cat.defaultValue;
            }
            return initial;
        },
    );

    const computeBonus = (pctStr: string, fee: number) => {
        const pct = parseFloat(pctStr);
        if (isNaN(pct) || isNaN(fee)) return '0.00';
        return ((pct / 100) * fee).toFixed(2);
    };

    return (
        <Box
            sx={{
                display: 'grid',
                gap: 2,
                gridTemplateColumns: { md: 'repeat(3, 1fr)' },
            }}
        >
            {BONUS_CATEGORIES.map((cat) => (
                <Box key={cat.key} sx={{ display: 'grid', gap: 1 }}>
                    <Label htmlFor={cat.key}>{cat.label} (%)</Label>
                    <Input
                        id={cat.key}
                        name={cat.key}
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        value={percentages[cat.key]}
                        onChange={(e) =>
                            setPercentages((prev) => ({
                                ...prev,
                                [cat.key]: e.target.value,
                            }))
                        }
                        required
                    />
                    <Typography variant="body2" color="text.secondary">
                        Tier 1 post-subsidy: GH₵
                        {postSubsidy(tier1Fee).toFixed(2)} → {cat.label} earns
                        GH₵
                        {computeBonus(
                            percentages[cat.key],
                            postSubsidy(tier1Fee),
                        )}{' '}
                        | Tier 2 post-subsidy: GH₵
                        {postSubsidy(tier2Fee).toFixed(2)} → earns GH₵
                        {computeBonus(
                            percentages[cat.key],
                            postSubsidy(tier2Fee),
                        )}
                    </Typography>
                    <InputError message={errors[cat.key]} />
                </Box>
            ))}
        </Box>
    );
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
                        style={{
                            display: 'flex',
                            flexDirection: 'column',
                            gap: 24,
                        }}
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
                                        <Box
                                            sx={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 2,
                                            }}
                                        >
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
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

                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
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

                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
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

                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
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
                                        <Box
                                            sx={{
                                                display: 'flex',
                                                flexDirection: 'column',
                                                gap: 2,
                                            }}
                                        >
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
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

                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
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

                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
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

                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
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

                                {/* Vendor Subsidy Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Vendor Subsidy</CardTitle>
                                        <CardDescription>
                                            The discount applied to a vendor's
                                            onboarding fee when they onboard
                                            using a valid referral code. Applies
                                            identically to Tier 1 and Tier 2.
                                            Example: at 25%, a Tier 1 vendor
                                            with a 200 GHS fee pays 150 GHS.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Box sx={{ display: 'grid', gap: 1 }}>
                                            <Label htmlFor="vendor_onboarding_subsidy_pct">
                                                Subsidy (%)
                                            </Label>
                                            <Input
                                                id="vendor_onboarding_subsidy_pct"
                                                name="vendor_onboarding_subsidy_pct"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                defaultValue={
                                                    settings
                                                        .vendor_onboarding_subsidy_pct
                                                        ?.value || '25.00'
                                                }
                                                required
                                            />
                                            <InputError
                                                message={
                                                    errors.vendor_onboarding_subsidy_pct
                                                }
                                            />
                                        </Box>
                                    </CardContent>
                                </Card>

                                {/* Referral Bonus Percentages */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Referral Bonus Percentages
                                        </CardTitle>
                                        <CardDescription>
                                            The share of the amount a vendor
                                            actually paid (after the subsidy)
                                            that each role earns as points when
                                            their referral code is used.
                                            Example: if Customer = 15% and the
                                            vendor paid GH₵150, a Customer
                                            referrer earns GH₵22.50, shown as
                                            225 points at the current conversion
                                            rate.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <ReferralBonusFields
                                            settings={settings}
                                            errors={errors}
                                        />
                                    </CardContent>
                                </Card>

                                {/* Referral Points System Card */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>
                                            Referral Points System
                                        </CardTitle>
                                        <CardDescription>
                                            Controls how referrer rewards
                                            display and when referrers can cash
                                            out. A referrer earning GH₵15 sees
                                            150 points at a 10-per-GHS rate, and
                                            can cash out once they reach 1000
                                            points (GH₵100).
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Box
                                            sx={{
                                                display: 'grid',
                                                gap: 2,
                                                gridTemplateColumns: {
                                                    md: 'repeat(2, 1fr)',
                                                },
                                            }}
                                        >
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
                                                <Label htmlFor="referral_points_per_ghs">
                                                    Points per GHS
                                                </Label>
                                                <Input
                                                    id="referral_points_per_ghs"
                                                    name="referral_points_per_ghs"
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    defaultValue={
                                                        settings
                                                            .referral_points_per_ghs
                                                            ?.value || '10'
                                                    }
                                                    required
                                                />
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    How many points a referrer
                                                    sees per 1 GHS earned.
                                                </Typography>
                                                <InputError
                                                    message={
                                                        errors.referral_points_per_ghs
                                                    }
                                                />
                                            </Box>
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
                                                <Label htmlFor="referral_cashout_min_points">
                                                    Minimum points to cash out
                                                </Label>
                                                <Input
                                                    id="referral_cashout_min_points"
                                                    name="referral_cashout_min_points"
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    defaultValue={
                                                        settings
                                                            .referral_cashout_min_points
                                                            ?.value || '1000'
                                                    }
                                                    required
                                                />
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    The lowest balance at which
                                                    a referrer can request a
                                                    withdrawal.
                                                </Typography>
                                                <InputError
                                                    message={
                                                        errors.referral_cashout_min_points
                                                    }
                                                />
                                            </Box>
                                        </Box>
                                    </CardContent>
                                </Card>

                                {/* Payout Amount Settings */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Payout Limits</CardTitle>
                                        <CardDescription>
                                            Minimum and maximum amounts a vendor
                                            can request for a single payout.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Box
                                            sx={{
                                                display: 'grid',
                                                gap: 2,
                                                gridTemplateColumns: {
                                                    md: 'repeat(2, 1fr)',
                                                },
                                            }}
                                        >
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
                                                <Label htmlFor="payout_min_amount">
                                                    Minimum Payout (GHS)
                                                </Label>
                                                <Input
                                                    id="payout_min_amount"
                                                    name="payout_min_amount"
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    defaultValue={
                                                        settings
                                                            .payout_min_amount
                                                            ?.value || '50'
                                                    }
                                                    required
                                                />
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    {settings.payout_min_amount
                                                        ?.description ||
                                                        'Minimum amount a vendor can request'}
                                                </Typography>
                                                <InputError
                                                    message={
                                                        errors.payout_min_amount
                                                    }
                                                />
                                            </Box>
                                            <Box
                                                sx={{ display: 'grid', gap: 1 }}
                                            >
                                                <Label htmlFor="payout_max_amount">
                                                    Maximum Payout (GHS)
                                                </Label>
                                                <Input
                                                    id="payout_max_amount"
                                                    name="payout_max_amount"
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    defaultValue={
                                                        settings
                                                            .payout_max_amount
                                                            ?.value || '10000'
                                                    }
                                                    required
                                                />
                                                <Typography
                                                    variant="body2"
                                                    color="text.secondary"
                                                >
                                                    {settings.payout_max_amount
                                                        ?.description ||
                                                        'Maximum amount a vendor can request'}
                                                </Typography>
                                                <InputError
                                                    message={
                                                        errors.payout_max_amount
                                                    }
                                                />
                                            </Box>
                                        </Box>
                                    </CardContent>
                                </Card>

                                <Box
                                    sx={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 2,
                                    }}
                                >
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Saving...' : 'Save'}
                                    </Button>

                                    {recentlySuccessful && (
                                        <Typography
                                            variant="body2"
                                            color="text.secondary"
                                        >
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
