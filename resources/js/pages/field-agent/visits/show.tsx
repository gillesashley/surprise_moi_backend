import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Vendor {
    id: number;
    business_name: string | null;
    name: string;
    email: string;
    phone: string | null;
}
interface VendorApplication {
    id: number;
    has_business_certificate: boolean;
    tin_number: string | null;
    ghana_card_front: string | null;
    ghana_card_back: string | null;
    selfie_image: string | null;
    business_certificate_document: string | null;
    proof_of_business: string | null;
    mobile_money_number: string | null;
    mobile_money_provider: string | null;
    facebook_handle: string | null;
    instagram_handle: string | null;
    twitter_handle: string | null;
}
interface RecentVisit {
    id: string;
    started_at: string;
    status: string;
    badge_expires_at: string | null;
}
interface Props {
    vendor: Vendor;
    application: VendorApplication | null;
    recentVisits: RecentVisit[];
}

export default function VisitShow({ vendor, application, recentVisits }: Props) {
    const [starting, setStarting] = useState(false);

    const startVisit = () => {
        if (!navigator.geolocation) {
            alert('Your browser does not support location. A visit requires GPS.');
            return;
        }
        setStarting(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                router.post(
                    `/field-agent/visits/${vendor.id}/start`,
                    {
                        latitude: pos.coords.latitude,
                        longitude: pos.coords.longitude,
                    },
                    { onFinish: () => setStarting(false) },
                );
            },
            () => {
                setStarting(false);
                alert(
                    'Location is required to verify a visit happened in person. Please enable location and try again.',
                );
            },
            { enableHighAccuracy: true, timeout: 15_000 },
        );
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/field-agent/dashboard' },
                { title: 'Visits', href: '/field-agent/visits' },
                { title: vendor.business_name ?? vendor.name, href: `/field-agent/visits/${vendor.id}` },
            ]}
        >
            <Head title={`Visit — ${vendor.business_name ?? vendor.name}`} />
            <div className="space-y-6 p-6">
                <header>
                    <h1 className="text-2xl font-semibold">{vendor.business_name ?? vendor.name}</h1>
                    <p className="text-sm text-muted-foreground">
                        {vendor.phone} · {vendor.email}
                    </p>
                </header>

                <button
                    type="button"
                    onClick={startVisit}
                    disabled={starting}
                    className="w-full rounded bg-primary px-4 py-3 text-primary-foreground disabled:opacity-50"
                >
                    {starting ? 'Getting location…' : 'Start visit'}
                </button>

                <section>
                    <h2 className="mb-2 font-semibold">Claim data on file</h2>
                    <ClaimGrid application={application} />
                </section>

                <section>
                    <h2 className="mb-2 font-semibold">Recent visits</h2>
                    {recentVisits.length === 0 ? (
                        <p className="text-sm text-muted-foreground">No prior visits.</p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {recentVisits.map((v) => (
                                <li key={v.id}>
                                    {new Date(v.started_at).toLocaleDateString()} — <strong>{v.status}</strong>
                                    {v.badge_expires_at &&
                                        ` (expires ${new Date(v.badge_expires_at).toLocaleDateString()})`}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

function ClaimGrid({ application }: { application: VendorApplication | null }) {
    if (!application) return <p className="text-sm text-muted-foreground">No application on file.</p>;
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <DocImg label="Ghana Card (front)" path={application.ghana_card_front} />
            <DocImg label="Ghana Card (back)" path={application.ghana_card_back} />
            <DocImg label="Selfie" path={application.selfie_image} />
            {application.has_business_certificate && (
                <DocImg label="Business certificate" path={application.business_certificate_document} />
            )}
            {application.tin_number && <DocImg label="Proof of business" path={application.proof_of_business} />}
            <KV
                label="Mobile money"
                value={
                    application.mobile_money_provider
                        ? `${application.mobile_money_provider} — ${application.mobile_money_number}`
                        : '—'
                }
            />
            <KV label="TIN number" value={application.tin_number ?? '—'} />
            <KV label="Facebook" value={application.facebook_handle ?? '—'} />
            <KV label="Instagram" value={application.instagram_handle ?? '—'} />
            <KV label="Twitter/X" value={application.twitter_handle ?? '—'} />
        </div>
    );
}

function DocImg({ label, path }: { label: string; path: string | null }) {
    if (!path) return <KV label={label} value="—" />;
    const src = path.startsWith('http') ? path : `/storage/${path}`;
    return (
        <div>
            <div className="mb-1 text-xs font-medium text-muted-foreground">{label}</div>
            <a href={src} target="_blank" rel="noreferrer">
                <img src={src} alt={label} className="max-h-48 rounded border object-contain" />
            </a>
        </div>
    );
}

function KV({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs font-medium text-muted-foreground">{label}</div>
            <div className="text-sm">{value}</div>
        </div>
    );
}
