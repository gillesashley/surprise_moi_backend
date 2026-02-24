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
import { Eye, Pencil, Plus, Trash2 } from 'lucide-react';

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
    period_type: string;
    start_date: string;
    end_date: string;
    status: string;
}

interface PaginatedTargets {
    data: Target[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    targets: PaginatedTargets;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Targets',
        href: '/dashboard/targets',
    },
];

const formatTargetType = (type: string) => {
    return type
        .split('_')
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
};

const getStatusBadgeColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        case 'completed':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200';
        case 'expired':
            return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
        case 'cancelled':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
    }
};

export default function TargetsIndex({ targets }: Props) {
    const handleDelete = (targetId: number) => {
        if (
            confirm(
                'Are you sure you want to delete this target? This action cannot be undone.',
            )
        ) {
            router.delete(`/dashboard/targets/${targetId}`, {
                preserveScroll: true,
            });
        }
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/dashboard/targets',
            { page },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const progressPercentage = (current: number, target: number) => {
        return Math.min((current / target) * 100, 100).toFixed(1);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Targets Management" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">
                            Targets Management
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Assign and manage targets for field agents and
                            marketers
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/dashboard/targets/create">
                            <Plus className="mr-2 size-4" />
                            Create Target
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>All Targets</CardTitle>
                        <CardDescription>
                            View and manage all assigned targets
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="p-2 text-left text-sm font-medium">
                                            User
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Target Type
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Progress
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Bonus
                                        </th>
                                        <th className="p-2 text-left text-sm font-medium">
                                            Period
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
                                    {targets.data.map((target) => (
                                        <tr
                                            key={target.id}
                                            className="border-b hover:bg-muted/50"
                                        >
                                            <td className="p-2">
                                                <div>
                                                    <p className="font-medium">
                                                        {target.user.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {target.user.email}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                {formatTargetType(
                                                    target.target_type,
                                                )}
                                            </td>
                                            <td className="p-2">
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {target.current_value} /{' '}
                                                        {target.target_value}
                                                    </p>
                                                    <div className="mt-1 h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                                        <div
                                                            className="h-full bg-purple-600"
                                                            style={{
                                                                width: `${progressPercentage(
                                                                    target.current_value,
                                                                    target.target_value,
                                                                )}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-2">
                                                GH₵{target.bonus_amount}
                                            </td>
                                            <td className="p-2 capitalize">
                                                {target.period_type}
                                            </td>
                                            <td className="p-2">
                                                <span
                                                    className={`inline-flex rounded-full px-2 py-1 text-xs font-semibold uppercase ${getStatusBadgeColor(
                                                        target.status,
                                                    )}`}
                                                >
                                                    {target.status}
                                                </span>
                                            </td>
                                            <td className="p-2">
                                                <div className="flex gap-2">
                                                    <Link
                                                        href={`/dashboard/targets/${target.id}`}
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Eye className="size-4" />
                                                    </Link>
                                                    <Link
                                                        href={`/dashboard/targets/${target.id}/edit`}
                                                        className="text-muted-foreground hover:text-foreground"
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Link>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(
                                                                target.id,
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

                        {targets.data.length === 0 && (
                            <div className="py-8 text-center text-muted-foreground">
                                No targets found. Create one to get started.
                            </div>
                        )}

                        {targets.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {targets.data.length} of{' '}
                                    {targets.total} targets
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                targets.current_page - 1,
                                            )
                                        }
                                        disabled={targets.current_page === 1}
                                    >
                                        Previous
                                    </Button>
                                    <div className="flex items-center px-3 text-sm">
                                        Page {targets.current_page} of{' '}
                                        {targets.last_page}
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            handlePageChange(
                                                targets.current_page + 1,
                                            )
                                        }
                                        disabled={
                                            targets.current_page ===
                                            targets.last_page
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
