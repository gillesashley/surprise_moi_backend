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
    store as couponStore,
} from '@/routes/dashboard/coupons';
import { type BreadcrumbItem } from '@/types';
import { Form, Head, Link } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Coupons',
        href: couponsIndex().url,
    },
    {
        title: 'Create Coupon',
        href: couponStore.url(),
    },
];

export default function CouponCreate() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Coupon" />
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
                        <CardTitle>Create Coupon</CardTitle>
                        <CardDescription>Add a new discount code</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Form action={couponStore.url()} method="post">
                            {({ errors, processing }) => (
                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                    {/* Code */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="code">Code</Label>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>The unique code customers enter at checkout to apply the discount</Typography>
                                        <Input id="code" name="code" placeholder="e.g. SUMMER20" required onInput={(e) => { (e.target as HTMLInputElement).value = (e.target as HTMLInputElement).value.toUpperCase(); }} />
                                        {errors.code && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.code}</Typography>
                                        )}
                                    </Box>

                                    {/* Title */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="title">Title</Label>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>A friendly name shown to customers when the coupon is applied</Typography>
                                        <Input id="title" name="title" placeholder="e.g. Summer Sale 20% Off" />
                                        {errors.title && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.title}</Typography>
                                        )}
                                    </Box>

                                    {/* Description */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="description">Description</Label>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Internal notes about this coupon — not visible to customers</Typography>
                                        <Box
                                            component="textarea"
                                            id="description"
                                            name="description"
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

                                    {/* Type & Value side by side */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="type">Type</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>How the discount is calculated</Typography>
                                            <Select name="type" defaultValue="percentage" required>
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
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Discount amount — percentage (e.g. 20) or fixed GHS amount</Typography>
                                            <Input id="value" name="value" type="number" step="0.01" min="0" required />
                                            {errors.value && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.value}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Min Purchase & Max Discount side by side */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="min_purchase_amount">Min Purchase Amount (GHS)</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Order must reach this total before the coupon can be applied</Typography>
                                            <Input id="min_purchase_amount" name="min_purchase_amount" type="number" step="0.01" min="0" />
                                            {errors.min_purchase_amount && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.min_purchase_amount}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="max_discount_amount">Max Discount Amount (GHS)</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Caps the discount — useful for percentage coupons on large orders</Typography>
                                            <Input id="max_discount_amount" name="max_discount_amount" type="number" step="0.01" min="0" />
                                            {errors.max_discount_amount && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.max_discount_amount}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Usage Limit & Per-User Limit side by side */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="usage_limit">Usage Limit</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Total number of times this coupon can be used across all customers. Leave blank for unlimited.</Typography>
                                            <Input id="usage_limit" name="usage_limit" type="number" min="1" />
                                            {errors.usage_limit && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.usage_limit}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="user_limit_per_user">Per-User Limit</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>How many times a single customer can use this coupon</Typography>
                                            <Input id="user_limit_per_user" name="user_limit_per_user" type="number" min="1" defaultValue="1" required />
                                            {errors.user_limit_per_user && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.user_limit_per_user}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Valid From & Valid Until side by side */}
                                    <Box sx={{ display: 'flex', gap: 2 }}>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="valid_from">Valid From</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Date when the coupon becomes active</Typography>
                                            <Input id="valid_from" name="valid_from" type="date" required />
                                            {errors.valid_from && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.valid_from}</Typography>
                                            )}
                                        </Box>
                                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1, flex: 1 }}>
                                            <Label htmlFor="valid_until">Valid Until</Label>
                                            <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Date when the coupon expires</Typography>
                                            <Input id="valid_until" name="valid_until" type="date" required />
                                            {errors.valid_until && (
                                                <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>{errors.valid_until}</Typography>
                                            )}
                                        </Box>
                                    </Box>

                                    {/* Applicable To */}
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="applicable_to">Applicable To</Label>
                                        <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>Which types of items this coupon can be used on</Typography>
                                        <Select name="applicable_to" defaultValue="all" required>
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
                                            defaultChecked
                                            sx={{ width: 16, height: 16 }}
                                        />
                                        <Label htmlFor="is_active">Active — uncheck to save as a draft without making it available to customers</Label>
                                    </Box>

                                    {/* Actions */}
                                    <Box sx={{ display: 'flex', gap: 1 }}>
                                        <Button type="submit" disabled={processing}>
                                            {processing ? 'Creating...' : 'Create Coupon'}
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
