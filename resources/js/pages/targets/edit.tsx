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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h4" sx={{ fontWeight: 700 }}>Edit Target</Typography>
                    <Button variant="outline" asChild>
                        <Link href={`/dashboard/targets/${target.id}`}>
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Details
                        </Link>
                    </Button>
                </Box>

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
                        >
                            {({ errors, processing }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label>Assigned To</Label>
                                        <Input
                                            value={`${target.user.name} (${target.user.email})`}
                                            disabled
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            User assignment cannot be changed
                                            after creation
                                        </Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label>Target Type</Label>
                                        <Input
                                            value={
                                                targetTypes[target.target_type]
                                            }
                                            disabled
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Target type cannot be changed after
                                            creation
                                        </Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.target_value}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.bonus_amount}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.overachievement_rate}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.end_date}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.status}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="notes">Notes</Label>
                                        <Textarea
                                            id="notes"
                                            name="notes"
                                            rows={4}
                                            defaultValue={target.notes || ''}
                                            placeholder="Optional notes about this target..."
                                        />
                                        {errors.notes && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.notes}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
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
                                    </Box>
                                </Box>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            </Box>
        </AppLayout>
    );
}
