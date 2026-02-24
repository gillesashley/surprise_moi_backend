import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface Props {
    users: User[];
    targetTypes: Record<string, string>;
    periodTypes: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Targets',
        href: '/targets',
    },
    {
        title: 'Create',
        href: '/targets/create',
    },
];

export default function TargetCreate({
    users,
    targetTypes,
    periodTypes,
}: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Target" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/targets">
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Targets
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Create New Target</CardTitle>
                        <CardDescription>
                            Assign a new target to a field agent or marketer
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action="/targets" method="post" resetOnSuccess>
                            {({ errors, processing }) => (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="user_id">
                                            Assign To
                                        </Label>
                                        <select
                                            id="user_id"
                                            name="user_id"
                                            required
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="">
                                                Select a user...
                                            </option>
                                            {users.map((user) => (
                                                <option
                                                    key={user.id}
                                                    value={user.id}
                                                >
                                                    {user.name} ({user.role}) -{' '}
                                                    {user.email}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.user_id && (
                                            <p className="text-sm text-destructive">
                                                {errors.user_id}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="target_type">
                                            Target Type
                                        </Label>
                                        <select
                                            id="target_type"
                                            name="target_type"
                                            required
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="">
                                                Select target type...
                                            </option>
                                            {Object.entries(targetTypes).map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        {errors.target_type && (
                                            <p className="text-sm text-destructive">
                                                {errors.target_type}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="target_value">
                                                Target Value
                                            </Label>
                                            <Input
                                                id="target_value"
                                                name="target_value"
                                                type="number"
                                                step="0.01"
                                                required
                                                placeholder="e.g., 10 or 10000"
                                            />
                                            {errors.target_value && (
                                                <p className="text-sm text-destructive">
                                                    {errors.target_value}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="bonus_amount">
                                                Bonus Amount (GH₵)
                                            </Label>
                                            <Input
                                                id="bonus_amount"
                                                name="bonus_amount"
                                                type="number"
                                                step="0.01"
                                                required
                                                placeholder="e.g., 500"
                                            />
                                            {errors.bonus_amount && (
                                                <p className="text-sm text-destructive">
                                                    {errors.bonus_amount}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="overachievement_rate">
                                            Overachievement Rate (%)
                                        </Label>
                                        <Input
                                            id="overachievement_rate"
                                            name="overachievement_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            placeholder="e.g., 10"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Additional bonus percentage for
                                            exceeding the target
                                        </p>
                                        {errors.overachievement_rate && (
                                            <p className="text-sm text-destructive">
                                                {errors.overachievement_rate}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="period_type">
                                            Period Type
                                        </Label>
                                        <select
                                            id="period_type"
                                            name="period_type"
                                            required
                                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                        >
                                            <option value="">
                                                Select period...
                                            </option>
                                            {periodTypes.map((period) => (
                                                <option
                                                    key={period}
                                                    value={period}
                                                >
                                                    {period
                                                        .charAt(0)
                                                        .toUpperCase() +
                                                        period.slice(1)}
                                                </option>
                                            ))}
                                        </select>
                                        {errors.period_type && (
                                            <p className="text-sm text-destructive">
                                                {errors.period_type}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="start_date">
                                                Start Date
                                            </Label>
                                            <Input
                                                id="start_date"
                                                name="start_date"
                                                type="date"
                                                required
                                            />
                                            {errors.start_date && (
                                                <p className="text-sm text-destructive">
                                                    {errors.start_date}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="end_date">
                                                End Date
                                            </Label>
                                            <Input
                                                id="end_date"
                                                name="end_date"
                                                type="date"
                                                required
                                            />
                                            {errors.end_date && (
                                                <p className="text-sm text-destructive">
                                                    {errors.end_date}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes">
                                            Notes (Optional)
                                        </Label>
                                        <textarea
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-base ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-hidden disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                            placeholder="Additional notes about this target..."
                                        />
                                        {errors.notes && (
                                            <p className="text-sm text-destructive">
                                                {errors.notes}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Creating...'
                                                : 'Create Target'}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            asChild
                                        >
                                            <Link href="/targets">Cancel</Link>
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
