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
import AppLayout from '@/layouts/app-layout';
import {
    index as couponsIndex,
    update as couponUpdate,
} from '@/routes/dashboard/coupons';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft } from 'lucide-react';

interface Coupon {
    id: number;
    code: string;
    title: string | null;
    description: string | null;
    type: 'percentage' | 'fixed' | 'cashback';
    value: string;
    min_purchase_amount: string | null;
    max_discount_amount: string | null;
    usage_limit: number | null;
    used_count: number;
    user_limit_per_user: number;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
    applicable_to: string;
}

interface Props {
    coupon: Coupon;
}

const breadcrumbs = (coupon: Coupon): BreadcrumbItem[] => [
    {
        title: 'Coupons',
        href: couponsIndex().url,
    },
    {
        title: coupon.code,
        href: couponUpdate.url(coupon.id),
    },
];

const dateValue = (dateStr: string | null): string => {
    if (!dateStr) return '';
    return dateStr.slice(0, 10);
};

export default function CouponEdit({ coupon }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs(coupon)}>
            <Head title={`Edit: ${coupon.code}`} />
            <Box sx={{ display: 'flex', height: '100%', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={couponsIndex.url()}>
                            <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                            Back to Coupons
                        </Link>
                    </Button>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>Edit Coupon</CardTitle>
                        <CardDescription>Update coupon details</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={couponUpdate.url(coupon.id)} method="post">
                            {({ errors, processing, wasSuccessful }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    <input type="hidden" name="_method" value="PUT" />

                                    {/* Code */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="code">Code</Label>
                                        <Input id="code" name="code" defaultValue={coupon.code} required />
                                        {errors.code && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.code}</Typography>
                                        )}
                                    </Box>

                                    {/* Title */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="title">Title</Label>
                                        <Input id="title" name="title" defaultValue={coupon.title || ''} />
                                        {errors.title && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.title}</Typography>
                                        )}
                                    </Box>

                                    {/* Description */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="description">Description</Label>
                                        <Box
                                            component="textarea"
                                            id="description"
                                            name="description"
                                            defaultValue={coupon.description || ''}
                                            rows={3}
                                            placeholder="Internal notes about this coupon"
                                            sx={{
                                                display: 'flex',
                                                minHeight: 80,
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
                                        {errors.description && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.description}</Typography>
                                        )}
                                    </Box>

                                    {/* Type & Value */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="type">Type</Label>
                                            <Select name="type" defaultValue={coupon.type} required>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="percentage">Percentage</SelectItem>
                                                    <SelectItem value="fixed">Fixed Amount</SelectItem>
                                                    <SelectItem value="cashback">Cashback</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.type && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.type}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="value">Value</Label>
                                            <Input id="value" name="value" type="number" step="0.01" min="0" defaultValue={coupon.value} required />
                                            {errors.value && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.value}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Min Purchase & Max Discount */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="min_purchase_amount">Min Purchase Amount (GHS)</Label>
                                            <Input id="min_purchase_amount" name="min_purchase_amount" type="number" step="0.01" min="0" defaultValue={coupon.min_purchase_amount || ''} />
                                            {errors.min_purchase_amount && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.min_purchase_amount}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="max_discount_amount">Max Discount Amount (GHS)</Label>
                                            <Input id="max_discount_amount" name="max_discount_amount" type="number" step="0.01" min="0" defaultValue={coupon.max_discount_amount || ''} />
                                            {errors.max_discount_amount && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.max_discount_amount}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Usage Limits */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="usage_limit">Usage Limit (blank = unlimited)</Label>
                                            <Input id="usage_limit" name="usage_limit" type="number" min="1" defaultValue={coupon.usage_limit ?? ''} />
                                            {errors.usage_limit && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.usage_limit}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="user_limit_per_user">Per-User Limit</Label>
                                            <Input id="user_limit_per_user" name="user_limit_per_user" type="number" min="1" defaultValue={coupon.user_limit_per_user} required />
                                            {errors.user_limit_per_user && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.user_limit_per_user}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Validity Dates */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="valid_from">Valid From</Label>
                                            <Input id="valid_from" name="valid_from" type="date" defaultValue={dateValue(coupon.valid_from)} required />
                                            {errors.valid_from && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.valid_from}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="valid_until">Valid Until</Label>
                                            <Input id="valid_until" name="valid_until" type="date" defaultValue={dateValue(coupon.valid_until)} required />
                                            {errors.valid_until && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.valid_until}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Applicable To */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="applicable_to">Applicable To</Label>
                                        <Select name="applicable_to" defaultValue={coupon.applicable_to} required>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Items</SelectItem>
                                                <SelectItem value="products">Products Only</SelectItem>
                                                <SelectItem value="services">Services Only</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.applicable_to && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.applicable_to}</Typography>
                                        )}
                                    </Box>

                                    {/* Is Active */}
                                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
                                        <Box
                                            component="input"
                                            type="checkbox"
                                            id="is_active"
                                            name="is_active"
                                            value="1"
                                            defaultChecked={coupon.is_active}
                                            sx={{ width: 16, height: 16 }}
                                        />
                                        <Label htmlFor="is_active">Active</Label>
                                    </Box>

                                    {wasSuccessful && (
                                        <Typography sx={{ fontSize: '0.875rem', color: 'success.main' }}>
                                            Coupon updated successfully!
                                        </Typography>
                                    )}

                                    {/* Actions */}
                                    <Box sx={{ display: 'flex', gap: 1 }}>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Saving...' : 'Save Changes'}
                                        </Button>
                                        <Button type="button" variant="outline" asChild>
                                            <Link href={couponsIndex.url()}>Cancel</Link>
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
