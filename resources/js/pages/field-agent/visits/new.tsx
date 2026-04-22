import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import FormControlLabel from '@mui/material/FormControlLabel';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';

interface Visit {
    id: string;
    status: string;
    ghana_card_number: string | null;
    tin_number: string | null;
    has_shop: boolean;
    shop_location: string | null;
    primary_business_address: string | null;
    storefront_photo_path: string | null;
    vendor_application: {
        id: number;
        user: { id: number; business_name: string | null; name: string };
    };
}

interface Props {
    visit: Visit;
}

export default function VisitForm({ visit }: Props) {
    const isTerminal = visit.status !== 'draft';

    const submit = useForm({
        ghana_card_number: visit.ghana_card_number ?? '',
        tin_number: visit.tin_number ?? '',
        has_shop: visit.has_shop ? 'true' : 'false',
        shop_location: visit.shop_location ?? '',
        primary_business_address: visit.primary_business_address ?? '',
        storefront_photo: null as File | null,
    });

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Inertia needs has_shop to be a real boolean when posting
        submit.transform((data) => ({
            ...data,
            has_shop: data.has_shop === 'true',
        }));

        submit.post(`/field-agent/visits/forms/${visit.id}/submit`, {
            forceFormData: true,
        });
    };

    const vendorLabel =
        visit.vendor_application?.user?.business_name ??
        visit.vendor_application?.user?.name ??
        'Vendor';

    const hasShop = submit.data.has_shop === 'true';

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Vendor Onboarding', href: '/field-agent/visits' },
                { title: vendorLabel, href: '#' },
                {
                    title: 'Questionnaire',
                    href: `/field-agent/visits/forms/${visit.id}`,
                },
            ]}
        >
            <Head title="Questionnaire Form" />

            <Box
                component="form"
                onSubmit={onSubmit}
                sx={{
                    display: 'flex',
                    flex: 1,
                    flexDirection: 'column',
                    gap: 3,
                    p: 3,
                    maxWidth: 720,
                    mx: 'auto',
                    width: '100%',
                }}
            >
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        Questionnaire for {vendorLabel}
                    </Typography>
                    <Typography
                        variant="body2"
                        color="text.secondary"
                        sx={{ mt: 0.5 }}
                    >
                        Please verify the vendor's details in person and answer
                        the questions below.
                    </Typography>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Identity & Registration</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Stack spacing={2}>
                            <TextField
                                label="Ghana Card Number"
                                required
                                fullWidth
                                disabled={isTerminal}
                                value={submit.data.ghana_card_number}
                                onChange={(e) =>
                                    submit.setData(
                                        'ghana_card_number',
                                        e.target.value,
                                    )
                                }
                                error={!!submit.errors.ghana_card_number}
                                helperText={submit.errors.ghana_card_number}
                            />
                            <TextField
                                label="TIN (Tax Identification Number)"
                                fullWidth
                                disabled={isTerminal}
                                value={submit.data.tin_number}
                                onChange={(e) =>
                                    submit.setData('tin_number', e.target.value)
                                }
                                helperText={
                                    submit.errors.tin_number ??
                                    'Required if this is a registered business'
                                }
                                error={!!submit.errors.tin_number}
                            />
                        </Stack>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Physical Location</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Stack spacing={2}>
                            <Box>
                                <Typography variant="body2" sx={{ mb: 1 }}>
                                    Does the vendor have a physical shop?
                                </Typography>
                                <RadioGroup
                                    row
                                    value={submit.data.has_shop}
                                    onChange={(e) =>
                                        submit.setData(
                                            'has_shop',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <FormControlLabel
                                        value="true"
                                        control={
                                            <Radio disabled={isTerminal} />
                                        }
                                        label="Yes"
                                    />
                                    <FormControlLabel
                                        value="false"
                                        control={
                                            <Radio disabled={isTerminal} />
                                        }
                                        label="No"
                                    />
                                </RadioGroup>
                            </Box>

                            {hasShop ? (
                                <>
                                    <TextField
                                        label="Shop Location"
                                        required
                                        fullWidth
                                        disabled={isTerminal}
                                        value={submit.data.shop_location}
                                        onChange={(e) =>
                                            submit.setData(
                                                'shop_location',
                                                e.target.value,
                                            )
                                        }
                                        error={!!submit.errors.shop_location}
                                        helperText={submit.errors.shop_location}
                                    />
                                    <Box>
                                        <Typography
                                            variant="body2"
                                            sx={{ mb: 0.5 }}
                                        >
                                            Upload photographic evidence of the
                                            shop{' '}
                                            {(visit.storefront_photo_path ||
                                                submit.data.storefront_photo) &&
                                                '✓'}
                                        </Typography>
                                        <input
                                            type="file"
                                            accept="image/*"
                                            capture="environment"
                                            disabled={isTerminal}
                                            required={
                                                !visit.storefront_photo_path
                                            }
                                            onChange={(e) =>
                                                submit.setData(
                                                    'storefront_photo',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                        {submit.errors.storefront_photo && (
                                            <Typography
                                                variant="caption"
                                                color="error"
                                            >
                                                {submit.errors.storefront_photo}
                                            </Typography>
                                        )}
                                    </Box>
                                </>
                            ) : (
                                <TextField
                                    label="Primary Business Address / Location"
                                    required
                                    fullWidth
                                    disabled={isTerminal}
                                    value={submit.data.primary_business_address}
                                    onChange={(e) =>
                                        submit.setData(
                                            'primary_business_address',
                                            e.target.value,
                                        )
                                    }
                                    error={
                                        !!submit.errors.primary_business_address
                                    }
                                    helperText={
                                        submit.errors.primary_business_address
                                    }
                                />
                            )}
                        </Stack>
                    </CardContent>
                </Card>

                <Button
                    type="submit"
                    size="lg"
                    disabled={submit.processing || isTerminal}
                    sx={{ py: 1.5 }}
                >
                    {isTerminal
                        ? `Questionnaire ${visit.status}`
                        : submit.processing
                          ? 'Submitting…'
                          : 'Submit Questionnaire'}
                </Button>
            </Box>
        </AppLayout>
    );
}
