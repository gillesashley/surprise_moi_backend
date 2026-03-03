import { Button } from '@/components/ui/button';
import { Pagination } from '@/components/ui/pagination';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    create as bespokeServiceCreate,
    destroy as bespokeServiceDestroy,
    edit as bespokeServiceEdit,
} from '@/routes/dashboard/bespoke-services';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
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
        <Box sx={{ mt: 3 }}>
            <Box
                sx={{
                    mb: 2,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                }}
            >
                <Typography variant="h6" fontWeight={600}>
                    Bespoke Services
                </Typography>
                {canCreate && (
                    <Button asChild>
                        <Link href={bespokeServiceCreate.url()}>
                            <Plus style={{ width: 16, height: 16, marginRight: 8 }} />
                            Add Service
                        </Link>
                    </Button>
                )}
            </Box>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Slug</TableHead>
                        <TableHead>Vendors</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Order</TableHead>
                        <TableHead style={{ textAlign: 'right' }}>
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {bespokeServices.data.map((service) => (
                        <TableRow key={service.id}>
                            <TableCell>
                                <Typography variant="body2" fontWeight={500}>
                                    {service.name}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                >
                                    {service.slug}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {service.vendor_applications_count}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Chip
                                    label={
                                        service.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }
                                    size="small"
                                    color={
                                        service.is_active
                                            ? 'success'
                                            : 'default'
                                    }
                                    variant="outlined"
                                />
                            </TableCell>
                            <TableCell>
                                <Typography variant="body2">
                                    {service.sort_order}
                                </Typography>
                            </TableCell>
                            <TableCell>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        justifyContent: 'flex-end',
                                        gap: 1,
                                    }}
                                >
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
                                            <Pencil style={{ width: 16, height: 16 }} />
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
                                            <Trash2 style={{ width: 16, height: 16, color: 'var(--mui-palette-error-main, #d32f2f)' }} />
                                        </Button>
                                    )}
                                </Box>
                            </TableCell>
                        </TableRow>
                    ))}
                    {bespokeServices.data.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={6}>
                                <Typography
                                    variant="body2"
                                    color="text.secondary"
                                    sx={{ textAlign: 'center', p: 2 }}
                                >
                                    No bespoke services found
                                </Typography>
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
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
        </Box>
    );
}
