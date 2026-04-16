import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Application = {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    contact_number: string;
    location: string;
    ghana_card_number: string;
    status: string;
    region?: { name: string };
    city?: { name: string };
    reviewer?: { name: string } | null;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
    ghana_card_image_url: string;
    selfie_url: string;
};

export default function FieldAgentApplicationShow({ application }: { application: Application }) {
    const [rejectOpen, setRejectOpen] = useState(false);

    const approveForm = useForm({});
    const rejectForm = useForm({ rejection_reason: '' });

    const reviewable = application.status === 'pending' || application.status === 'under_review';

    const onApprove = (e: FormEvent) => {
        e.preventDefault();
        approveForm.post(`/dashboard/field-agent-applications/${application.id}/approve`);
    };

    const onReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/dashboard/field-agent-applications/${application.id}/reject`, {
            onSuccess: () => setRejectOpen(false),
        });
    };

    return (
        <AppLayout>
            <Head title={`Application — ${application.first_name} ${application.last_name}`} />
            <div className="max-w-4xl space-y-6 p-6">
                <div className="flex justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {application.first_name} {application.last_name}
                        </h1>
                        <p className="text-sm text-muted-foreground">Status: {application.status}</p>
                    </div>
                    {reviewable && (
                        <div className="flex gap-2">
                            <form onSubmit={onApprove}>
                                <Button type="submit" disabled={approveForm.processing}>
                                    Approve
                                </Button>
                            </form>
                            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                                <DialogTrigger asChild>
                                    <Button variant="destructive">Reject</Button>
                                </DialogTrigger>
                                <DialogContent>
                                    <DialogHeader>
                                        <DialogTitle>Reject application</DialogTitle>
                                        <DialogDescription>
                                            Please provide a clear reason. This will be sent to the applicant.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <form onSubmit={onReject} className="space-y-4">
                                        <Textarea
                                            rows={4}
                                            placeholder="Reason for rejection…"
                                            value={rejectForm.data.rejection_reason}
                                            onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                                        />
                                        {rejectForm.errors.rejection_reason && (
                                            <p className="text-sm text-destructive">{rejectForm.errors.rejection_reason}</p>
                                        )}
                                        <DialogFooter>
                                            <Button type="button" variant="outline" onClick={() => setRejectOpen(false)}>
                                                Cancel
                                            </Button>
                                            <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                                                Confirm rejection
                                            </Button>
                                        </DialogFooter>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>
                    )}
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Section title="Contact">
                        <Field label="Email" value={application.email} />
                        <Field label="Phone" value={application.contact_number} />
                    </Section>
                    <Section title="Location">
                        <Field label="Region" value={application.region?.name ?? '—'} />
                        <Field label="City" value={application.city?.name ?? '—'} />
                        <Field label="Location" value={application.location} />
                    </Section>
                    <Section title="Identity">
                        <Field label="Ghana card number" value={application.ghana_card_number} />
                    </Section>
                    <Section title="Review">
                        <Field label="Status" value={application.status} />
                        {application.reviewer && <Field label="Reviewed by" value={application.reviewer.name} />}
                        {application.reviewed_at && (
                            <Field label="Reviewed at" value={new Date(application.reviewed_at).toLocaleString()} />
                        )}
                        {application.rejection_reason && <Field label="Reason" value={application.rejection_reason} />}
                    </Section>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Figure title="Ghana card" url={application.ghana_card_image_url} />
                    <Figure title="Selfie" url={application.selfie_url} />
                </div>
            </div>
        </AppLayout>
    );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
    return (
        <div className="rounded border p-4">
            <h2 className="mb-2 font-medium">{title}</h2>
            <div className="space-y-1 text-sm">{children}</div>
        </div>
    );
}

function Field({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span>{value}</span>
        </div>
    );
}

function Figure({ title, url }: { title: string; url: string }) {
    return (
        <div className="rounded border p-4">
            <h2 className="mb-2 font-medium">{title}</h2>
            <a href={url} target="_blank" rel="noreferrer">
                <img src={url} alt={title} className="max-h-96 rounded" />
            </a>
        </div>
    );
}
