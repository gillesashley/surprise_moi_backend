import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, useForm } from '@inertiajs/react';
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
            return Boolean(data.ghana_card_number && data.ghana_card_image && data.selfie);
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
            <div className="mx-auto max-w-2xl p-6">
                <h1 className="text-2xl font-semibold">Become a Field Agent</h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Step {step + 1} of {STEPS.length} — {STEPS[step]}
                </p>

                <div className="my-4 flex gap-2">
                    {STEPS.map((_, i) => (
                        <div key={i} className={`h-2 flex-1 rounded-full ${i <= step ? 'bg-primary' : 'bg-muted'}`} />
                    ))}
                </div>

                <form onSubmit={submit} encType="multipart/form-data">
                    <input
                        type="text"
                        name="website"
                        value={data.website}
                        onChange={(e) => setData('website', e.target.value)}
                        className="hidden"
                        tabIndex={-1}
                        autoComplete="off"
                    />

                    {step === 0 && (
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="first_name">First name</Label>
                                <Input id="first_name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} />
                                {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="last_name">Last name</Label>
                                <Input id="last_name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} />
                                {errors.last_name && <p className="text-sm text-destructive">{errors.last_name}</p>}
                            </div>
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>
                            <div>
                                <Label htmlFor="contact_number">Contact number</Label>
                                <Input
                                    id="contact_number"
                                    placeholder="0551234567"
                                    value={data.contact_number}
                                    onChange={(e) => setData('contact_number', e.target.value)}
                                />
                                {errors.contact_number && <p className="text-sm text-destructive">{errors.contact_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                />
                                {errors.password && <p className="text-sm text-destructive">{errors.password}</p>}
                            </div>
                            <div>
                                <Label htmlFor="password_confirmation">Confirm password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                />
                            </div>
                        </div>
                    )}

                    {step === 1 && (
                        <div className="space-y-4">
                            <div>
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
                                {errors.region_id && <p className="text-sm text-destructive">{errors.region_id}</p>}
                            </div>
                            <div>
                                <Label>City</Label>
                                <Select value={data.city_id} onValueChange={(v) => setData('city_id', v)} disabled={!selectedRegion}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a city" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {selectedRegion?.cities.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.city_id && <p className="text-sm text-destructive">{errors.city_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input
                                    id="location"
                                    placeholder="e.g. Osu, near Koala"
                                    value={data.location}
                                    onChange={(e) => setData('location', e.target.value)}
                                />
                                {errors.location && <p className="text-sm text-destructive">{errors.location}</p>}
                            </div>
                        </div>
                    )}

                    {step === 2 && (
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="ghana_card_number">Ghana card number</Label>
                                <Input
                                    id="ghana_card_number"
                                    placeholder="GHA-123456789-1"
                                    value={data.ghana_card_number}
                                    onChange={(e) => setData('ghana_card_number', e.target.value)}
                                />
                                {errors.ghana_card_number && <p className="text-sm text-destructive">{errors.ghana_card_number}</p>}
                            </div>
                            <div>
                                <Label htmlFor="ghana_card_image">Ghana card photo (max 5MB)</Label>
                                <Input
                                    id="ghana_card_image"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => setData('ghana_card_image', e.target.files?.[0] ?? null)}
                                />
                                {errors.ghana_card_image && <p className="text-sm text-destructive">{errors.ghana_card_image}</p>}
                            </div>
                            <div>
                                <Label htmlFor="selfie">Selfie (max 5MB)</Label>
                                <Input
                                    id="selfie"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(e) => setData('selfie', e.target.files?.[0] ?? null)}
                                />
                                {errors.selfie && <p className="text-sm text-destructive">{errors.selfie}</p>}
                            </div>
                        </div>
                    )}

                    {step === 3 && (
                        <div className="space-y-2">
                            <h2 className="text-lg font-medium">Review</h2>
                            <div className="rounded border p-4 text-sm">
                                <p>
                                    <strong>Name:</strong> {data.first_name} {data.last_name}
                                </p>
                                <p>
                                    <strong>Email:</strong> {data.email}
                                </p>
                                <p>
                                    <strong>Contact:</strong> {data.contact_number}
                                </p>
                                <p>
                                    <strong>Region:</strong> {selectedRegion?.name}
                                </p>
                                <p>
                                    <strong>City:</strong> {selectedRegion?.cities.find((c) => String(c.id) === data.city_id)?.name}
                                </p>
                                <p>
                                    <strong>Location:</strong> {data.location}
                                </p>
                                <p>
                                    <strong>Ghana card:</strong> {data.ghana_card_number}
                                </p>
                                <p>
                                    <strong>Ghana card photo:</strong> {data.ghana_card_image?.name ?? '—'}
                                </p>
                                <p>
                                    <strong>Selfie:</strong> {data.selfie?.name ?? '—'}
                                </p>
                            </div>
                        </div>
                    )}

                    <div className="mt-6 flex justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={step === 0 || processing}
                            onClick={() => setStep(step - 1)}
                        >
                            Back
                        </Button>
                        {step < STEPS.length - 1 ? (
                            <Button type="button" disabled={!canProceed() || processing} onClick={() => setStep(step + 1)}>
                                Next
                            </Button>
                        ) : (
                            <Button type="submit" disabled={processing}>
                                Submit application
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}
