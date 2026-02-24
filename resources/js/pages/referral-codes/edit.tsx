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

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold">Edit Referral Code</h1>
                    <Button variant="outline" asChild>
                        <Link href={`/dashboard/referral-codes/${code.id}`}>
                            <ArrowLeft className="mr-2 size-4" />
                            Back to Details
                        </Link>
                    </Button>
                </div>

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
                            className="space-y-6"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="space-y-2">
                                        <Label>Referral Code</Label>
                                        <Input
                                            value={code.code}
                                            disabled
                                            className="bg-muted font-mono"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Code cannot be changed after
                                            creation
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        <Label>Influencer</Label>
                                        <Input
                                            value={`${code.influencer.name} (${code.influencer.email})`}
                                            disabled
                                            className="bg-muted"
                                        />
                                        <p className="text-sm text-muted-foreground">
                                            Influencer assignment cannot be
                                            changed after creation
                                        </p>
                                    </div>

                                    <div className="space-y-2">
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
                                            <p className="text-sm text-red-600">
                                                {errors.description}
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-6 md:grid-cols-2">
                                        <div className="space-y-2">
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
                                                <p className="text-sm text-red-600">
                                                    {errors.registration_bonus}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
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
                                                <p className="text-sm text-red-600">
                                                    {errors.commission_rate}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
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
                                        <p className="text-sm text-muted-foreground">
                                            Discount applied to vendors using
                                            this referral code
                                        </p>
                                        {errors.discount_percentage && (
                                            <p className="text-sm text-red-600">
                                                {errors.discount_percentage}
                                            </p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
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
                                        <p className="text-sm text-muted-foreground">
                                            How long the influencer will receive
                                            commissions from referred vendors
                                            (1-120 months)
                                        </p>
                                        {errors.commission_duration_months && (
                                            <p className="text-sm text-red-600">
                                                {
                                                    errors.commission_duration_months
                                                }
                                            </p>
                                        )}
                                    </div>

                                    <div className="grid gap-6 md:grid-cols-2">
                                        <div className="space-y-2">
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
                                            <p className="text-sm text-muted-foreground">
                                                Leave empty for unlimited usage
                                            </p>
                                            {errors.max_usages && (
                                                <p className="text-sm text-red-600">
                                                    {errors.max_usages}
                                                </p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
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
                                            <p className="text-sm text-muted-foreground">
                                                Leave empty for no expiration
                                            </p>
                                            {errors.expires_at && (
                                                <p className="text-sm text-red-600">
                                                    {errors.expires_at}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
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
                                            <p className="text-sm text-red-600">
                                                {errors.is_active}
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
