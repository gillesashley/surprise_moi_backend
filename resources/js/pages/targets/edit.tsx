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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Target {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    target_type: string;
    target_value: number;
    current_value: number;
    bonus_amount: number;
    overachievement_rate: number;
    period_type: string;
    start_date: string;
    end_date: string;
    status: string;
    notes?: string;
}

interface Props {
    target: Target;
    users: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
    }>;
    targetTypes: Record<string, string>;
    periodTypes: string[];
}

export default function TargetEdit({
    target,
    users,
    targetTypes,
    periodTypes,
}: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Targets',
            href: '/dashboard/targets',
        },
        {
            title: `Target #${target.id}`,
            href: `/dashboard/targets/${target.id}`,
        },
        {
            title: 'Edit',
            href: `/dashboard/targets/${target.id}/edit`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Target #${target.id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold">Edit Target</h1>
                    <Button variant="outline" asChild>
                        <Link href={`/dashboard/targets/${target.id}`}>
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Details
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Target Information</CardTitle>
                        <CardDescription>
                            Update the target details. Note: User and target
                            type cannot be changed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/dashboard/targets/${target.id}`}
                            method="put"
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label>Assigned To</Label>
                                        <Input
                                            value={`${target.user.name} (${target.user.email})`}
                                            disabled
                                            className="bg-muted"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            User assignment cannot be changed
                                            after creation
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Target Type</Label>
                                        <Input
                                            value={
                                                targetTypes[target.target_type]
                                            }
                                            disabled
                                            className="bg-muted"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Target type cannot be changed after
                                            creation
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="target_value">
                                            Target Value *
                                        </Label>
                                        <Input
                                            id="target_value"
                                            name="target_value"
                                            type="number"
                                            step="0.01"
                                            defaultValue={target.target_value}
                                            required
                                        />
                                        {errors.target_value && (
                                            <p className="text-sm text-red-600">
                                                {errors.target_value}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="bonus_amount">
                                            Bonus Amount (GHS) *
                                        </Label>
                                        <Input
                                            id="bonus_amount"
                                            name="bonus_amount"
                                            type="number"
                                            step="0.01"
                                            defaultValue={target.bonus_amount}
                                            required
                                        />
                                        {errors.bonus_amount && (
                                            <p className="text-sm text-red-600">
                                                {errors.bonus_amount}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="overachievement_rate">
                                            Overachievement Rate (%)
                                        </Label>
                                        <Input
                                            id="overachievement_rate"
                                            name="overachievement_rate"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="100"
                                            defaultValue={
                                                target.overachievement_rate
                                            }
                                        />
                                        {errors.overachievement_rate && (
                                            <p className="text-sm text-red-600">
                                                {errors.overachievement_rate}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="end_date">
                                            End Date *
                                        </Label>
                                        <Input
                                            id="end_date"
                                            name="end_date"
                                            type="date"
                                            defaultValue={
                                                target.end_date.split('T')[0]
                                            }
                                            required
                                        />
                                        {errors.end_date && (
                                            <p className="text-sm text-red-600">
                                                {errors.end_date}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="status">Status *</Label>
                                        <Select
                                            name="status"
                                            defaultValue={target.status}
                                            required
                                        >
                                            <SelectTrigger id="status">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">
                                                    Active
                                                </SelectItem>
                                                <SelectItem value="completed">
                                                    Completed
                                                </SelectItem>
                                                <SelectItem value="expired">
                                                    Expired
                                                </SelectItem>
                                                <SelectItem value="cancelled">
                                                    Cancelled
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.status && (
                                            <p className="text-sm text-red-600">
                                                {errors.status}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <Textarea
                                            id="notes"
                                            name="notes"
                                            rows={4}
                                            defaultValue={target.notes || ''}
                                            placeholder="Optional notes about this target..."
                                        />
                                        {errors.notes && (
                                            <p className="text-sm text-red-600">
                                                {errors.notes}
                                            </p>
                                        )}
                                    </div>

                                    <div className="flex justify-end gap-3">
                                        <Button
                                            variant="outline"
                                            type="button"
                                            asChild
                                        >
                                            <Link
                                                href={`/dashboard/targets/${target.id}`}
                                            >
                                                Cancel
                                            </Link>
                                        </Button>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Updating...'
                                                : 'Update Target'}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
