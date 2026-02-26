import { dashboard, login, register } from '@/routes';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Gift, Sparkles, Users, Package } from 'lucide-react';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Welcome to SurpriseMoi" />
            <div className="min-h-screen bg-gradient-to-br from-primary/10 via-background to-accent/10">
                {/* Header Navigation */}
                <header className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="container mx-auto flex h-16 items-center justify-between px-4">
                        <div className="flex items-center gap-2">
                            <Gift className="h-6 w-6 text-primary" />
                            <span className="text-xl font-bold">SurpriseMoi</span>
                        </div>
                        <nav className="flex items-center gap-4">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex h-9 items-center justify-center rounded-md px-6 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-flex h-9 items-center justify-center rounded-md bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                        >
                                            Register
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                {/* Hero Section */}
                <main className="container mx-auto px-4 py-20">
                    <div className="mx-auto max-w-4xl text-center">
                        <div className="mb-6 inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-2 text-sm font-medium text-primary">
                            <Sparkles className="h-4 w-4" />
                            <span>Create Memorable Moments</span>
                        </div>
                        
                        <h1 className="mb-6 text-5xl font-bold tracking-tight sm:text-6xl md:text-7xl">
                            Welcome to{' '}
                            <span className="bg-gradient-to-r from-primary to-accent bg-clip-text text-transparent">
                                SurpriseMoi
                            </span>
                        </h1>
                        
                        <p className="mb-12 text-xl text-muted-foreground">
                            Your one-stop platform for creating unforgettable surprises and connecting with amazing vendors.
                            Discover unique gifts, services, and experiences tailored just for you.
                        </p>

                        <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                            {!auth.user && (
                                <>
                                    <Link
                                        href={register()}
                                        className="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                                    >
                                        Get Started
                                    </Link>
                                    <Link
                                        href={login()}
                                        className="inline-flex h-12 items-center justify-center rounded-md border border-input bg-background px-8 text-base font-semibold transition-colors hover:bg-accent hover:text-accent-foreground"
                                    >
                                        Sign In
                                    </Link>
                                </>
                            )}
                            {auth.user && (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex h-12 items-center justify-center rounded-md bg-primary px-8 text-base font-semibold text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    Go to Dashboard
                                </Link>
                            )}
                        </div>
                    </div>

                    {/* Features Grid */}
                    <div className="mx-auto mt-24 grid max-w-5xl gap-8 md:grid-cols-3">
                        <div className="rounded-xl border bg-card p-6 text-center shadow-sm transition-all hover:shadow-md">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
                                <Gift className="h-7 w-7 text-primary" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Unique Surprises</h3>
                            <p className="text-sm text-muted-foreground">
                                Discover and create personalized surprises with our curated selection of gifts and experiences.
                            </p>
                        </div>

                        <div className="rounded-xl border bg-card p-6 text-center shadow-sm transition-all hover:shadow-md">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-accent/10">
                                <Package className="h-7 w-7 text-accent" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Quality Products</h3>
                            <p className="text-sm text-muted-foreground">
                                Browse thousands of high-quality products from verified vendors across multiple categories.
                            </p>
                        </div>

                        <div className="rounded-xl border bg-card p-6 text-center shadow-sm transition-all hover:shadow-md">
                            <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-success/10">
                                <Users className="h-7 w-7 text-success" />
                            </div>
                            <h3 className="mb-2 text-lg font-semibold">Trusted Vendors</h3>
                            <p className="text-sm text-muted-foreground">
                                Connect with reliable vendors offering exceptional services and products.
                            </p>
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="border-t bg-background/95 backdrop-blur">
                    <div className="container mx-auto px-4 py-8 text-center text-sm text-muted-foreground">
                        <p>&copy; {new Date().getFullYear()} SurpriseMoi. All rights reserved.</p>
                    </div>
                </footer>
            </div>
        </>
    );
}

