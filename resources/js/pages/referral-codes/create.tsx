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

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-3xl font-bold">Create Referral Code</h1>
                <Button variant="outline" asChild>
                    <Link href="/dashboard/referral-codes">
                        <ArrowLeft className="mr-2 size-4" />
                        Back to Referral Codes
                    </Link>
                </Button>
            </div>

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
                        className="space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="space-y-2">
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
                                        <p className="text-sm text-red-600">
                                            {errors.influencer_id}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
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
                                            placeholder="0.00"
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
                                            placeholder="0.0"
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
                                        placeholder="0.0"
                                        required
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Discount applied to vendors using this
                                        referral code
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
                                        defaultValue="12"
                                        required
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        How long the influencer will receive
                                        commissions from referred vendors (1-120
                                        months)
                                    </p>
                                    {errors.commission_duration_months && (
                                        <p className="text-sm text-red-600">
                                            {errors.commission_duration_months}
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
                                            min={
                                                new Date(Date.now() + 86400000)
                                                    .toISOString()
                                                    .split('T')[0]
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

                                <div className="flex justify-end gap-3">
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
                                </div>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
