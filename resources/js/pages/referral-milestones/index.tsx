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
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { CheckCircle, Gift } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface MilestoneReward {
    id: number;
    threshold: number;
    points_at_milestone: number;
    status: 'pending' | 'fulfilled' | 'cancelled';
    reward_type: string | null;
    reward_description: string | null;
    reward_value: number | null;
    admin_notes: string | null;
    created_at: string;
    fulfilled_at: string | null;
    user: {
        id: number;
        name: string;
        email: string;
        role: string | null;
    };
    fulfilled_by: {
        id: number;
        name: string;
    } | null;
}

interface PaginatedRewards {
    data: MilestoneReward[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    rewards: PaginatedRewards;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Referral Codes',
        href: '/dashboard/referral-codes',
    },
    {
        title: 'Milestone Rewards',
        href: '/dashboard/referral-milestones',
    },
];

const formatNumber = (n: number) => new Intl.NumberFormat('en-GH').format(n);

const formatDate = (iso: string) =>
    new Date(iso).toLocaleDateString('en-GH', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });

export default function ReferralMilestonesIndex({ rewards }: Props) {
    const [activeReward, setActiveReward] = useState<MilestoneReward | null>(null);
    const [rewardType, setRewardType] = useState('');
    const [rewardDescription, setRewardDescription] = useState('');
    const [rewardValue, setRewardValue] = useState('');
    const [adminNotes, setAdminNotes] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const openFulfillDialog = (reward: MilestoneReward) => {
        setActiveReward(reward);
        setRewardType(reward.reward_type ?? '');
        setRewardDescription(reward.reward_description ?? '');
        setRewardValue(reward.reward_value?.toString() ?? '');
        setAdminNotes(reward.admin_notes ?? '');
    };

    const closeDialog = () => {
        setActiveReward(null);
        setRewardType('');
        setRewardDescription('');
        setRewardValue('');
        setAdminNotes('');
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!activeReward) return;
        setSubmitting(true);
        router.patch(
            `/dashboard/referral-milestones/${activeReward.id}/fulfill`,
            {
                reward_type: rewardType,
                reward_description: rewardDescription,
                reward_value: rewardValue ? parseFloat(rewardValue) : null,
                admin_notes: adminNotes,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    setSubmitting(false);
                    closeDialog();
                },
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Milestone Rewards" />

            <Box sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 2, p: 2 }}>
                <Box>
                    <Typography variant="h5" sx={{ fontWeight: 700 }}>
                        Milestone Rewards
                    </Typography>
                    <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                        Users who have crossed a referral points milestone. Pending rewards need platform fulfillment.
                    </Typography>
                </Box>

                <Card>
                    <CardHeader>
                        <CardTitle>All Milestone Rewards</CardTitle>
                        <CardDescription>
                            Pending rewards are listed first.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Box sx={{ overflowX: 'auto' }}>
                            <Box component="table" sx={{ width: '100%' }}>
                                <thead>
                                    <Box component="tr" sx={{ borderBottom: 1, borderColor: 'divider' }}>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>User</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Role</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Threshold</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Points</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Status</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Reward</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Crossed On</Box>
                                        <Box component="th" sx={{ p: 1, textAlign: 'left', fontSize: '0.875rem', fontWeight: 500 }}>Action</Box>
                                    </Box>
                                </thead>
                                <tbody>
                                    {rewards.data.map((r) => (
                                        <Box
                                            component="tr"
                                            key={r.id}
                                            sx={{ borderBottom: 1, borderColor: 'divider', '&:hover': { bgcolor: 'action.hover' } }}
                                        >
                                            <Box component="td" sx={{ p: 1 }}>
                                                <Typography sx={{ fontWeight: 500 }}>{r.user.name}</Typography>
                                                <Typography sx={{ fontSize: '0.75rem', color: 'text.secondary' }}>{r.user.email}</Typography>
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem', textTransform: 'capitalize' }}>
                                                {(r.user.role || 'customer').replace('_', ' ')}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontWeight: 600 }}>
                                                {formatNumber(r.threshold)} pts
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {formatNumber(r.points_at_milestone)}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {r.status === 'pending' && (
                                                    <Chip label="PENDING" color="warning" size="small" variant="outlined" />
                                                )}
                                                {r.status === 'fulfilled' && (
                                                    <Chip
                                                        icon={<CheckCircle style={{ width: 12, height: 12 }} />}
                                                        label="FULFILLED"
                                                        color="success"
                                                        size="small"
                                                        variant="outlined"
                                                    />
                                                )}
                                                {r.status === 'cancelled' && (
                                                    <Chip label="CANCELLED" color="default" size="small" variant="outlined" />
                                                )}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {r.reward_description || <Box component="span" sx={{ color: 'text.secondary' }}>—</Box>}
                                            </Box>
                                            <Box component="td" sx={{ p: 1, fontSize: '0.875rem' }}>
                                                {formatDate(r.created_at)}
                                            </Box>
                                            <Box component="td" sx={{ p: 1 }}>
                                                {r.status === 'pending' && (
                                                    <Button size="sm" onClick={() => openFulfillDialog(r)}>
                                                        <Gift style={{ marginRight: 6, width: 14, height: 14 }} />
                                                        Fulfill
                                                    </Button>
                                                )}
                                            </Box>
                                        </Box>
                                    ))}
                                </tbody>
                            </Box>
                        </Box>

                        {rewards.data.length === 0 && (
                            <Box sx={{ py: 4, textAlign: 'center', color: 'text.secondary' }}>
                                No milestone rewards yet.
                            </Box>
                        )}
                    </CardContent>
                </Card>
            </Box>

            <Dialog open={activeReward !== null} onOpenChange={(open) => !open && closeDialog()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Fulfill Milestone Reward</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit}>
                        <Box sx={{ display: 'flex', flexDirection: 'column', gap: 2, py: 2 }}>
                            {activeReward && (
                                <Box sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                    {activeReward.user.name} crossed{' '}
                                    <strong>{formatNumber(activeReward.threshold)} points</strong>.
                                </Box>
                            )}
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="reward_type">Reward Type</Label>
                                <Input
                                    id="reward_type"
                                    value={rewardType}
                                    onChange={(e) => setRewardType(e.target.value)}
                                    placeholder="e.g. cash, gift, power bank"
                                />
                            </Box>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="reward_description">Reward Description</Label>
                                <Textarea
                                    id="reward_description"
                                    rows={2}
                                    value={rewardDescription}
                                    onChange={(e) => setRewardDescription(e.target.value)}
                                    placeholder="What was given to the user..."
                                />
                            </Box>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="reward_value">Reward Value (GHS)</Label>
                                <Input
                                    id="reward_value"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={rewardValue}
                                    onChange={(e) => setRewardValue(e.target.value)}
                                    placeholder="Optional GHS value for reporting"
                                />
                            </Box>
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                <Label htmlFor="admin_notes">Admin Notes</Label>
                                <Textarea
                                    id="admin_notes"
                                    rows={2}
                                    value={adminNotes}
                                    onChange={(e) => setAdminNotes(e.target.value)}
                                    placeholder="Internal notes (optional)"
                                />
                            </Box>
                        </Box>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Saving...' : 'Mark Fulfilled'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
