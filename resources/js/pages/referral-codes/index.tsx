import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Copy, Eye, Pencil, Plus, Power, PowerOff, Trash2 } from 'lucide-react';

interface ReferralCode {
    id: number;
    code: string;
    influencer: {
        id: number;
        name: string;
        email: string;
    };
    is_active: boolean;
    usage_count: number;
    max_usages: number | null;
    commission_rate: number;
    registration_bonus: number;
    discount_percentage: number;
    expires_at: string | null;
}

interface PaginatedCodes {
    data: ReferralCode[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    codes: PaginatedCodes;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Referral Codes',
        href: '/dashboard/referral-codes',
    },
];

export default function ReferralCodesIndex({ codes }: Props) {
    const handleDelete = (codeId: number, code: string) => {
        if (
            confirm(
                `Are you sure you want to delete referral code "${code}"? This action cannot be undone.`,
            )
        ) {
            router.delete(`/dashboard/referral-codes/${codeId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleToggle = (codeId: number) => {
        router.post(
            `/dashboard/referral-codes/${codeId}/toggle`,
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const copyToClipboard = (code: string) => {
        navigator.clipboard.writeText(code);
        alert(`Code "${code}" copied to clipboard!`);
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/referral-codes',
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Referral Codes" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Referral Codes Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage referral codes for influencers
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/referral-codes/create">
                            <Plus className="mr-2 size-4" />
                            Create Code
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Referral Codes</CardTitle>
                        <CardDescription>
                            View and manage all referral codes
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2 text-left text-sm font-medium">
                                            Code
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Influencer
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Discount
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Usage
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Commission
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Bonus
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Status
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {codes.data.map((code) => (
                                        <tr
                                            key={code.id}
                                            className="border-b hover:bg-muted/50"
                                        >
                                            <td className="p-2">
                                                <div className="flex items-center gap-2">
                                                    <code className="rounded bg-muted px-2 py-1 font-mono text-sm font-semibold">
                                                        {code.code}
                                                    </code>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            copyToClipboard(
                                                                code.code,
                                                            )
                                                        }
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Copy className="size-4" />
                                                    </button>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {code.influencer.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {code.influencer.email}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                <span className="font-semibold text-green-600">
                                                    {code.discount_percentage}%
                                                </span>
                                            </td>
                                            <td className="p-2">
                                                {code.usage_count} /{' '}
                                                {code.max_usages || '∞'}
                                            </td>
                                            <td className="p-2">
                                                {code.commission_rate}%
                                            </td>
                                            <td className="p-2">
                                                GH₵{code.registration_bonus}
                                            </td>
                                            <td className="p-2">
                                                {code.is_active ? (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <Power className="size-3" />
                                                        ACTIVE
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-800 dark:bg-gray-800 dark:text-gray-200">
                                                        <PowerOff className="size-3" />
                                                        INACTIVE
                                                    </span>
                                                )}
                                            </td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}`}
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Eye className="size-4" />
                                                    </Link>
                                                    <Link
                                                        href={`/dashboard/referral-codes/${code.id}/edit`}
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleToggle(
                                                                code.id,
                                                            )
                                                        }
                                                        className="text-muted-foreground hover:text-foreground"
                                                        title={
                                                            code.is_active
                                                                ? 'Deactivate'
                                                                : 'Activate'
                                                        }
                                                    >
                                                        {code.is_active ? (
                                                            <PowerOff className="size-4" />
                                                        ) : (
                                                            <Power className="size-4" />
                                                        )}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                code.id,
                                                                code.code,
                                                            )
                                                        }
                                                        className="text-muted-foreground hover:text-destructive"
                                                    >
                                                        <Trash2 className="size-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {codes.data.length === 0 && (
                            <div className="py-8 text-center text-muted-foreground">
                                No referral codes found. Create one to get
                                started.
                            </div>
                        )}

                        {codes.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {codes.data.length} of {codes.total}{' '}
                                    codes
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page - 1,
                                            )
                                        }
                                        disabled={codes.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <div className="flex items-center px-3 text-sm">
                                        Page {codes.current_page} of{' '}
                                        {codes.last_page}
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                codes.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            codes.current_page ===
                                            codes.last_page
                                        }
                                    >
                                        Next
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
