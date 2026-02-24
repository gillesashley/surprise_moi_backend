import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    create as bespokeServiceCreate,
    destroy as bespokeServiceDestroy,
    edit as bespokeServiceEdit,
} from '@/routes/dashboard/bespoke-services';
import { Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface BespokeService {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    icon: string | null;
    image: string | null;
    is_active: boolean;
    sort_order: number;
    vendor_applications_count: number;
    created_at: string;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    bespokeServices: PaginatedData<BespokeService>;
    canCreate: boolean;
    canDelete: boolean;
}

export function BespokeServicesTab({
    bespokeServices,
    canCreate,
    canDelete,
}: Props) {
    const handleDelete = (serviceId: number, serviceName: string) => {
        if (
            confirm(
                `Are you sure you want to delete "${serviceName}"? This action cannot be undone.`,
            )
        ) {
            router.delete(bespokeServiceDestroy.url(serviceId), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div className="mt-6">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-lg font-semibold">Bespoke Services</h3>
                {canCreate && (
                    <Button asChild>
                        <Link href={bespokeServiceCreate.url()}>
                            <Plus className="mr-2 size-4" />
                            Add Service
                        </Link>
                    </Button>
                )}
            </div>
            <div className="overflow-x-auto">
                <table className="w-full">
                    <thead>
                        <tr className="border-b">
                            <th className="p-2 text-left text-sm font-medium">
                                Name
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Slug
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Vendors
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Status
                            </th>
                            <th className="p-2 text-left text-sm font-medium">
                                Order
                            </th>
                            <th className="p-2 text-right text-sm font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {bespokeServices.data.map((service) => (
                            <tr
                                key={service.id}
                                className="border-b last:border-0 hover:bg-muted/50"
                            >
                                <td className="p-2 text-sm font-medium">
                                    {service.name}
                                </td>
                                <td className="p-2 text-sm text-muted-foreground">
                                    {service.slug}
                                </td>
                                <td className="p-2 text-sm">
                                    {service.vendor_applications_count}
                                </td>
                                <td className="p-2 text-sm">
                                    <span
                                        className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                            service.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'
                                        }`}
                                    >
                                        {service.is_active
                                            ? 'Active'
                                            : 'Inactive'}
                                    </span>
                                </td>
                                <td className="p-2 text-sm">
                                    {service.sort_order}
                                </td>
                                <td className="p-2">
                                    <div className="flex justify-end gap-2">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={bespokeServiceEdit.url(
                                                    service.id,
                                                )}
                                            >
                                                <Pencil className="size-4" />
                                            </Link>
                                        </Button>
                                        {canDelete && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    handleDelete(
                                                        service.id,
                                                        service.name,
                                                    )
                                                }
                                            >
                                                <Trash2 className="size-4 text-destructive" />
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {bespokeServices.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="p-4 text-center text-muted-foreground"
                                >
                                    No bespoke services found
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
            <Pagination
                currentPage={bespokeServices.current_page}
                lastPage={bespokeServices.last_page}
                onPageChange={(page) => {
                    router.get('/content-management', {
                        tab: 'bespoke',
                        page,
                    });
                }}
            />
        </div>
    );
}
