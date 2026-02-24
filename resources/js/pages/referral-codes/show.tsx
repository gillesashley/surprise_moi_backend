import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle, Copy, XCircle } from 'lucide-react';
import { useState } from 'react';

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
    created_at: string;
    referrals?: Array<{
        id: number;
        vendor: {
            id: number;
            name: string;
            email: string;
        };
        status: string;
        created_at: string;
    }>;
}

interface Props {
    code: ReferralCode;
}

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-GH', {
        style: 'currency',
        currency: 'GHS',
    }).format(amount);
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

export default function ReferralCodeShow({ code }: Props) {
    const [copied, setCopied] = useState(false);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Referral Codes',
            href: '/dashboard/referral-codes',
        },
        {
            title: code.code,
            href: `/dashboard/referral-codes/${code.id}`,
        },
    ];

    const copyToClipboard = async () => {
        await navigator.clipboard.writeText(code.code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Referral Code: ${code.code}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold">
                        Referral Code Details
                    </h1>
                    <div className="flex gap-3">
                        <Button variant="outline" asChild>
                            <Link href="/dashboard/referral-codes">
                                <ArrowLeft className="mr-2 size-4" />
                                Back to Referral Codes
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link
                                href={`/dashboard/referral-codes/${code.id}/edit`}
                            >
                                Edit Code
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Code Information</CardTitle>
                            <CardDescription>
                                Basic details about this referral code
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Referral Code
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    <code className="rounded bg-muted px-3 py-2 font-mono text-xl font-bold">
                                        {code.code}
                                    </code>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={copyToClipboard}
                                    >
                                        <Copy className="size-4" />
                                    </Button>
                                    {copied && (
                                        <span className="text-sm font-medium text-green-600">
                                            Copied!
                                        </span>
                                    )}
                                </div>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Status
                                </label>
                                <div className="mt-1 flex items-center gap-2">
                                    {code.is_active ? (
                                        <>
                                            <CheckCircle className="size-5 text-green-600" />
                                            <span className="font-semibold text-green-600">
                                                Active
                                            </span>
                                        </>
                                    ) : (
                                        <>
                                            <XCircle className="size-5 text-gray-600" />
                                            <span className="font-semibold text-gray-600">
                                                Inactive
                                            </span>
                                        </>
                                    )}
                                </div>
                            </div>
                            {code.description && (
                                <div>
                                    <label className="text-sm font-medium text-muted-foreground">
                                        Description
                                    </label>
                                    <p className="mt-1 text-sm">
                                        {code.description}
                                    </p>
                                </div>
                            )}
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Created
                                </label>
                                <p className="mt-1 text-sm">
                                    {formatDate(code.created_at)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Influencer</CardTitle>
                            <CardDescription>
                                Who this code is assigned to
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Name
                                </label>
                                <p className="mt-1 text-lg font-semibold">
                                    {code.influencer.name}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Email
                                </label>
                                <p className="mt-1 text-sm">
                                    {code.influencer.email}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Rewards Structure</CardTitle>
                            <CardDescription>
                                Commission and bonus details
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Registration Bonus
                                </label>
                                <p className="mt-1 text-2xl font-bold">
                                    {formatCurrency(code.registration_bonus)}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Vendor Discount
                                </label>
                                <p className="mt-1 text-2xl font-bold text-green-600">
                                    {code.discount_percentage}%
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Commission Rate
                                </label>
                                <p className="mt-1 text-2xl font-bold">
                                    {code.commission_rate}%
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Commission Duration
                                </label>
                                <p className="mt-1 text-lg font-semibold">
                                    {code.commission_duration_months} months
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Usage Information</CardTitle>
                            <CardDescription>
                                Limits and expiration
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Usage Count
                                </label>
                                <p className="mt-1 text-2xl font-bold">
                                    {code.usage_count}
                                    {code.max_usages && ` / ${code.max_usages}`}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Maximum Usages
                                </label>
                                <p className="mt-1 text-lg font-semibold">
                                    {code.max_usages || 'Unlimited'}
                                </p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-muted-foreground">
                                    Expiration Date
                                </label>
                                <p className="mt-1 text-sm">
                                    {code.expires_at
                                        ? formatDate(code.expires_at)
                                        : 'No expiration'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {code.referrals && code.referrals.length > 0 && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Referrals</CardTitle>
                            <CardDescription>
                                Vendors who used this referral code
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Vendor</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Referred On</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {code.referrals.map((referral) => (
                                        <TableRow key={referral.id}>
                                            <TableCell className="font-medium">
                                                {referral.vendor.name}
                                            </TableCell>
                                            <TableCell>
                                                {referral.vendor.email}
                                            </TableCell>
                                            <TableCell>
                                                <span className="capitalize">
                                                    {referral.status}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {formatDate(
                                                    referral.created_at,
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
