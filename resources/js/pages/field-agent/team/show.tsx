import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Form, Head } from '@inertiajs/react';

type Member = {
    id: number;
    name: string;
    email: string;
    phone: string;
    location: string | null;
    is_active: boolean;
    must_change_password: boolean;
    created_at: string;
};

type Vendor = {
    id: number;
    business_name: string;
    status: string;
    created_at: string | null;
};

export default function TeamShow({ member, vendors }: { member: Member; vendors: Vendor[] }) {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Team', href: '/field-agent/team' },
            { title: member.name, href: `/field-agent/team/${member.id}` },
        ]}>
            <Head title={member.name} />

            <div className="max-w-3xl mx-auto p-4 space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">{member.name}</h1>
                        <p className="text-sm text-muted-foreground">
                            {member.email} · {member.phone} · {member.location ?? '—'}
                        </p>
                    </div>
                    <Badge variant={member.is_active ? 'default' : 'secondary'}>
                        {member.is_active ? 'Active' : 'Inactive'}
                    </Badge>
                </div>

                <Form
                    action={`/field-agent/team/${member.id}`}
                    method="patch"
                    transform={(data) => ({ ...data, is_active: !member.is_active })}
                >
                    {({ processing }) => (
                        <Button type="submit" variant={member.is_active ? 'destructive' : 'default'} disabled={processing}>
                            {member.is_active ? 'Deactivate member' : 'Reactivate member'}
                        </Button>
                    )}
                </Form>

                <section>
                    <h2 className="font-medium mb-2">Onboarded vendors ({vendors.length})</h2>
                    {vendors.length === 0 ? (
                        <p className="text-sm text-muted-foreground">None yet.</p>
                    ) : (
                        <div className="divide-y rounded-md border">
                            {vendors.map((v) => (
                                <div key={v.id} className="flex items-center justify-between p-3">
                                    <div>
                                        <div className="font-medium">{v.business_name}</div>
                                        <div className="text-xs text-muted-foreground">{v.created_at ?? ''}</div>
                                    </div>
                                    <Badge variant="outline">{v.status}</Badge>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}
