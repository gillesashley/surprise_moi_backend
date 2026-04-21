import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';

interface Item {
    id: number;
    item_key: string;
    category: string;
    criticality: string;
    passed: boolean | null;
    note: string | null;
}
interface Visit {
    id: string;
    status: string;
    computed_result: string | null;
    admin_override_result: string | null;
    admin_override_reason: string | null;
    admin_override_at: string | null;
    override_by: { id: number; name: string } | null;
    started_at: string;
    submitted_at: string | null;
    visit_latitude: string;
    visit_longitude: string;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    escalated: boolean;
    badge_issued_at: string | null;
    badge_expires_at: string | null;
    vendor: {
        id: number;
        business_name: string | null;
        name: string;
        email: string;
        phone: string | null;
        field_verified_until: string | null;
    };
    field_agent: { id: number; name: string };
    items: Item[];
}
interface Props {
    visit: Visit;
}

export default function AdminVendorVisitShow({ visit }: Props) {
    const override = useForm({ result: 'passed', reason: '' });
    const revoke = useForm({ reason: '' });

    const storefrontSrc = visit.storefront_photo_path ? `/storage/${visit.storefront_photo_path}` : null;
    const ownerSrc = visit.owner_photo_path ? `/storage/${visit.owner_photo_path}` : null;
    const mapsUrl = `https://maps.google.com/?q=${visit.visit_latitude},${visit.visit_longitude}`;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/dashboard' },
                { title: 'Vendor visits', href: '/dashboard/vendor-visits' },
                { title: visit.vendor.business_name ?? visit.vendor.name, href: '#' },
            ]}
        >
            <Head title="Visit detail" />
            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <header>
                    <h1 className="text-xl font-semibold">{visit.vendor.business_name ?? visit.vendor.name}</h1>
                    <p className="text-sm text-muted-foreground">Agent: {visit.field_agent.name}</p>
                    <p className="text-sm text-muted-foreground">
                        Started: {new Date(visit.started_at).toLocaleString()} ·{' '}
                        <a href={mapsUrl} target="_blank" rel="noreferrer" className="text-primary underline">
                            Map
                        </a>
                    </p>
                    <p className="text-sm">
                        Status: <strong>{visit.status}</strong> {visit.escalated && '(escalated)'}
                    </p>
                </header>

                <section>
                    <h2 className="mb-2 font-semibold">Checklist</h2>
                    <ul className="space-y-1 text-sm">
                        {visit.items.map((it) => (
                            <li key={it.id}>
                                <strong>{it.criticality === 'critical' ? '★' : '·'}</strong> {it.item_key}:{' '}
                                <span className={it.passed ? 'text-green-700' : 'text-red-700'}>
                                    {it.passed === null ? 'unanswered' : it.passed ? 'pass' : 'fail'}
                                </span>
                                {it.note && <em className="ml-2 text-muted-foreground">“{it.note}”</em>}
                            </li>
                        ))}
                    </ul>
                </section>

                <section className="grid gap-4 md:grid-cols-2">
                    {storefrontSrc && (
                        <a href={storefrontSrc} target="_blank" rel="noreferrer">
                            <img
                                src={storefrontSrc}
                                alt="Storefront"
                                className="max-h-64 w-full rounded border object-cover"
                            />
                        </a>
                    )}
                    {ownerSrc && (
                        <a href={ownerSrc} target="_blank" rel="noreferrer">
                            <img
                                src={ownerSrc}
                                alt="Owner"
                                className="max-h-64 w-full rounded border object-cover"
                            />
                        </a>
                    )}
                </section>

                {visit.notes && (
                    <section>
                        <h2 className="mb-1 font-semibold">Agent notes</h2>
                        <p className="whitespace-pre-line text-sm">{visit.notes}</p>
                    </section>
                )}

                <section className="rounded border p-4">
                    <h2 className="mb-2 font-semibold">Admin override</h2>
                    {visit.admin_override_result && (
                        <p className="mb-2 text-sm text-amber-800">
                            Previously overridden to <strong>{visit.admin_override_result}</strong> by{' '}
                            {visit.override_by?.name}: “{visit.admin_override_reason}”
                        </p>
                    )}
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            override.post(`/dashboard/vendor-visits/${visit.id}/override`);
                        }}
                        className="space-y-2"
                    >
                        <select
                            value={override.data.result}
                            onChange={(e) => override.setData('result', e.target.value)}
                            className="rounded border px-2 py-1"
                        >
                            <option value="passed">Mark passed</option>
                            <option value="failed">Mark failed</option>
                        </select>
                        <textarea
                            required
                            placeholder="Reason (required)"
                            value={override.data.reason}
                            onChange={(e) => override.setData('reason', e.target.value)}
                            className="w-full rounded border p-2"
                            rows={2}
                        />
                        <button
                            disabled={override.processing}
                            className="rounded bg-primary px-3 py-1 text-primary-foreground disabled:opacity-50"
                        >
                            Apply override
                        </button>
                    </form>
                </section>

                {visit.status === 'passed' && (
                    <section className="rounded border border-red-200 p-4">
                        <h2 className="mb-2 font-semibold text-red-800">Revoke active badge</h2>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (
                                    !confirm(
                                        'Revoke this badge? The vendor will lose Field Verified status immediately.',
                                    )
                                )
                                    return;
                                revoke.post(`/dashboard/vendor-visits/${visit.id}/revoke`);
                            }}
                            className="space-y-2"
                        >
                            <textarea
                                required
                                placeholder="Reason (required)"
                                value={revoke.data.reason}
                                onChange={(e) => revoke.setData('reason', e.target.value)}
                                className="w-full rounded border p-2"
                                rows={2}
                            />
                            <button
                                disabled={revoke.processing}
                                className="rounded bg-red-600 px-3 py-1 text-white disabled:opacity-50"
                            >
                                Revoke badge
                            </button>
                        </form>
                    </section>
                )}
            </div>
        </AppLayout>
    );
}
