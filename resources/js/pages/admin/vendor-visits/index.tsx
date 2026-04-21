import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';

interface VisitRow {
    id: string;
    status: string;
    started_at: string;
    submitted_at: string | null;
    vendor: { id: number; business_name: string | null; name: string };
    field_agent: { id: number; name: string };
}

interface Props {
    tab: 'needs-review' | 'recent-failures' | 'all';
    visits: {
        data: VisitRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
}

const TABS: Array<{ key: Props['tab']; label: string }> = [
    { key: 'needs-review', label: 'Needs review' },
    { key: 'recent-failures', label: 'Recent failures' },
    { key: 'all', label: 'All visits' },
];

export default function AdminVendorVisitsIndex({ tab, visits }: Props) {
    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/dashboard' },
                { title: 'Vendor visits', href: '/dashboard/vendor-visits' },
            ]}
        >
            <Head title="Vendor visits" />
            <div className="p-6">
                <div className="mb-4 flex gap-2">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => router.visit(`/dashboard/vendor-visits?tab=${t.key}`)}
                            className={`rounded border px-3 py-1 ${tab === t.key ? 'bg-primary text-primary-foreground' : ''}`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="py-2">Vendor</th>
                            <th>Agent</th>
                            <th>Status</th>
                            <th>Started</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {visits.data.map((v) => (
                            <tr key={v.id} className="border-b">
                                <td className="py-2">{v.vendor.business_name ?? v.vendor.name}</td>
                                <td>{v.field_agent.name}</td>
                                <td>
                                    <StatusBadge status={v.status} />
                                </td>
                                <td>{new Date(v.started_at).toLocaleString()}</td>
                                <td>
                                    <Link href={`/dashboard/vendor-visits/${v.id}`} className="text-primary">
                                        Open →
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AppLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const tone =
        {
            passed: 'bg-green-600',
            failed: 'bg-red-600',
            submitted: 'bg-amber-600',
            revoked: 'bg-gray-600',
            draft: 'bg-slate-400',
        }[status] ?? 'bg-slate-400';
    return <span className={`rounded px-2 py-0.5 text-xs text-white ${tone}`}>{status}</span>;
}
