import { submit } from '@/actions/App/Http/Controllers/AccountDeletionController';
import { type SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Gift, Shield, Trash2 } from 'lucide-react';

export default function AccountDeletion() {
    const page = usePage<SharedData>();
    const status = page.props.status as string | undefined;
    const { data, setData, post, processing } = useForm({
        email: '',
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        post(submit().url);
    }

    return (
        <>
            <Head title="Request Account Deletion" />
            <div className="min-h-screen bg-gradient-to-br from-primary/10 via-background to-accent/10">
                <header className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="container mx-auto flex h-16 items-center justify-between px-4">
                        <div className="flex items-center gap-2">
                            <Gift className="h-6 w-6 text-primary" />
                            <span className="text-xl font-bold">
                                SurpriseMoi
                            </span>
                        </div>
                    </div>
                </header>

                <main className="container mx-auto px-4 py-12">
                    <div className="mx-auto max-w-lg">
                        <div className="mb-8 text-center">
                            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-destructive/10">
                                <Trash2 className="h-8 w-8 text-destructive" />
                            </div>
                            <h1 className="mb-2 text-3xl font-bold">
                                Request Account Deletion
                            </h1>
                            <p className="text-muted-foreground">
                                Delete your SurpriseMoi account and all
                                associated data
                            </p>
                        </div>

                        {status && (
                            <div className="mb-6 rounded-lg border border-success/20 bg-success/10 p-4 text-center text-success">
                                {status}
                            </div>
                        )}

                        <div className="mb-6 rounded-lg border border-border bg-card p-6 shadow-sm">
                            <div className="mb-4 flex items-start gap-3">
                                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />
                                <div>
                                    <h2 className="mb-2 font-semibold text-destructive">
                                        Warning: This action is irreversible
                                    </h2>
                                    <ul className="space-y-1 text-sm text-muted-foreground">
                                        <li>
                                            Your profile and personal
                                            information will be deleted
                                        </li>
                                        <li>
                                            Your order history and saved data
                                            will be removed
                                        </li>
                                        <li>
                                            Any active orders or pending
                                            transactions may be affected
                                        </li>
                                        <li>This action cannot be undone</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label
                                    htmlFor="email"
                                    className="mb-2 block text-sm font-medium"
                                >
                                    Email Address
                                </label>
                                <input
                                    type="email"
                                    id="email"
                                    value={data.email}
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    placeholder="Enter your registered email"
                                    required
                                    className="w-full rounded-md border border-input bg-background px-4 py-3 text-sm focus:border-primary focus:ring-1 focus:ring-primary focus:outline-none"
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-destructive px-6 py-3 text-sm font-semibold text-destructive-foreground transition-colors hover:bg-destructive/90 disabled:opacity-50"
                            >
                                {processing
                                    ? 'Processing...'
                                    : 'Delete My Account'}
                            </button>
                        </form>

                        <div className="mt-6 flex items-start gap-3 rounded-lg bg-muted/50 p-4">
                            <Shield className="mt-0.5 h-5 w-5 shrink-0 text-primary" />
                            <div className="text-sm text-muted-foreground">
                                <p className="font-medium">
                                    Your Privacy Matters
                                </p>
                                <p>
                                    We take your privacy seriously. Your data
                                    will be permanently removed from our systems
                                    in accordance with our privacy policy.
                                </p>
                            </div>
                        </div>
                    </div>
                </main>

                <footer className="border-t bg-background/95 backdrop-blur">
                    <div className="container mx-auto px-4 py-8 text-center text-sm text-muted-foreground">
                        <p>
                            &copy; {new Date().getFullYear()} SurpriseMoi. All
                            rights reserved.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
