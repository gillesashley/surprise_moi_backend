import AppLayout from '@/layouts/app-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

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
    escalated: boolean;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    vendor: { id: number; business_name: string | null; name: string };
}
interface Props {
    visit: Visit;
    items: Item[];
}

const CHECKLIST_LABELS: Record<string, string> = {
    'identity.person_matches_ghana_card': 'Does the person in front of you match the Ghana Card photo on file?',
    'identity.name_matches_records': 'Does the name on the physical Ghana Card match the name on file?',
    'physical.location_is_real': 'Is the claimed business address a real, findable location?',
    'physical.business_name_matches': 'Does the business at this address match the business name on file?',
    'physical.business_is_operational': 'Is there signage, stock, or active service — a real going concern?',
    'documents.business_cert_seen':
        'Have you seen the physical business certificate, and does it match the file?',
    'documents.tin_seen': 'Have you seen the physical TIN document, and does it match the file?',
    'financial.phone_reachable': 'Did you call the registered phone and have it ring / be answered?',
    'financial.momo_test_received': 'Did your GHS 1 test MoMo reach the registered mobile money number?',
};

export default function VisitForm({ visit, items }: Props) {
    const [itemState, setItemState] = useState(items);
    const submit = useForm({
        storefront_photo: null as File | null,
        owner_photo: null as File | null,
        notes: visit.notes ?? '',
        escalated: visit.escalated,
    });

    const categories = useMemo(() => {
        const byCat: Record<string, Item[]> = {};
        itemState.forEach((i) => {
            (byCat[i.category] ??= []).push(i);
        });
        return byCat;
    }, [itemState]);

    const hasStorefront = Boolean(visit.storefront_photo_path) || Boolean(submit.data.storefront_photo);
    const hasOwner = Boolean(visit.owner_photo_path) || Boolean(submit.data.owner_photo);
    const allAnswered = itemState.every((i) => i.passed !== null);
    const canSubmit = allAnswered && hasStorefront && hasOwner;
    const isTerminal = visit.status !== 'draft';

    const toggleItem = (item: Item, passed: boolean) => {
        setItemState((prev) => prev.map((i) => (i.id === item.id ? { ...i, passed } : i)));
        router.patch(
            `/field-agent/visits/forms/${visit.id}/items/${item.id}`,
            { passed },
            { preserveScroll: true, preserveState: true, only: [] },
        );
    };

    const saveNote = (item: Item, note: string) => {
        router.patch(
            `/field-agent/visits/forms/${visit.id}/items/${item.id}`,
            { passed: item.passed, note },
            { preserveScroll: true, preserveState: true, only: [] },
        );
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submit.post(`/field-agent/visits/forms/${visit.id}/submit`, { forceFormData: true });
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Visits', href: '/field-agent/visits' },
                {
                    title: visit.vendor.business_name ?? visit.vendor.name,
                    href: `/field-agent/visits/${visit.vendor.id}`,
                },
                { title: 'Form', href: `/field-agent/visits/forms/${visit.id}` },
            ]}
        >
            <Head title="Visit form" />
            <form onSubmit={onSubmit} className="mx-auto max-w-xl space-y-6 p-6">
                <div className="rounded bg-green-50 p-3 text-sm text-green-900">GPS captured ✓</div>

                {Object.entries(categories).map(([cat, catItems]) => (
                    <section key={cat}>
                        <h2 className="mb-2 text-lg font-semibold capitalize">{cat.replace('_', ' ')}</h2>
                        <div className="space-y-3">
                            {catItems.map((item) => (
                                <div key={item.id} className="rounded border p-3">
                                    <div className="text-sm">
                                        {CHECKLIST_LABELS[item.item_key] ?? item.item_key}
                                    </div>
                                    <div className="mt-2 flex gap-2">
                                        <button
                                            type="button"
                                            disabled={isTerminal}
                                            onClick={() => toggleItem(item, true)}
                                            className={`flex-1 rounded border px-3 py-1 ${item.passed === true ? 'bg-green-600 text-white' : ''}`}
                                        >
                                            Pass
                                        </button>
                                        <button
                                            type="button"
                                            disabled={isTerminal}
                                            onClick={() => toggleItem(item, false)}
                                            className={`flex-1 rounded border px-3 py-1 ${item.passed === false ? 'bg-red-600 text-white' : ''}`}
                                        >
                                            Fail
                                        </button>
                                    </div>
                                    <input
                                        disabled={isTerminal}
                                        placeholder="Optional note"
                                        defaultValue={item.note ?? ''}
                                        onBlur={(e) => saveNote(item, e.target.value)}
                                        className="mt-2 w-full rounded border px-2 py-1 text-sm"
                                    />
                                </div>
                            ))}
                        </div>
                    </section>
                ))}

                <section>
                    <h2 className="mb-2 text-lg font-semibold">Required evidence</h2>
                    <label className="mb-3 block">
                        <div className="text-sm">Storefront photo {hasStorefront && '✓'}</div>
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={(e) => submit.setData('storefront_photo', e.target.files?.[0] ?? null)}
                        />
                    </label>
                    <label className="mb-3 block">
                        <div className="text-sm">Owner-at-premises photo {hasOwner && '✓'}</div>
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={(e) => submit.setData('owner_photo', e.target.files?.[0] ?? null)}
                        />
                    </label>
                </section>

                <section>
                    <label className="block">
                        <div className="text-sm">General notes</div>
                        <textarea
                            disabled={isTerminal}
                            value={submit.data.notes}
                            onChange={(e) => submit.setData('notes', e.target.value)}
                            className="w-full rounded border p-2"
                            rows={3}
                        />
                    </label>
                    <label className="mt-3 flex items-center gap-2">
                        <input
                            type="checkbox"
                            disabled={isTerminal}
                            checked={submit.data.escalated}
                            onChange={(e) => submit.setData('escalated', e.target.checked)}
                        />
                        <span className="text-sm">
                            Escalate to admin — tick if something feels off but you can't prove it.
                        </span>
                    </label>
                </section>

                <button
                    type="submit"
                    disabled={!canSubmit || submit.processing || isTerminal}
                    className="w-full rounded bg-primary px-4 py-3 text-primary-foreground disabled:opacity-50"
                >
                    {isTerminal ? `Visit ${visit.status}` : submit.processing ? 'Submitting…' : 'Submit visit'}
                </button>
            </form>
        </AppLayout>
    );
}
