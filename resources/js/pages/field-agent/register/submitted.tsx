import { Head, Link } from '@inertiajs/react';

export default function FieldAgentRegisterSubmitted() {
    return (
        <>
            <Head title="Application received" />
            <div className="mx-auto max-w-xl p-6 text-center">
                <h1 className="text-2xl font-semibold">Application received</h1>
                <p className="mt-4 text-sm text-muted-foreground">
                    Thank you for applying to become a Surprise Moi field agent. Our team will review your submission and contact
                    you by email and SMS once the review is complete.
                </p>
                <Link href="/" className="mt-6 inline-block underline">
                    Back to home
                </Link>
            </div>
        </>
    );
}
