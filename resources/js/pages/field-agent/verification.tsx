import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';

type Props = {
    application: {
        first_name: string;
        last_name: string;
        email: string;
        contact_number: string;
        location: string;
        ghana_card_number: string;
        status: string;
        region?: { name: string };
        city?: { name: string };
        ghana_card_image_url: string;
        selfie_url: string;
    } | null;
};

export default function Verification({ application }: Props) {
    if (!application) {
        return (
            <AppLayout>
                <Head title="My verification" />
                <div className="p-6">No verification record found.</div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title="My verification" />
            <div className="max-w-3xl space-y-4 p-6">
                <h1 className="text-2xl font-semibold">My verification</h1>
                <div className="space-y-1 rounded border p-4 text-sm">
                    <p>
                        <strong>Name:</strong> {application.first_name} {application.last_name}
                    </p>
                    <p>
                        <strong>Email:</strong> {application.email}
                    </p>
                    <p>
                        <strong>Phone:</strong> {application.contact_number}
                    </p>
                    <p>
                        <strong>Region:</strong> {application.region?.name}
                    </p>
                    <p>
                        <strong>City:</strong> {application.city?.name}
                    </p>
                    <p>
                        <strong>Location:</strong> {application.location}
                    </p>
                    <p>
                        <strong>Ghana card:</strong> {application.ghana_card_number}
                    </p>
                    <p>
                        <strong>Status:</strong> {application.status}
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="rounded border p-4">
                        <h2 className="mb-2 font-medium">Ghana card</h2>
                        <img src={application.ghana_card_image_url} className="max-h-96 rounded" alt="Ghana card" />
                    </div>
                    <div className="rounded border p-4">
                        <h2 className="mb-2 font-medium">Selfie</h2>
                        <img src={application.selfie_url} className="max-h-96 rounded" alt="Selfie" />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
