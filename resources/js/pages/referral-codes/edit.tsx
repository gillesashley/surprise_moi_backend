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

interface ReferralCode {
    id: number;
    code: string;
    influencer: {
        id: number;
        name: string;
        email: string;
    };
    description?: string;
    registration_bonus: number;
    commission_rate: number;
    commission_duration_months: number;
    discount_percentage: number;
    is_active: boolean;
    usage_count: number;
    max_usages?: number;
    expires_at?: string;
}

interface Props {
    code: ReferralCode;
    influencers: Array<{
        id: number;
        name: string;
        email: string;
    }>;
}

export default function ReferralCodeEdit({ code, influencers }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Referral Codes',
            href: '/dashboard/referral-codes',
        },
        {
            title: code.code,
            href: `/dashboard/referral-codes/${code.id}`,
        },
        {
            title: 'Edit',
            href: `/dashboard/referral-codes/${code.id}/edit`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Referral Code: ${code.code}`} />

            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Typography variant="h4" sx={{ fontWeight: 700 }}>Edit Referral Code</Typography>
                    <Button variant="outline" asChild>
                        <Link href={`/dashboard/referral-codes/${code.id}`}>
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Details
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Referral Code Details</CardTitle>
                        <CardDescription>
                            Update the referral code. Note: Code and influencer
                            cannot be changed.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action={`/dashboard/referral-codes/${code.id}`}
                            method="put"
                        >
                            {({ errors, processing }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label>Referral Code</Label>
                                        <Input
                                            value={code.code}
                                            disabled
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Code cannot be changed after
                                            creation
                                        </Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label>Influencer</Label>
                                        <Input
                                            value={`${code.influencer.name} (${code.influencer.email})`}
                                            disabled
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Influencer assignment cannot be
                                            changed after creation
                                        </Typography>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="description">
                                            Description
                                        </Label>
                                        <Textarea
                                            id="description"
                                            name="description"
                                            rows={3}
                                            defaultValue={
                                                code.description || ''
                                            }
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
                                                defaultValue={
                                                    code.registration_bonus
                                                }
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
                                                defaultValue={
                                                    code.commission_rate
                                                }
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
                                            defaultValue={
                                                code.discount_percentage
                                            }
                                            required
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Discount applied to vendors using
                                            this referral code
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
                                            defaultValue={
                                                code.commission_duration_months
                                            }
                                            required
                                        />
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            How long the influencer will receive
                                            commissions from referred vendors
                                            (1-120 months)
                                        </Typography>
                                        {errors.commission_duration_months && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {
                                                    errors.commission_duration_months
                                                }
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
                                                defaultValue={
                                                    code.max_usages || ''
                                                }
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
                                                defaultValue={
                                                    code.expires_at
                                                        ? code.expires_at.split(
                                                              'T',
                                                          )[0]
                                                        : ''
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

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="is_active">
                                            Status *
                                        </Label>
                                        <Select
                                            name="is_active"
                                            defaultValue={
                                                code.is_active ? '1' : '0'
                                            }
                                            required
                                        >
                                            <SelectTrigger id="is_active">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">
                                                    Active
                                                </SelectItem>
                                                <SelectItem value="0">
                                                    Inactive
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.is_active && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.is_active}
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
                                                href={`/dashboard/referral-codes/${code.id}`}
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
                                                : 'Update Referral Code'}
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
