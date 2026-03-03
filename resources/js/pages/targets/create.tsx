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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
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
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/targets">
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Targets
                        </Link>
                    </Button>
                </Box>

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
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="user_id">
                                            Assign To
                                        </Label>
                                        <Box
                                            component="select"
                                            id="user_id"
                                            name="user_id"
                                            required
                                            sx={{
                                                display: 'flex',
                                                height: 40,
                                                width: '100%',
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': { outline: 'none', ring: 2, ringColor: 'primary.main' },
                                                '&:disabled': { cursor: 'not-allowed', opacity: 0.5 },
                                            }}
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
                                        </Box>
                                        {errors.user_id && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.user_id}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="target_type">
                                            Target Type
                                        </Label>
                                        <Box
                                            component="select"
                                            id="target_type"
                                            name="target_type"
                                            required
                                            sx={{
                                                display: 'flex',
                                                height: 40,
                                                width: '100%',
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': { outline: 'none', ring: 2, ringColor: 'primary.main' },
                                                '&:disabled': { cursor: 'not-allowed', opacity: 0.5 },
                                            }}
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
                                        </Box>
                                        {errors.target_type && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.target_type}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' }, gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                    {errors.target_value}
                                                </Typography>
                                            )}
                                        </Box>

                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                    {errors.bonus_amount}
                                                </Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Additional bonus percentage for
                                            exceeding the target
                                        </Typography>
                                        {errors.overachievement_rate && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.overachievement_rate}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="period_type">
                                            Period Type
                                        </Label>
                                        <Box
                                            component="select"
                                            id="period_type"
                                            name="period_type"
                                            required
                                            sx={{
                                                display: 'flex',
                                                height: 40,
                                                width: '100%',
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': { outline: 'none', ring: 2, ringColor: 'primary.main' },
                                                '&:disabled': { cursor: 'not-allowed', opacity: 0.5 },
                                            }}
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
                                        </Box>
                                        {errors.period_type && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.period_type}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', md: 'repeat(2, 1fr)' }, gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                    {errors.start_date}
                                                </Typography>
                                            )}
                                        </Box>

                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                    {errors.end_date}
                                                </Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="notes">
                                            Notes (Optional)
                                        </Label>
                                        <Box
                                            component="textarea"
                                            id="notes"
                                            name="notes"
                                            rows={3}
                                            placeholder="Additional notes about this target..."
                                            sx={{
                                                display: 'flex',
                                                width: '100%',
                                                borderRadius: 1,
                                                border: 1,
                                                borderColor: 'divider',
                                                bgcolor: 'background.paper',
                                                px: 1.5,
                                                py: 1,
                                                fontSize: { xs: '1rem', md: '0.875rem' },
                                                '&:focus-visible': { outline: 'none', ring: 2, ringColor: 'primary.main' },
                                                '&:disabled': { cursor: 'not-allowed', opacity: 0.5 },
                                            }}
                                        />
                                        {errors.notes && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.notes}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', gap: 1 }}>
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
