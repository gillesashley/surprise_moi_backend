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

interface Props {
    influencers: Array<{
        id: number;
        name: string;
        email: string;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Referral Codes',
        href: '/dashboard/referral-codes',
    },
    {
        title: 'Create',
        href: '/dashboard/referral-codes/create',
    },
];

export default function ReferralCodeCreate({ influencers }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Referral Code" />

            <Box sx={{ mb: 3, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h4" sx={{ fontWeight: 700 }}>Create Referral Code</Typography>
                <Button variant="outline" asChild>
                    <Link href="/dashboard/referral-codes">
                        <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                        Back to Referral Codes
                    </Link>
                </Button>
            </Box>

            <Card>
                <CardHeader>
                    <CardTitle>Referral Code Details</CardTitle>
                    <CardDescription>
                        Create a new referral code for an influencer
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action="/referral-codes"
                        method="post"
                    >
                        {({ errors, processing }) => (
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="influencer_id">
                                        Influencer *
                                    </Label>
                                    <Select name="influencer_id" required>
                                        <SelectTrigger id="influencer_id">
                                            <SelectValue placeholder="Select influencer" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {influencers.map((influencer) => (
                                                <SelectItem
                                                    key={influencer.id}
                                                    value={influencer.id.toString()}
                                                >
                                                    {influencer.name} (
                                                    {influencer.email})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.influencer_id && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.influencer_id}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="description">
                                        Description
                                    </Label>
                                    <Textarea
                                        id="description"
                                        name="description"
                                        rows={3}
                                        placeholder="Optional description for this referral code..."
                                    />
                                    {errors.description && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.description}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="registration_bonus">
                                            Registration Bonus (GHS) *
                                        </Label>
                                        <Input
                                            id="registration_bonus"
                                            name="registration_bonus"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            required
                                        />
                                        {errors.registration_bonus && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.registration_bonus}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="commission_rate">
                                            Commission Rate (%) *
                                        </Label>
                                        <Input
                                            id="commission_rate"
                                            name="commission_rate"
                                            type="number"
                                            step="0.1"
                                            min="0"
                                            max="100"
                                            placeholder="0.0"
                                            required
                                        />
                                        {errors.commission_rate && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.commission_rate}
                                            </Typography>
                                        )}
                                    </Box>
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="discount_percentage">
                                        Discount Percentage (%) *
                                    </Label>
                                    <Input
                                        id="discount_percentage"
                                        name="discount_percentage"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100"
                                        placeholder="0.0"
                                        required
                                    />
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        Discount applied to vendors using this
                                        referral code
                                    </Typography>
                                    {errors.discount_percentage && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.discount_percentage}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                    <Label htmlFor="commission_duration_months">
                                        Commission Duration (Months) *
                                    </Label>
                                    <Input
                                        id="commission_duration_months"
                                        name="commission_duration_months"
                                        type="number"
                                        min="1"
                                        max="120"
                                        defaultValue="12"
                                        required
                                    />
                                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                        How long the influencer will receive
                                        commissions from referred vendors (1-120
                                        months)
                                    </Typography>
                                    {errors.commission_duration_months && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.commission_duration_months}
                                        </Typography>
                                    )}
                                </Box>

                                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="max_usages">
                                            Maximum Usages
                                        </Label>
                                        <Input
                                            id="max_usages"
                                            name="max_usages"
                                            type="number"
                                            min="1"
                                            placeholder="Unlimited"
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Leave empty for unlimited usage
                                        </Typography>
                                        {errors.max_usages && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.max_usages}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="expires_at">
                                            Expiration Date
                                        </Label>
                                        <Input
                                            id="expires_at"
                                            name="expires_at"
                                            type="date"
                                            min={
                                                new Date(Date.now() + 86400000)
                                                    .toISOString()
                                                    .split('T')[0]
                                            }
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Leave empty for no expiration
                                        </Typography>
                                        {errors.expires_at && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.expires_at}
                                            </Typography>
                                        )}
                                    </Box>
                                </Box>

                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
                                    <Button
                                        variant="outline"
                                        type="button"
                                        asChild
                                    >
                                        <Link href="/dashboard/referral-codes">
                                            Cancel
                                        </Link>
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Creating...'
                                            : 'Create Referral Code'}
                                    </Button>
                                </Box>
                            </Box>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
