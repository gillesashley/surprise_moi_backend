import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';

type Member = {
    id: number;
    name: string;
    email: string;
    phone: string;
    location: string | null;
    is_active: boolean;
    must_change_password: boolean;
    vendors_onboarded: number;
    created_at: string;
};

export default function TeamIndex({ members }: { members: Member[] }) {
    return (
        <AppLayout breadcrumbs={[{ title: 'Team', href: '/field-agent/team' }]}>
            <Head title="My Team" />

            <div className="flex items-center justify-between p-4">
                <h1 className="text-xl font-semibold">My Team</h1>
                <Button asChild>
                    <Link href="/field-agent/team/new">Add member</Link>
                </Button>
            </div>

            {members.length === 0 ? (
                <div className="rounded-md border border-dashed p-8 text-center text-muted-foreground mx-4">
                    No team members yet. Click <strong>Add member</strong> to create one.
                </div>
            ) : (
                <div className="divide-y rounded-md border mx-4">
                    {members.map((m) => (
                        <Link
                            key={m.id}
                            href={`/field-agent/team/${m.id}`}
                            className="flex items-center justify-between p-4 hover:bg-muted"
                        >
                            <div>
                                <div className="font-medium">{m.name}</div>
                                <div className="text-sm text-muted-foreground">
                                    {m.email} · {m.phone} · {m.location ?? '—'}
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-sm">{m.vendors_onboarded} onboarded</span>
                                <Badge variant={m.is_active ? 'default' : 'secondary'}>
                                    {m.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                        </Link>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
