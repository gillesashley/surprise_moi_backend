import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Form, Head, Link } from '@inertiajs/react';

export default function TeamNew() {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Team', href: '/field-agent/team' },
            { title: 'Add member', href: '/field-agent/team/new' },
        ]}>
            <Head title="Add team member" />

            <div className="max-w-xl mx-auto p-4 space-y-4">
                <h1 className="text-xl font-semibold">Add team member</h1>
                <p className="text-sm text-muted-foreground">
                    The member's default password will be their phone number. They will be required to change
                    it on first login.
                </p>

                <Form
                    action="/field-agent/team"
                    method="post"
                    resetOnSuccess
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div>
                                <Label htmlFor="name">Full name</Label>
                                <Input id="name" name="name" required />
                                {errors.name && <p className="text-sm text-destructive">{errors.name}</p>}
                            </div>

                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input id="email" name="email" type="email" required />
                                {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                            </div>

                            <div>
                                <Label htmlFor="phone">Phone (default password)</Label>
                                <Input id="phone" name="phone" placeholder="0551234567" required />
                                {errors.phone && <p className="text-sm text-destructive">{errors.phone}</p>}
                            </div>

                            <div>
                                <Label htmlFor="location">Location</Label>
                                <Input id="location" name="location" required />
                                {errors.location && <p className="text-sm text-destructive">{errors.location}</p>}
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding…' : 'Add member'}
                                </Button>
                                <Button type="button" variant="ghost" asChild>
                                    <Link href="/field-agent/team">Cancel</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
