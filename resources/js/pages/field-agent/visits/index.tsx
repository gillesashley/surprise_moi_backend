import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';

interface VendorRow {
    id: number;
    business_name: string | null;
    name: string;
    field_verified_until: string | null;
}

interface DraftRow {
    id: string;
    started_at: string;
    vendor: { id: number; business_name: string | null; name: string };
}

interface Props {
    needsVisit: VendorRow[];
    expiringSoon: VendorRow[];
    drafts: DraftRow[];
}

export default function VisitsIndex({ needsVisit, expiringSoon, drafts }: Props) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Dashboard', href: '/field-agent/dashboard' }, { title: 'Visits', href: '/field-agent/visits' }]}>
            <Head title="Vendor visits" />
            <div className="space-y-8 p-6">
                <Section title={`Needs visit now (${needsVisit.length})`} emptyText="Nothing pending — great job.">
                    {needsVisit.map((v) => (
                        <VendorRowItem key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section title={`Expiring soon (${expiringSoon.length})`} emptyText="No badges expiring in the next 30 days.">
                    {expiringSoon.map((v) => (
                        <VendorRowItem key={v.id} vendor={v} badgeExpires={v.field_verified_until} />
                    ))}
                </Section>

                <Section title={`Resume drafts (${drafts.length})`} emptyText="No draft visits.">
                    {drafts.map((d) => (
                        <Link key={d.id} href={`/field-agent/visits/forms/${d.id}`} className="block rounded border p-3 hover:bg-muted">
                            <div className="font-medium">{d.vendor.business_name ?? d.vendor.name}</div>
                            <div className="text-sm text-muted-foreground">Started {new Date(d.started_at).toLocaleString()}</div>
                        </Link>
                    ))}
                </Section>
            </div>
        </AppLayout>
    );
}

function Section({ title, emptyText, children }: { title: string; emptyText: string; children: React.ReactNode }) {
    const empty = !children || (Array.isArray(children) && children.length === 0);
    return (
        <section>
            <h2 className="mb-3 text-lg font-semibold">{title}</h2>
            <div className="space-y-2">{empty ? <p className="text-sm text-muted-foreground">{emptyText}</p> : children}</div>
        </section>
    );
}

function VendorRowItem({ vendor, badgeExpires }: { vendor: VendorRow; badgeExpires: string | null }) {
    const label = vendor.business_name ?? vendor.name;
    return (
        <Link href={`/field-agent/visits/${vendor.id}`} className="flex items-center justify-between rounded border p-3 hover:bg-muted">
            <div>
                <div className="font-medium">{label}</div>
                <div className="text-sm text-muted-foreground">
                    {badgeExpires ? `Expires ${new Date(badgeExpires).toLocaleDateString()}` : 'Never verified'}
                </div>
            </div>
            <span className="text-sm text-primary">Open →</span>
        </Link>
    );
}
