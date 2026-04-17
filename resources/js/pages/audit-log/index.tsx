import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { KeyboardArrowDown, KeyboardArrowUp } from '@mui/icons-material';
import {
    Box,
    Chip,
    Collapse,
    IconButton,
    MenuItem,
    Paper,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TablePagination,
    TableRow,
    TextField,
    Typography,
} from '@mui/material';
import { useEffect, useState } from 'react';

type Props = {
    logs: {
        data: Array<{
            id: number;
            event: string;
            description: string;
            subject_type: string | null;
            subject_id: number | null;
            causer: { id: number; name: string; email: string; role: string } | null;
            properties: Record<string, any>;
            created_at: string;
        }>;
        total: number;
        current_page: number;
        per_page: number;
    };
    filters: {
        event?: string;
        subject_type?: string;
        user?: string;
        from?: string;
        to?: string;
        per_page?: number;
    };
    entityOptions: Array<{ value: string; label: string }>;
    eventOptions: Array<{ value: string; label: string }>;
};

const ACTION_COLORS: Record<string, 'success' | 'info' | 'error' | 'warning' | 'default'> = {
    created: 'success',
    updated: 'info',
    deleted: 'error',
    login: 'info',
    logout: 'info',
    login_failed: 'error',
    password_reset: 'warning',
};

function shortType(fullType: string | null): string {
    if (!fullType) return '—';
    const parts = fullType.split('\\');
    return parts[parts.length - 1];
}

function actionColorFor(event: string): 'success' | 'info' | 'error' | 'warning' | 'default' {
    if (ACTION_COLORS[event]) return ACTION_COLORS[event];
    if (event.endsWith('.approved') || event.endsWith('.paid') || event.endsWith('.fulfilled')) return 'success';
    if (event.endsWith('.rejected') || event.endsWith('.role_changed')) return 'error';
    if (event.endsWith('.updated')) return 'info';
    return 'default';
}

function ChangesPanel({ properties }: { properties: Record<string, any> }) {
    const old = properties.old ?? {};
    const attrs = properties.attributes ?? {};
    const keys = Array.from(new Set([...Object.keys(old), ...Object.keys(attrs)]));

    if (keys.length === 0) {
        const extra = properties.extra ?? {};
        if (Object.keys(extra).length) {
            return (
                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                    {Object.entries(extra).map(([k, v]) => (
                        <Box key={k} sx={{ display: 'flex', gap: 2 }}>
                            <Typography variant="caption" sx={{ fontWeight: 700, minWidth: 140 }}>
                                {k}
                            </Typography>
                            <Typography variant="caption" sx={{ fontFamily: 'monospace' }}>
                                {JSON.stringify(v)}
                            </Typography>
                        </Box>
                    ))}
                </Box>
            );
        }
        return (
            <Typography variant="body2" color="text.secondary">
                No changes recorded
            </Typography>
        );
    }

    return (
        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
            {keys.map((k) => (
                <Box key={k} sx={{ display: 'flex', gap: 2, alignItems: 'flex-start' }}>
                    <Typography variant="caption" sx={{ fontWeight: 700, minWidth: 140, color: 'text.secondary' }}>
                        {k}
                    </Typography>
                    <Box sx={{ display: 'flex', gap: 1, alignItems: 'center', flexWrap: 'wrap', flex: 1 }}>
                        {old[k] !== undefined && (
                            <Box
                                sx={{
                                    px: 1,
                                    py: 0.3,
                                    borderRadius: 1,
                                    bgcolor: 'rgba(229,62,62,0.08)',
                                    border: '1px solid rgba(229,62,62,0.2)',
                                }}
                            >
                                <Typography variant="caption" sx={{ color: 'error.main', fontFamily: 'monospace' }}>
                                    {JSON.stringify(old[k])}
                                </Typography>
                            </Box>
                        )}
                        {old[k] !== undefined && attrs[k] !== undefined && (
                            <Typography variant="caption" color="text.disabled">
                                →
                            </Typography>
                        )}
                        {attrs[k] !== undefined && (
                            <Box
                                sx={{
                                    px: 1,
                                    py: 0.3,
                                    borderRadius: 1,
                                    bgcolor: 'rgba(72,187,120,0.08)',
                                    border: '1px solid rgba(72,187,120,0.2)',
                                }}
                            >
                                <Typography variant="caption" sx={{ color: 'success.main', fontFamily: 'monospace' }}>
                                    {JSON.stringify(attrs[k])}
                                </Typography>
                            </Box>
                        )}
                    </Box>
                </Box>
            ))}
        </Box>
    );
}

function AuditRow({ row }: { row: Props['logs']['data'][number] }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <TableRow hover>
                <TableCell sx={{ width: 40, pr: 0 }}>
                    <IconButton size="small" onClick={() => setOpen((v) => !v)}>
                        {open ? <KeyboardArrowUp fontSize="small" /> : <KeyboardArrowDown fontSize="small" />}
                    </IconButton>
                </TableCell>
                <TableCell>
                    <Typography variant="body2">{new Date(row.created_at).toLocaleString()}</Typography>
                </TableCell>
                <TableCell>
                    <Typography variant="body2">{row.causer ? row.causer.name : 'System'}</Typography>
                    {row.causer && (
                        <Typography variant="caption" color="text.secondary">
                            {row.causer.email}
                        </Typography>
                    )}
                </TableCell>
                <TableCell>
                    <Chip label={row.event} color={actionColorFor(row.event)} size="small" sx={{ fontWeight: 700 }} />
                </TableCell>
                <TableCell>
                    <Typography variant="body2">
                        {shortType(row.subject_type)} {row.subject_id ? `#${row.subject_id}` : ''}
                    </Typography>
                </TableCell>
                <TableCell>
                    <Typography variant="caption" color="text.secondary">
                        {row.properties.ip ?? ''}
                    </Typography>
                </TableCell>
            </TableRow>
            <TableRow>
                <TableCell colSpan={6} sx={{ py: 0, border: 0 }}>
                    <Collapse in={open} timeout="auto" unmountOnExit>
                        <Box
                            sx={{
                                m: 1.5,
                                p: 2,
                                bgcolor: 'rgba(0,0,0,0.02)',
                                borderRadius: 1,
                                border: '1px solid',
                                borderColor: 'divider',
                            }}
                        >
                            <Typography
                                variant="caption"
                                sx={{ fontWeight: 700, mb: 1, display: 'block', color: 'text.secondary' }}
                            >
                                DETAILS · IP: {row.properties.ip ?? '—'} · UA: {row.properties.user_agent ?? '—'}
                            </Typography>
                            <ChangesPanel properties={row.properties} />
                        </Box>
                    </Collapse>
                </TableCell>
            </TableRow>
        </>
    );
}

export default function AuditLogIndex({ logs, filters, entityOptions, eventOptions }: Props) {
    const [event, setEvent] = useState(filters.event ?? '');
    const [subjectType, setSubjectType] = useState(filters.subject_type ?? '');
    const [user, setUser] = useState(filters.user ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');

    useEffect(() => {
        const t = setTimeout(() => {
            router.get(
                '/dashboard/audit-log',
                {
                    event: event || undefined,
                    subject_type: subjectType || undefined,
                    user: user || undefined,
                    from: from || undefined,
                    to: to || undefined,
                    per_page: filters.per_page,
                },
                { preserveState: true, replace: true },
            );
        }, 300);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [event, subjectType, user, from, to]);

    return (
        <AppLayout>
            <Head title="Audit Log" />
            <Box sx={{ p: 3 }}>
                <Box sx={{ mb: 3 }}>
                    <Typography variant="h5" sx={{ fontWeight: 700 }}>
                        Audit Log
                    </Typography>
                    <Typography variant="body2" color="text.secondary">
                        Every create, update, delete, and key action across the dashboard.
                    </Typography>
                </Box>

                <Paper sx={{ p: 2, mb: 3 }}>
                    <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
                        <TextField
                            select
                            size="small"
                            label="Entity"
                            value={subjectType}
                            onChange={(e) => setSubjectType(e.target.value)}
                            sx={{ width: 200 }}
                        >
                            <MenuItem value="">All Entities</MenuItem>
                            {entityOptions.map((o) => (
                                <MenuItem key={o.value} value={o.value}>
                                    {o.label}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            select
                            size="small"
                            label="Action"
                            value={event}
                            onChange={(e) => setEvent(e.target.value)}
                            sx={{ width: 200 }}
                        >
                            <MenuItem value="">All Actions</MenuItem>
                            {eventOptions.map((o) => (
                                <MenuItem key={o.value} value={o.value}>
                                    {o.label}
                                </MenuItem>
                            ))}
                        </TextField>
                        <TextField
                            size="small"
                            label="User (id, name, email)"
                            value={user}
                            onChange={(e) => setUser(e.target.value)}
                            sx={{ width: 220 }}
                        />
                        <TextField
                            size="small"
                            label="From"
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                            InputLabelProps={{ shrink: true }}
                        />
                        <TextField
                            size="small"
                            label="To"
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                            InputLabelProps={{ shrink: true }}
                        />
                    </Box>
                </Paper>

                <Paper>
                    <TableContainer>
                        <Table size="small">
                            <TableHead>
                                <TableRow>
                                    <TableCell sx={{ width: 40 }} />
                                    <TableCell>Timestamp</TableCell>
                                    <TableCell>User</TableCell>
                                    <TableCell>Action</TableCell>
                                    <TableCell>Entity</TableCell>
                                    <TableCell>IP</TableCell>
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {logs.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={6} align="center" sx={{ py: 6 }}>
                                            <Typography color="text.secondary">No audit logs found.</Typography>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    logs.data.map((row) => <AuditRow key={row.id} row={row} />)
                                )}
                            </TableBody>
                        </Table>
                    </TableContainer>
                    <TablePagination
                        component="div"
                        count={logs.total}
                        page={logs.current_page - 1}
                        rowsPerPage={logs.per_page}
                        onPageChange={(_, p) =>
                            router.get(
                                '/dashboard/audit-log',
                                { ...filters, page: p + 1 },
                                { preserveState: true, replace: true },
                            )
                        }
                        onRowsPerPageChange={(e) =>
                            router.get(
                                '/dashboard/audit-log',
                                { ...filters, per_page: parseInt(e.target.value, 10), page: 1 },
                                { preserveState: true, replace: true },
                            )
                        }
                        rowsPerPageOptions={[10, 20, 50, 100]}
                    />
                </Paper>
            </Box>
        </AppLayout>
    );
}
