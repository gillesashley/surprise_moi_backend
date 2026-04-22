import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Head, Link, router, useForm } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Eye, Headphones, Plus, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface TicketSummary {
    id: number;
    ticket_number: string;
    subject: string;
    category: string;
    priority: string;
    status: string;
    contact_name: string | null;
    contact_phone: string | null;
    user: { id: number; name: string; email: string } | null;
    assignee: { id: number; name: string } | null;
    created_at: string | null;
}

interface PaginatedTickets {
    data: TicketSummary[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Option {
    value: string;
    label: string;
}

interface Props {
    tickets: PaginatedTickets;
    filters: {
        status?: string;
        priority?: string;
        category?: string;
        search?: string;
    };
    categories: Option[];
    statuses: Option[];
    priorities: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Customer Support', href: '/dashboard/customer-support' },
];

const statusChipColor = (
    status: string,
): 'warning' | 'info' | 'success' | 'default' => {
    const map: Record<string, 'warning' | 'info' | 'success' | 'default'> = {
        open: 'warning',
        in_progress: 'info',
        closed: 'success',
    };
    return map[status] || 'default';
};

const priorityChipColor = (
    priority: string,
): 'error' | 'warning' | 'default' => {
    const map: Record<string, 'error' | 'warning' | 'default'> = {
        high: 'error',
        normal: 'warning',
        low: 'default',
    };
    return map[priority] || 'default';
};

const statusLabel: Record<string, string> = {
    open: 'Open',
    in_progress: 'In Progress',
    closed: 'Closed',
};

function formatCategory(value: string): string {
    return value
        .split('_')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

export default function CustomerSupportIndex({
    tickets,
    filters,
    categories,
    statuses,
    priorities,
}: Props) {
    const [statusFilter, setStatusFilter] = useState(filters.status || 'all');
    const [priorityFilter, setPriorityFilter] = useState(
        filters.priority || 'all',
    );
    const [categoryFilter, setCategoryFilter] = useState(
        filters.category || 'all',
    );
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [createOpen, setCreateOpen] = useState(false);

    const createForm = useForm({
        subject: '',
        category: '',
        priority: 'normal',
        description: '',
        contact_name: '',
        contact_phone: '',
        contact_email: '',
    });

    const handleCreate = () => {
        createForm.post('/dashboard/customer-support', {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpen(false);
                createForm.reset();
            },
        });
    };

    const buildParams = (overrides: Record<string, unknown> = {}) => ({
        ...(statusFilter !== 'all' && { status: statusFilter }),
        ...(priorityFilter !== 'all' && { priority: priorityFilter }),
        ...(categoryFilter !== 'all' && { category: categoryFilter }),
        ...(searchTerm && { search: searchTerm }),
        page: 1,
        ...overrides,
    });

    useEffect(() => {
        const delay = setTimeout(() => {
            if (searchTerm !== filters.search) {
                router.get('/dashboard/customer-support', buildParams(), {
                    preserveState: true,
                    preserveScroll: true,
                });
            }
        }, 300);
        return () => clearTimeout(delay);
    }, [searchTerm]);

    const handleStatusChange = (value: string) => {
        setStatusFilter(value);
        router.get(
            '/dashboard/customer-support',
            buildParams({ status: value !== 'all' ? value : undefined }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePriorityChange = (value: string) => {
        setPriorityFilter(value);
        router.get(
            '/dashboard/customer-support',
            buildParams({ priority: value !== 'all' ? value : undefined }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleCategoryChange = (value: string) => {
        setCategoryFilter(value);
        router.get(
            '/dashboard/customer-support',
            buildParams({ category: value !== 'all' ? value : undefined }),
            { preserveState: true, preserveScroll: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get('/dashboard/customer-support', buildParams({ page }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customer Support" />
            <Box
                sx={{
                    display: 'flex',
                    flex: 1,
                    flexDirection: 'column',
                    gap: 2,
                    p: 2,
                    height: '100%',
                }}
            >
                <Card>
                    <CardHeader>
                        <Box
                            sx={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 2,
                            }}
                        >
                            <Box
                                sx={{
                                    display: 'flex',
                                    alignItems: 'center',
                                    justifyContent: 'space-between',
                                }}
                            >
                                <Box
                                    sx={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 1,
                                    }}
                                >
                                    <Headphones
                                        style={{
                                            width: 20,
                                            height: 20,
                                            color: '#2563eb',
                                        }}
                                    />
                                    <Box>
                                        <CardTitle>Customer Support</CardTitle>
                                        <CardDescription>
                                            Tickets raised by reps checking in
                                            on customers and vendors
                                        </CardDescription>
                                    </Box>
                                </Box>
                                <Box
                                    sx={{
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: 1,
                                    }}
                                >
                                    <Chip
                                        label={`${tickets.total} total`}
                                        size="small"
                                        variant="outlined"
                                    />
                                    <Button
                                        onClick={() => setCreateOpen(true)}
                                        size="sm"
                                    >
                                        <Plus
                                            style={{
                                                marginRight: 4,
                                                width: 16,
                                                height: 16,
                                            }}
                                        />{' '}
                                        New Ticket
                                    </Button>
                                </Box>
                            </Box>
                            <Box
                                sx={{
                                    display: 'flex',
                                    flexDirection: { xs: 'column', sm: 'row' },
                                    gap: 1,
                                }}
                            >
                                <Box sx={{ position: 'relative', flex: 1 }}>
                                    <Search
                                        style={{
                                            position: 'absolute',
                                            top: 10,
                                            left: 10,
                                            width: 16,
                                            height: 16,
                                            color: 'var(--muted-foreground)',
                                        }}
                                    />
                                    <Input
                                        type="search"
                                        placeholder="Search by ticket #, subject, contact, or user..."
                                        value={searchTerm}
                                        onChange={(e) =>
                                            setSearchTerm(e.target.value)
                                        }
                                        style={{ paddingLeft: 36 }}
                                    />
                                </Box>
                                <Select
                                    value={statusFilter}
                                    onValueChange={handleStatusChange}
                                >
                                    <SelectTrigger style={{ width: 160 }}>
                                        <SelectValue placeholder="All statuses" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Statuses
                                        </SelectItem>
                                        {statuses.map((s) => (
                                            <SelectItem
                                                key={s.value}
                                                value={s.value}
                                            >
                                                {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={priorityFilter}
                                    onValueChange={handlePriorityChange}
                                >
                                    <SelectTrigger style={{ width: 160 }}>
                                        <SelectValue placeholder="All priorities" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Priorities
                                        </SelectItem>
                                        {priorities.map((p) => (
                                            <SelectItem
                                                key={p.value}
                                                value={p.value}
                                            >
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={categoryFilter}
                                    onValueChange={handleCategoryChange}
                                >
                                    <SelectTrigger style={{ width: 200 }}>
                                        <SelectValue placeholder="All categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Categories
                                        </SelectItem>
                                        {categories.map((c) => (
                                            <SelectItem
                                                key={c.value}
                                                value={c.value}
                                            >
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Box>
                        </Box>
                    </CardHeader>
                    <CardContent>
                        {tickets.data.length === 0 ? (
                            <Box
                                sx={{
                                    py: 6,
                                    textAlign: 'center',
                                    color: 'text.secondary',
                                }}
                            >
                                <Headphones
                                    style={{
                                        margin: '0 auto 8px',
                                        width: 32,
                                        height: 32,
                                        opacity: 0.4,
                                    }}
                                />
                                <Typography>No tickets found.</Typography>
                            </Box>
                        ) : (
                            <>
                                <Box sx={{ overflowX: 'auto' }}>
                                    <Box
                                        component="table"
                                        sx={{ width: '100%' }}
                                    >
                                        <thead>
                                            <Box
                                                component="tr"
                                                sx={{
                                                    borderBottom: 1,
                                                    borderColor: 'divider',
                                                }}
                                            >
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Ticket #
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Subject
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Contact
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Category
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Priority
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Status
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Assignee
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Date
                                                </Box>
                                                <Box
                                                    component="th"
                                                    sx={{
                                                        p: 1,
                                                        textAlign: 'left',
                                                        fontSize: '0.875rem',
                                                        fontWeight: 500,
                                                    }}
                                                >
                                                    Actions
                                                </Box>
                                            </Box>
                                        </thead>
                                        <tbody>
                                            {tickets.data.map((ticket) => (
                                                <Box
                                                    component="tr"
                                                    key={ticket.id}
                                                    sx={{
                                                        borderBottom: 1,
                                                        borderColor: 'divider',
                                                        '&:last-child': {
                                                            borderBottom: 0,
                                                        },
                                                        '&:hover': {
                                                            bgcolor:
                                                                'action.hover',
                                                        },
                                                    }}
                                                >
                                                    <Box
                                                        component="td"
                                                        sx={{
                                                            p: 1,
                                                            fontSize:
                                                                '0.875rem',
                                                            fontFamily:
                                                                'monospace',
                                                        }}
                                                    >
                                                        {ticket.ticket_number}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{
                                                            p: 1,
                                                            fontSize:
                                                                '0.875rem',
                                                            fontWeight: 500,
                                                        }}
                                                    >
                                                        {ticket.subject}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{ p: 1 }}
                                                    >
                                                        {ticket.user ? (
                                                            <>
                                                                <Box
                                                                    sx={{
                                                                        fontSize:
                                                                            '0.875rem',
                                                                        fontWeight: 500,
                                                                    }}
                                                                >
                                                                    {
                                                                        ticket
                                                                            .user
                                                                            .name
                                                                    }
                                                                </Box>
                                                                <Box
                                                                    sx={{
                                                                        fontSize:
                                                                            '0.75rem',
                                                                        color: 'text.secondary',
                                                                    }}
                                                                >
                                                                    {
                                                                        ticket
                                                                            .user
                                                                            .email
                                                                    }
                                                                </Box>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <Box
                                                                    sx={{
                                                                        fontSize:
                                                                            '0.875rem',
                                                                        fontWeight: 500,
                                                                    }}
                                                                >
                                                                    {ticket.contact_name ??
                                                                        '\u2014'}
                                                                </Box>
                                                                <Box
                                                                    sx={{
                                                                        fontSize:
                                                                            '0.75rem',
                                                                        color: 'text.secondary',
                                                                    }}
                                                                >
                                                                    {ticket.contact_phone ??
                                                                        ''}
                                                                </Box>
                                                            </>
                                                        )}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{
                                                            p: 1,
                                                            fontSize:
                                                                '0.875rem',
                                                        }}
                                                    >
                                                        {formatCategory(
                                                            ticket.category,
                                                        )}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{ p: 1 }}
                                                    >
                                                        <Chip
                                                            label={
                                                                ticket.priority
                                                                    .charAt(0)
                                                                    .toUpperCase() +
                                                                ticket.priority.slice(
                                                                    1,
                                                                )
                                                            }
                                                            color={priorityChipColor(
                                                                ticket.priority,
                                                            )}
                                                            size="small"
                                                            variant="outlined"
                                                        />
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{ p: 1 }}
                                                    >
                                                        <Chip
                                                            label={
                                                                statusLabel[
                                                                    ticket
                                                                        .status
                                                                ] ||
                                                                ticket.status
                                                            }
                                                            color={statusChipColor(
                                                                ticket.status,
                                                            )}
                                                            size="small"
                                                            variant="outlined"
                                                        />
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{
                                                            p: 1,
                                                            fontSize:
                                                                '0.875rem',
                                                        }}
                                                    >
                                                        {ticket.assignee
                                                            ?.name ?? '\u2014'}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{
                                                            p: 1,
                                                            fontSize:
                                                                '0.875rem',
                                                            color: 'text.secondary',
                                                        }}
                                                    >
                                                        {ticket.created_at
                                                            ? new Date(
                                                                  ticket.created_at,
                                                              ).toLocaleDateString()
                                                            : '\u2014'}
                                                    </Box>
                                                    <Box
                                                        component="td"
                                                        sx={{ p: 1 }}
                                                    >
                                                        <Button
                                                            asChild
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={`/dashboard/customer-support/${ticket.id}`}
                                                            >
                                                                <Eye
                                                                    style={{
                                                                        marginRight: 4,
                                                                        width: 16,
                                                                        height: 16,
                                                                    }}
                                                                />{' '}
                                                                View
                                                            </Link>
                                                        </Button>
                                                    </Box>
                                                </Box>
                                            ))}
                                        </tbody>
                                    </Box>
                                </Box>
                                {tickets.last_page > 1 && (
                                    <Box
                                        sx={{
                                            mt: 2,
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'space-between',
                                        }}
                                    >
                                        <Typography
                                            sx={{
                                                fontSize: '0.875rem',
                                                color: 'text.secondary',
                                            }}
                                        >
                                            Page {tickets.current_page} of{' '}
                                            {tickets.last_page} ({tickets.total}{' '}
                                            total)
                                        </Typography>
                                        <Box sx={{ display: 'flex', gap: 1 }}>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    tickets.current_page === 1
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        tickets.current_page -
                                                            1,
                                                    )
                                                }
                                            >
                                                Previous
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={
                                                    tickets.current_page ===
                                                    tickets.last_page
                                                }
                                                onClick={() =>
                                                    handlePageChange(
                                                        tickets.current_page +
                                                            1,
                                                    )
                                                }
                                            >
                                                Next
                                            </Button>
                                        </Box>
                                    </Box>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>New Ticket</DialogTitle>
                            <DialogDescription>
                                Open a new case file for a client. You can log
                                the first interaction or send an SMS right
                                after.
                            </DialogDescription>
                        </DialogHeader>
                        <Box
                            sx={{
                                display: 'flex',
                                flexDirection: 'column',
                                gap: 1.5,
                                maxHeight: '60vh',
                                overflowY: 'auto',
                            }}
                        >
                            <Box>
                                <Label htmlFor="contact_name">
                                    Contact name *
                                </Label>
                                <Input
                                    id="contact_name"
                                    value={createForm.data.contact_name}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'contact_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Full name of the person"
                                />
                                {createForm.errors.contact_name && (
                                    <Typography
                                        sx={{
                                            fontSize: '0.75rem',
                                            color: 'error.main',
                                            mt: 0.5,
                                        }}
                                    >
                                        {createForm.errors.contact_name}
                                    </Typography>
                                )}
                            </Box>
                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 1,
                                }}
                            >
                                <Box>
                                    <Label htmlFor="contact_phone">
                                        Contact phone
                                    </Label>
                                    <Input
                                        id="contact_phone"
                                        value={createForm.data.contact_phone}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'contact_phone',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0244xxxxxxx or +233..."
                                    />
                                    {createForm.errors.contact_phone && (
                                        <Typography
                                            sx={{
                                                fontSize: '0.75rem',
                                                color: 'error.main',
                                                mt: 0.5,
                                            }}
                                        >
                                            {createForm.errors.contact_phone}
                                        </Typography>
                                    )}
                                </Box>
                                <Box>
                                    <Label htmlFor="contact_email">
                                        Contact email
                                    </Label>
                                    <Input
                                        id="contact_email"
                                        type="email"
                                        value={createForm.data.contact_email}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'contact_email',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="optional"
                                    />
                                    {createForm.errors.contact_email && (
                                        <Typography
                                            sx={{
                                                fontSize: '0.75rem',
                                                color: 'error.main',
                                                mt: 0.5,
                                            }}
                                        >
                                            {createForm.errors.contact_email}
                                        </Typography>
                                    )}
                                </Box>
                            </Box>
                            <Box>
                                <Label htmlFor="subject">Subject *</Label>
                                <Input
                                    id="subject"
                                    value={createForm.data.subject}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'subject',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Short description of the case"
                                />
                                {createForm.errors.subject && (
                                    <Typography
                                        sx={{
                                            fontSize: '0.75rem',
                                            color: 'error.main',
                                            mt: 0.5,
                                        }}
                                    >
                                        {createForm.errors.subject}
                                    </Typography>
                                )}
                            </Box>
                            <Box
                                sx={{
                                    display: 'grid',
                                    gridTemplateColumns: {
                                        xs: '1fr',
                                        sm: '1fr 1fr',
                                    },
                                    gap: 1,
                                }}
                            >
                                <Box>
                                    <Label htmlFor="create_category">
                                        Category *
                                    </Label>
                                    <Select
                                        value={createForm.data.category}
                                        onValueChange={(v) =>
                                            createForm.setData('category', v)
                                        }
                                    >
                                        <SelectTrigger id="create_category">
                                            <SelectValue placeholder="Pick a category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((c) => (
                                                <SelectItem
                                                    key={c.value}
                                                    value={c.value}
                                                >
                                                    {c.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {createForm.errors.category && (
                                        <Typography
                                            sx={{
                                                fontSize: '0.75rem',
                                                color: 'error.main',
                                                mt: 0.5,
                                            }}
                                        >
                                            {createForm.errors.category}
                                        </Typography>
                                    )}
                                </Box>
                                <Box>
                                    <Label htmlFor="create_priority">
                                        Priority
                                    </Label>
                                    <Select
                                        value={createForm.data.priority}
                                        onValueChange={(v) =>
                                            createForm.setData('priority', v)
                                        }
                                    >
                                        <SelectTrigger id="create_priority">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {priorities.map((p) => (
                                                <SelectItem
                                                    key={p.value}
                                                    value={p.value}
                                                >
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {createForm.errors.priority && (
                                        <Typography
                                            sx={{
                                                fontSize: '0.75rem',
                                                color: 'error.main',
                                                mt: 0.5,
                                            }}
                                        >
                                            {createForm.errors.priority}
                                        </Typography>
                                    )}
                                </Box>
                            </Box>
                            <Box>
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={createForm.data.description}
                                    onChange={(e) =>
                                        createForm.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    placeholder="Optional opening context"
                                />
                                {createForm.errors.description && (
                                    <Typography
                                        sx={{
                                            fontSize: '0.75rem',
                                            color: 'error.main',
                                            mt: 0.5,
                                        }}
                                    >
                                        {createForm.errors.description}
                                    </Typography>
                                )}
                            </Box>
                        </Box>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setCreateOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleCreate}
                                disabled={createForm.processing}
                            >
                                {createForm.processing
                                    ? 'Creating...'
                                    : 'Create Ticket'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Box>
        </AppLayout>
    );
}
