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
import {
    ArrowLeft,
    CheckCircle,
    Clock,
    MessageSquare,
    Package,
    Phone,
    Send,
    User as UserIcon,
    UserCog,
} from 'lucide-react';
import { useMemo, useState } from 'react';

interface TimelineInteraction {
    type: 'interaction';
    id: number;
    channel: string;
    direction: string;
    summary: string;
    occurred_at: string | null;
    follow_up_at: string | null;
    creator: { id: number; name: string } | null;
    sort_at: string | null;
}

interface TimelineMessage {
    type: 'message';
    id: number;
    body: string;
    template_key: string | null;
    to_phone: string;
    status: string;
    failed_reason: string | null;
    sent_at: string | null;
    sender: { id: number; name: string } | null;
    sort_at: string | null;
}

type TimelineEntry = TimelineInteraction | TimelineMessage;

interface Ticket {
    id: number;
    ticket_number: string;
    subject: string;
    description: string | null;
    category: string;
    priority: string;
    status: string;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    user: { id: number; name: string; email: string; phone: string | null } | null;
    assignee: { id: number; name: string } | null;
    creator: { id: number; name: string } | null;
    closer: { id: number; name: string } | null;
    order: { id: number; order_number: string } | null;
    closure_note: string | null;
    closed_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface Assignee {
    id: number;
    name: string;
}

interface Props {
    ticket: Ticket;
    timeline: TimelineEntry[];
    assignees: Assignee[];
    templates: Record<string, string>;
    channels: string[];
    statuses: Option[];
    priorities: Option[];
    preferredPhone: string | null;
}

const statusChipColor = (status: string): 'warning' | 'info' | 'success' | 'default' => {
    const map: Record<string, 'warning' | 'info' | 'success' | 'default'> = {
        open: 'warning',
        in_progress: 'info',
        closed: 'success',
    };
    return map[status] || 'default';
};

const priorityChipColor = (priority: string): 'error' | 'warning' | 'default' => {
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

const messageStatusColor = (status: string): 'warning' | 'success' | 'error' | 'default' => {
    const map: Record<string, 'warning' | 'success' | 'error' | 'default'> = {
        queued: 'warning',
        sent: 'success',
        failed: 'error',
    };
    return map[status] || 'default';
};

const channelIcon: Record<string, React.ReactNode> = {
    phone_call: <Phone style={{ width: 14, height: 14 }} />,
    sms: <MessageSquare style={{ width: 14, height: 14 }} />,
    whatsapp: <MessageSquare style={{ width: 14, height: 14 }} />,
    email: <MessageSquare style={{ width: 14, height: 14 }} />,
    in_app_chat: <MessageSquare style={{ width: 14, height: 14 }} />,
    in_person: <UserIcon style={{ width: 14, height: 14 }} />,
    other: <MessageSquare style={{ width: 14, height: 14 }} />,
};

function formatLabel(value: string): string {
    return value.split('_').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function renderTemplate(template: string, ticket: Ticket): string {
    const name = ticket.user?.name || ticket.contact_name || 'there';
    return template.replace(/\{\{\s*name\s*\}\}/g, name);
}

export default function CustomerSupportShow({
    ticket,
    timeline,
    assignees,
    templates,
    channels,
    preferredPhone,
}: Props) {
    const [logOpen, setLogOpen] = useState(false);
    const [smsOpen, setSmsOpen] = useState(false);
    const [closeOpen, setCloseOpen] = useState(false);
    const [assignOpen, setAssignOpen] = useState(false);

    const logForm = useForm({
        channel: 'phone_call',
        direction: 'outbound',
        summary: '',
        follow_up_at: '',
    });

    const smsForm = useForm({
        template_key: 'custom',
        body: '',
        to_phone: preferredPhone ?? '',
    });

    const closeForm = useForm({ status: 'closed', closure_note: '' });

    const assignForm = useForm({ assigned_to: ticket.assignee?.id ?? 0 });

    const breadcrumbs: BreadcrumbItem[] = useMemo(
        () => [
            { title: 'Customer Support', href: '/dashboard/customer-support' },
            { title: ticket.ticket_number, href: `/dashboard/customer-support/${ticket.id}` },
        ],
        [ticket.id, ticket.ticket_number],
    );

    const isClosed = ticket.status === 'closed';

    const handleLogInteraction = () => {
        logForm.post(`/dashboard/customer-support/${ticket.id}/interactions`, {
            preserveScroll: true,
            onSuccess: () => {
                setLogOpen(false);
                logForm.reset();
            },
        });
    };

    const handleSendSms = () => {
        smsForm.post(`/dashboard/customer-support/${ticket.id}/messages`, {
            preserveScroll: true,
            onSuccess: () => {
                setSmsOpen(false);
                smsForm.reset('body', 'template_key');
            },
        });
    };

    const handleMarkInProgress = () => {
        router.patch(
            `/dashboard/customer-support/${ticket.id}/status`,
            { status: 'in_progress' },
            { preserveScroll: true },
        );
    };

    const handleClose = () => {
        closeForm.patch(`/dashboard/customer-support/${ticket.id}/status`, {
            preserveScroll: true,
            onSuccess: () => {
                setCloseOpen(false);
                closeForm.reset();
            },
        });
    };

    const handleAssign = () => {
        assignForm.patch(`/dashboard/customer-support/${ticket.id}/assign`, {
            preserveScroll: true,
            onSuccess: () => setAssignOpen(false),
        });
    };

    const handleTemplateChange = (key: string) => {
        smsForm.setData('template_key', key);
        if (key !== 'custom' && templates[key]) {
            smsForm.setData('body', renderTemplate(templates[key], ticket));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket ${ticket.ticket_number}`} />
            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2, height: '100%' }}>
                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
                    <Button asChild variant="ghost" size="sm">
                        <Link href="/dashboard/customer-support">
                            <ArrowLeft style={{ marginRight: 4, width: 16, height: 16 }} /> Back to Tickets
                        </Link>
                    </Button>
                    <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
                        <Button variant="outline" onClick={() => setLogOpen(true)} disabled={isClosed}>
                            <Phone style={{ marginRight: 4, width: 16, height: 16 }} /> Log Interaction
                        </Button>
                        <Button variant="outline" onClick={() => setSmsOpen(true)} disabled={isClosed || !preferredPhone}>
                            <Send style={{ marginRight: 4, width: 16, height: 16 }} /> Send SMS
                        </Button>
                        <Button variant="outline" onClick={() => setAssignOpen(true)} disabled={isClosed}>
                            <UserCog style={{ marginRight: 4, width: 16, height: 16 }} /> Reassign
                        </Button>
                        {ticket.status === 'open' && (
                            <Button variant="outline" onClick={handleMarkInProgress}>
                                <Clock style={{ marginRight: 4, width: 16, height: 16 }} /> Mark In Progress
                            </Button>
                        )}
                        {!isClosed && (
                            <Button onClick={() => setCloseOpen(true)}>
                                <CheckCircle style={{ marginRight: 4, width: 16, height: 16 }} /> Close Ticket
                            </Button>
                        )}
                    </Box>
                </Box>

                <Box sx={{ display: 'grid', gap: 2, gridTemplateColumns: { xs: '1fr', lg: '2fr 1fr' } }}>
                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', flexWrap: 'wrap', gap: 1 }}>
                                    <Box>
                                        <CardTitle sx={{ fontFamily: 'monospace' }}>{ticket.ticket_number}</CardTitle>
                                        <CardDescription>{formatLabel(ticket.category)}</CardDescription>
                                    </Box>
                                    <Box sx={{ display: 'flex', gap: 0.5 }}>
                                        <Chip
                                            label={ticket.priority.charAt(0).toUpperCase() + ticket.priority.slice(1)}
                                            color={priorityChipColor(ticket.priority)}
                                            size="small"
                                            variant="outlined"
                                        />
                                        <Chip
                                            label={statusLabel[ticket.status] || ticket.status}
                                            color={statusChipColor(ticket.status)}
                                            size="small"
                                            variant="outlined"
                                        />
                                    </Box>
                                </Box>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                                <Box>
                                    <Typography sx={{ fontSize: '1rem', fontWeight: 500 }}>{ticket.subject}</Typography>
                                    {ticket.description && (
                                        <Typography sx={{ mt: 0.5, fontSize: '0.875rem', lineHeight: 1.7, color: 'text.secondary' }}>
                                            {ticket.description}
                                        </Typography>
                                    )}
                                </Box>
                                {ticket.closure_note && (
                                    <Box sx={{ borderRadius: 1, bgcolor: 'action.hover', p: 1.5 }}>
                                        <Typography sx={{ mb: 0.5, fontSize: '0.875rem', fontWeight: 500, color: 'text.secondary' }}>Closure Note</Typography>
                                        <Typography sx={{ fontSize: '0.875rem' }}>{ticket.closure_note}</Typography>
                                        {ticket.closer && ticket.closed_at && (
                                            <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'text.secondary' }}>
                                                Closed by {ticket.closer.name} on {new Date(ticket.closed_at).toLocaleString()}
                                            </Typography>
                                        )}
                                    </Box>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ fontSize: '1rem' }}>Activity Timeline</CardTitle>
                                <CardDescription>Calls, messages, and check-ins, newest first</CardDescription>
                            </CardHeader>
                            <CardContent>
                                {timeline.length === 0 ? (
                                    <Typography sx={{ py: 3, textAlign: 'center', color: 'text.secondary', fontSize: '0.875rem' }}>
                                        No activity yet. Log a call or send an SMS to get started.
                                    </Typography>
                                ) : (
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                                        {timeline.map((entry) => (
                                            <Box
                                                key={`${entry.type}-${entry.id}`}
                                                sx={{
                                                    display: 'flex',
                                                    gap: 1.5,
                                                    borderLeft: 3,
                                                    borderColor: entry.type === 'message' ? 'info.main' : 'warning.main',
                                                    pl: 1.5,
                                                    py: 0.5,
                                                }}
                                            >
                                                <Box sx={{ flex: 1 }}>
                                                    {entry.type === 'interaction' ? (
                                                        <>
                                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, mb: 0.25 }}>
                                                                {channelIcon[entry.channel] ?? null}
                                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                                    {formatLabel(entry.channel)} ({entry.direction})
                                                                </Typography>
                                                            </Box>
                                                            <Typography sx={{ fontSize: '0.875rem', whiteSpace: 'pre-wrap' }}>{entry.summary}</Typography>
                                                            {entry.follow_up_at && (
                                                                <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'warning.main' }}>
                                                                    Follow up on {new Date(entry.follow_up_at).toLocaleDateString()}
                                                                </Typography>
                                                            )}
                                                            <Typography sx={{ mt: 0.25, fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                {entry.creator?.name ?? 'System'}
                                                                {entry.occurred_at && ` • ${new Date(entry.occurred_at).toLocaleString()}`}
                                                            </Typography>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Box sx={{ display: 'flex', alignItems: 'center', gap: 0.5, mb: 0.25 }}>
                                                                <Send style={{ width: 14, height: 14 }} />
                                                                <Typography sx={{ fontSize: '0.875rem', fontWeight: 500 }}>
                                                                    SMS to {entry.to_phone}
                                                                </Typography>
                                                                <Chip
                                                                    label={formatLabel(entry.status)}
                                                                    color={messageStatusColor(entry.status)}
                                                                    size="small"
                                                                    variant="outlined"
                                                                    sx={{ ml: 0.5 }}
                                                                />
                                                                {entry.template_key && entry.template_key !== 'custom' && (
                                                                    <Chip label={formatLabel(entry.template_key)} size="small" variant="outlined" />
                                                                )}
                                                            </Box>
                                                            <Typography sx={{ fontSize: '0.875rem', whiteSpace: 'pre-wrap' }}>{entry.body}</Typography>
                                                            {entry.failed_reason && (
                                                                <Typography sx={{ mt: 0.25, fontSize: '0.75rem', color: 'error.main' }}>
                                                                    Failure: {entry.failed_reason}
                                                                </Typography>
                                                            )}
                                                            <Typography sx={{ mt: 0.25, fontSize: '0.75rem', color: 'text.secondary' }}>
                                                                {entry.sender?.name ?? 'System'}
                                                                {entry.sent_at && ` • sent ${new Date(entry.sent_at).toLocaleString()}`}
                                                            </Typography>
                                                        </>
                                                    )}
                                                </Box>
                                            </Box>
                                        ))}
                                    </Box>
                                )}
                            </CardContent>
                        </Card>
                    </Box>

                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                    <UserIcon style={{ width: 16, height: 16 }} /> Contact
                                </CardTitle>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, fontSize: '0.875rem' }}>
                                <Typography sx={{ fontWeight: 500 }}>{ticket.user?.name ?? ticket.contact_name ?? '\u2014'}</Typography>
                                {ticket.user?.email && <Typography sx={{ color: 'text.secondary' }}>{ticket.user.email}</Typography>}
                                {ticket.contact_email && ticket.contact_email !== ticket.user?.email && (
                                    <Typography sx={{ color: 'text.secondary' }}>{ticket.contact_email}</Typography>
                                )}
                                {preferredPhone && (
                                    <Typography sx={{ color: 'text.secondary' }}>
                                        <Phone style={{ display: 'inline', width: 12, height: 12, marginRight: 4 }} /> {preferredPhone}
                                    </Typography>
                                )}
                            </CardContent>
                        </Card>

                        {ticket.order && (
                            <Card>
                                <CardHeader>
                                    <CardTitle sx={{ display: 'flex', alignItems: 'center', gap: 1, fontSize: '1rem' }}>
                                        <Package style={{ width: 16, height: 16 }} /> Related Order
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <Typography sx={{ fontFamily: 'monospace', fontSize: '0.875rem' }}>{ticket.order.order_number}</Typography>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle sx={{ fontSize: '1rem' }}>Assignment</CardTitle>
                            </CardHeader>
                            <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 0.5, fontSize: '0.875rem' }}>
                                <Typography sx={{ color: 'text.secondary' }}>
                                    Assigned to: <Box component="span" sx={{ color: 'text.primary', fontWeight: 500 }}>{ticket.assignee?.name ?? '\u2014'}</Box>
                                </Typography>
                                <Typography sx={{ color: 'text.secondary' }}>
                                    Created by: {ticket.creator?.name ?? '\u2014'}
                                </Typography>
                                <Typography sx={{ color: 'text.secondary' }}>
                                    Created: {ticket.created_at ? new Date(ticket.created_at).toLocaleString() : '\u2014'}
                                </Typography>
                            </CardContent>
                        </Card>
                    </Box>
                </Box>

                <Dialog open={logOpen} onOpenChange={setLogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Log Interaction</DialogTitle>
                            <DialogDescription>Record a call, check-in, or other touchpoint.</DialogDescription>
                        </DialogHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                            <Box>
                                <Label htmlFor="channel">Channel</Label>
                                <Select value={logForm.data.channel} onValueChange={(v) => logForm.setData('channel', v)}>
                                    <SelectTrigger id="channel"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {channels.map((c) => (
                                            <SelectItem key={c} value={c}>{formatLabel(c)}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {logForm.errors.channel && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{logForm.errors.channel}</Typography>}
                            </Box>
                            <Box>
                                <Label htmlFor="direction">Direction</Label>
                                <Select value={logForm.data.direction} onValueChange={(v) => logForm.setData('direction', v)}>
                                    <SelectTrigger id="direction"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="outbound">Outbound (we reached out)</SelectItem>
                                        <SelectItem value="inbound">Inbound (they reached us)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </Box>
                            <Box>
                                <Label htmlFor="summary">Summary</Label>
                                <Textarea
                                    id="summary"
                                    value={logForm.data.summary}
                                    onChange={(e) => logForm.setData('summary', e.target.value)}
                                    placeholder="What was discussed? What's next?"
                                    rows={4}
                                />
                                {logForm.errors.summary && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{logForm.errors.summary}</Typography>}
                            </Box>
                            <Box>
                                <Label htmlFor="follow_up_at">Follow-up date (optional)</Label>
                                <Input
                                    id="follow_up_at"
                                    type="date"
                                    value={logForm.data.follow_up_at}
                                    onChange={(e) => logForm.setData('follow_up_at', e.target.value)}
                                />
                                {logForm.errors.follow_up_at && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{logForm.errors.follow_up_at}</Typography>}
                            </Box>
                        </Box>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setLogOpen(false)}>Cancel</Button>
                            <Button onClick={handleLogInteraction} disabled={logForm.processing}>
                                {logForm.processing ? 'Saving...' : 'Save'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={smsOpen} onOpenChange={setSmsOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Send SMS</DialogTitle>
                            <DialogDescription>
                                The message is sent via Kairos Afrika. Delivery status appears in the timeline.
                            </DialogDescription>
                        </DialogHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1.5 }}>
                            <Box>
                                <Label htmlFor="to_phone">Recipient phone</Label>
                                <Input
                                    id="to_phone"
                                    value={smsForm.data.to_phone}
                                    onChange={(e) => smsForm.setData('to_phone', e.target.value)}
                                />
                                {smsForm.errors.to_phone && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{smsForm.errors.to_phone}</Typography>}
                            </Box>
                            <Box>
                                <Label htmlFor="template_key">Template</Label>
                                <Select value={smsForm.data.template_key} onValueChange={handleTemplateChange}>
                                    <SelectTrigger id="template_key"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        {Object.keys(templates).map((k) => (
                                            <SelectItem key={k} value={k}>{formatLabel(k)}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Box>
                            <Box>
                                <Label htmlFor="body">Message</Label>
                                <Textarea
                                    id="body"
                                    value={smsForm.data.body}
                                    onChange={(e) => smsForm.setData('body', e.target.value)}
                                    rows={4}
                                    placeholder="Type your message..."
                                />
                                <Typography sx={{ mt: 0.5, fontSize: '0.75rem', color: 'text.secondary' }}>
                                    {smsForm.data.body.length} / 1000
                                </Typography>
                                {smsForm.errors.body && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{smsForm.errors.body}</Typography>}
                            </Box>
                        </Box>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setSmsOpen(false)}>Cancel</Button>
                            <Button onClick={handleSendSms} disabled={smsForm.processing}>
                                {smsForm.processing ? 'Sending...' : 'Send SMS'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={closeOpen} onOpenChange={setCloseOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Close Ticket</DialogTitle>
                            <DialogDescription>Provide a closure note explaining the resolution.</DialogDescription>
                        </DialogHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                            <Label htmlFor="closure_note">Closure Note</Label>
                            <Textarea
                                id="closure_note"
                                value={closeForm.data.closure_note}
                                onChange={(e) => closeForm.setData('closure_note', e.target.value)}
                                rows={4}
                                placeholder="How was this resolved?"
                            />
                            {closeForm.errors.closure_note && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{closeForm.errors.closure_note}</Typography>}
                        </Box>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setCloseOpen(false)}>Cancel</Button>
                            <Button onClick={handleClose} disabled={closeForm.processing}>
                                {closeForm.processing ? 'Closing...' : 'Close Ticket'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <Dialog open={assignOpen} onOpenChange={setAssignOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Reassign Ticket</DialogTitle>
                            <DialogDescription>Move this ticket to another admin or super admin.</DialogDescription>
                        </DialogHeader>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                            <Label htmlFor="assigned_to">Assignee</Label>
                            <Select
                                value={String(assignForm.data.assigned_to || '')}
                                onValueChange={(v) => assignForm.setData('assigned_to', Number(v))}
                            >
                                <SelectTrigger id="assigned_to"><SelectValue placeholder="Pick an assignee" /></SelectTrigger>
                                <SelectContent>
                                    {assignees.map((a) => (
                                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {assignForm.errors.assigned_to && <Typography sx={{ fontSize: '0.75rem', color: 'error.main', mt: 0.5 }}>{assignForm.errors.assigned_to}</Typography>}
                        </Box>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setAssignOpen(false)}>Cancel</Button>
                            <Button onClick={handleAssign} disabled={assignForm.processing}>
                                {assignForm.processing ? 'Saving...' : 'Reassign'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </Box>
        </AppLayout>
    );
}
