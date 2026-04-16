import AppLogoIcon from '@/components/app-logo-icon';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { login, home } from '@/routes';
import { Head, Link, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Step from '@mui/material/Step';
import StepLabel from '@mui/material/StepLabel';
import Stepper from '@mui/material/Stepper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { FormEvent, useMemo, useState } from 'react';

type City = { id: number; name: string; slug: string };
type Region = { id: number; name: string; slug: string; cities: City[] };

type Props = { regions: Region[] };

type WizardData = {
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    password: string;
    password_confirmation: string;
    region_id: string;
    city_id: string;
    location: string;
    ghana_card_number: string;
    ghana_card_image: File | null;
    ghana_card_back_image: File | null;
    selfie: File | null;
    website: string;
};

const STEPS = ['Personal', 'Location', 'Identity', 'Review'] as const;

export default function FieldAgentRegister({ regions }: Props) {
    const [step, setStep] = useState<number>(0);

    const { data, setData, post, processing, errors } = useForm<WizardData>({
        first_name: '',
        last_name: '',
        email: '',
        contact_number: '',
        password: '',
        password_confirmation: '',
        region_id: '',
        city_id: '',
        location: '',
        ghana_card_number: '',
        ghana_card_image: null,
        ghana_card_back_image: null,
        selfie: null,
        website: '',
    });

    const selectedRegion = useMemo(
        () => regions.find((r) => String(r.id) === data.region_id),
        [regions, data.region_id],
    );

    const canProceed = (): boolean => {
        if (step === 0) {
            return Boolean(
                data.first_name &&
                    data.last_name &&
                    data.email &&
                    data.contact_number &&
                    data.password &&
                    data.password === data.password_confirmation,
            );
        }
        if (step === 1) {
            return Boolean(data.region_id && data.city_id && data.location);
        }
        if (step === 2) {
            return Boolean(data.ghana_card_number && data.ghana_card_image && data.ghana_card_back_image && data.selfie);
        }
        return true;
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/field-agents/register', { forceFormData: true });
    };

    return (
        <>
            <Head title="Become a field agent" />
            <Box
                sx={{
                    display: 'flex',
                    minHeight: '100svh',
                    flexDirection: 'column',
                    alignItems: 'center',
                    justifyContent: 'center',
                    bgcolor: 'background.default',
                    p: { xs: 2, md: 5 },
                }}
            >
                <Box sx={{ width: '100%', maxWidth: '34rem' }}>
                    <Box
                        sx={{
                            bgcolor: 'background.paper',
                            borderRadius: 3,
                            boxShadow: (theme) => theme.shadows[2],
                            p: { xs: 3, md: 4 },
                        }}
                    >
                        <Stack spacing={1.5} sx={{ alignItems: 'center', textAlign: 'center' }}>
                            <Link
                                href={home()}
                                style={{
                                    display: 'inline-flex',
                                    alignItems: 'center',
                                    justifyContent: 'center',
                                    color: 'inherit',
                                    textDecoration: 'none',
                                }}
                            >
                                <AppLogoIcon style={{ width: 40, height: 40 }} />
                            </Link>
                            <Typography variant="h5" sx={{ fontWeight: 600 }}>
                                Become a Field Agent
                            </Typography>
                            <Typography variant="body2" color="text.secondary">
                                Step {step + 1} of {STEPS.length} — {STEPS[step]}
                            </Typography>
                        </Stack>

                        <Stepper activeStep={step} alternativeLabel sx={{ mt: 3, mb: 4 }}>
                            {STEPS.map((label) => (
                                <Step key={label}>
                                    <StepLabel>{label}</StepLabel>
                                </Step>
                            ))}
                        </Stepper>

                        <form onSubmit={submit} encType="multipart/form-data" noValidate>
                            <input
                                type="text"
                                name="website"
                                value={data.website}
                                onChange={(e) => setData('website', e.target.value)}
                                style={{ display: 'none' }}
                                tabIndex={-1}
                                autoComplete="off"
                                aria-hidden="true"
                            />

                            {step === 0 && (
                                <Stack spacing={2.25}>
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            gap: 2.25,
                                            gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                        }}
                                    >
                                        <Stack spacing={1}>
                                            <Label htmlFor="first_name">First name</Label>
                                            <Input
                                                id="first_name"
                                                autoComplete="given-name"
                                                value={data.first_name}
                                                onChange={(e) => setData('first_name', e.target.value)}
                                            />
                                            <InputError message={errors.first_name} />
                                        </Stack>
                                        <Stack spacing={1}>
                                            <Label htmlFor="last_name">Last name</Label>
                                            <Input
                                                id="last_name"
                                                autoComplete="family-name"
                                                value={data.last_name}
                                                onChange={(e) => setData('last_name', e.target.value)}
                                            />
                                            <InputError message={errors.last_name} />
                                        </Stack>
                                    </Box>
                                    <Stack spacing={1}>
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            autoComplete="email"
                                            placeholder="you@example.com"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                        />
                                        <InputError message={errors.email} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label htmlFor="contact_number">Contact number</Label>
                                        <Input
                                            id="contact_number"
                                            autoComplete="tel"
                                            placeholder="0551234567"
                                            value={data.contact_number}
                                            onChange={(e) => setData('contact_number', e.target.value)}
                                        />
                                        <InputError message={errors.contact_number} />
                                    </Stack>
                                    <Box
                                        sx={{
                                            display: 'grid',
                                            gap: 2.25,
                                            gridTemplateColumns: { xs: '1fr', sm: '1fr 1fr' },
                                        }}
                                    >
                                        <Stack spacing={1}>
                                            <Label htmlFor="password">Password</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                autoComplete="new-password"
                                                value={data.password}
                                                onChange={(e) => setData('password', e.target.value)}
                                            />
                                            <InputError message={errors.password} />
                                        </Stack>
                                        <Stack spacing={1}>
                                            <Label htmlFor="password_confirmation">Confirm password</Label>
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                autoComplete="new-password"
                                                value={data.password_confirmation}
                                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                            />
                                        </Stack>
                                    </Box>
                                </Stack>
                            )}

                            {step === 1 && (
                                <Stack spacing={2.25}>
                                    <Stack spacing={1}>
                                        <Label>Region</Label>
                                        <Select
                                            value={data.region_id}
                                            onValueChange={(v) => {
                                                setData('region_id', v);
                                                setData('city_id', '');
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select a region" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {regions.map((r) => (
                                                    <SelectItem key={r.id} value={String(r.id)}>
                                                        {r.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.region_id} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label>City</Label>
                                        <Select
                                            value={data.city_id}
                                            onValueChange={(v) => setData('city_id', v)}
                                            disabled={!selectedRegion}
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder={selectedRegion ? 'Select a city' : 'Pick a region first'}
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {selectedRegion?.cities.map((c) => (
                                                    <SelectItem key={c.id} value={String(c.id)}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.city_id} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label htmlFor="location">Location / landmark</Label>
                                        <Input
                                            id="location"
                                            placeholder="e.g. Osu, near Koala"
                                            value={data.location}
                                            onChange={(e) => setData('location', e.target.value)}
                                        />
                                        <InputError message={errors.location} />
                                    </Stack>
                                </Stack>
                            )}

                            {step === 2 && (
                                <Stack spacing={2.25}>
                                    <Stack spacing={1}>
                                        <Label htmlFor="ghana_card_number">Ghana card number</Label>
                                        <Input
                                            id="ghana_card_number"
                                            placeholder="GHA-123456789-1"
                                            value={data.ghana_card_number}
                                            onChange={(e) => setData('ghana_card_number', e.target.value)}
                                        />
                                        <InputError message={errors.ghana_card_number} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label htmlFor="ghana_card_image">Ghana card — front (max 5MB)</Label>
                                        <Input
                                            id="ghana_card_image"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(e) =>
                                                setData('ghana_card_image', e.target.files?.[0] ?? null)
                                            }
                                        />
                                        <InputError message={errors.ghana_card_image} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label htmlFor="ghana_card_back_image">Ghana card — back (max 5MB)</Label>
                                        <Input
                                            id="ghana_card_back_image"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(e) =>
                                                setData('ghana_card_back_image', e.target.files?.[0] ?? null)
                                            }
                                        />
                                        <InputError message={errors.ghana_card_back_image} />
                                    </Stack>
                                    <Stack spacing={1}>
                                        <Label htmlFor="selfie">Selfie (max 5MB)</Label>
                                        <Input
                                            id="selfie"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp"
                                            onChange={(e) => setData('selfie', e.target.files?.[0] ?? null)}
                                        />
                                        <InputError message={errors.selfie} />
                                    </Stack>
                                </Stack>
                            )}

                            {step === 3 && (
                                <Stack spacing={2}>
                                    <Typography variant="subtitle1" sx={{ fontWeight: 600 }}>
                                        Review your details
                                    </Typography>
                                    <Box
                                        sx={{
                                            borderRadius: 2,
                                            border: 1,
                                            borderColor: 'divider',
                                            p: 2.5,
                                            display: 'grid',
                                            rowGap: 1.25,
                                            columnGap: 2,
                                            gridTemplateColumns: { xs: '1fr', sm: 'auto 1fr' },
                                            fontSize: 14,
                                        }}
                                    >
                                        <ReviewRow label="Name" value={`${data.first_name} ${data.last_name}`} />
                                        <ReviewRow label="Email" value={data.email} />
                                        <ReviewRow label="Contact" value={data.contact_number} />
                                        <ReviewRow label="Region" value={selectedRegion?.name ?? '—'} />
                                        <ReviewRow
                                            label="City"
                                            value={selectedRegion?.cities.find((c) => String(c.id) === data.city_id)?.name ?? '—'}
                                        />
                                        <ReviewRow label="Location" value={data.location} />
                                        <ReviewRow label="Ghana card" value={data.ghana_card_number} />
                                        <ReviewRow label="Ghana card — front" value={data.ghana_card_image?.name ?? '—'} />
                                        <ReviewRow label="Ghana card — back" value={data.ghana_card_back_image?.name ?? '—'} />
                                        <ReviewRow label="Selfie" value={data.selfie?.name ?? '—'} />
                                    </Box>
                                </Stack>
                            )}

                            <Box sx={{ mt: 4, display: 'flex', justifyContent: 'space-between', gap: 2 }}>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={step === 0 || processing}
                                    onClick={() => setStep(step - 1)}
                                >
                                    Back
                                </Button>
                                {step < STEPS.length - 1 ? (
                                    <Button
                                        type="button"
                                        disabled={!canProceed() || processing}
                                        onClick={() => setStep(step + 1)}
                                    >
                                        Next
                                    </Button>
                                ) : (
                                    <Button type="submit" disabled={processing}>
                                        Submit application
                                    </Button>
                                )}
                            </Box>
                        </form>
                    </Box>

                    <Typography variant="body2" color="text.secondary" sx={{ textAlign: 'center', mt: 3 }}>
                        Already have an account?{' '}
                        <Link href={login()} style={{ color: 'inherit', textDecoration: 'underline' }}>
                            Log in
                        </Link>
                    </Typography>
                </Box>
            </Box>
        </>
    );
}

function ReviewRow({ label, value }: { label: string; value: string }) {
    return (
        <>
            <Typography variant="body2" color="text.secondary" sx={{ fontWeight: 500 }}>
                {label}
            </Typography>
            <Typography variant="body2" sx={{ wordBreak: 'break-word' }}>
                {value || '—'}
            </Typography>
        </>
    );
}
