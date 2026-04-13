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
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { ArrowLeft, Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface UserOption {
    id: number;
    name: string;
    email: string;
    role: string | null;
}

const USER_CATEGORIES: Array<{ label: string; value: string }> = [
    { label: 'Customer', value: 'customer' },
    { label: 'Vendor', value: 'vendor' },
    { label: 'Influencer', value: 'influencer' },
    { label: 'Field Agent', value: 'field_agent' },
    { label: 'Marketer', value: 'marketer' },
    { label: 'Admin', value: 'admin' },
    { label: 'Super Admin', value: 'super_admin' },
];

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

interface Props {
    bonusPercentages: Record<string, number>;
    tierFees: { tier1: number; tier2: number };
}

export default function ReferralCodeCreate({ bonusPercentages, tierFees }: Props) {
    const [selectedCategory, setSelectedCategory] = useState<string>('');
    const [selectedUserId, setSelectedUserId] = useState<string>('');
    const [users, setUsers] = useState<UserOption[]>([]);
    const [searchTerm, setSearchTerm] = useState<string>('');
    const [loadingUsers, setLoadingUsers] = useState(false);

    const selectedPct = bonusPercentages[selectedCategory] ?? 0;
    const tier1Bonus = ((selectedPct / 100) * tierFees.tier1).toFixed(2);
    const tier2Bonus = ((selectedPct / 100) * tierFees.tier2).toFixed(2);

    // Fetch users whenever the category changes or the search term changes (debounced)
    useEffect(() => {
        if (!selectedCategory) {
            setUsers([]);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            setLoadingUsers(true);
            const params = new URLSearchParams({ role: selectedCategory });
            if (searchTerm.trim()) {
                params.set('q', searchTerm.trim());
            }

            fetch(`/dashboard/referral-codes/users-by-role?${params.toString()}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            })
                .then((res) => res.json())
                .then((json) => {
                    setUsers(json.data || []);
                    setLoadingUsers(false);
                })
                .catch((err) => {
                    if (err.name !== 'AbortError') {
                        setLoadingUsers(false);
                    }
                });
        }, 300);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [selectedCategory, searchTerm]);

    const handleCategoryChange = (value: string) => {
        setSelectedCategory(value);
        setSelectedUserId('');
        setSearchTerm('');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Referral Code" />

            <Box sx={{ mb: 3, display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Typography variant="h4" sx={{ fontWeight: 700 }}>Create Referral Code</Typography>
                <Button variant="outline" asChild>
                    <Link href="/dashboard/referral-codes">
                        <ArrowLeft style={{ marginRight: 8, width: 16, height: 16 }} />
                        Back to Referral Codes
                    </Link>
                </Button>
            </Box>

            <Card>
                <CardHeader>
                    <CardTitle>Referral Code Details</CardTitle>
                    <CardDescription>
                        Create a new referral code for any user on the platform
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action="/dashboard/referral-codes"
                        method="post"
                    >
                        {({ errors, processing }) => (
                            <Box sx={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
                                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="user_category">
                                            User Category *
                                        </Label>
                                        <Select
                                            value={selectedCategory}
                                            onValueChange={handleCategoryChange}
                                        >
                                            <SelectTrigger id="user_category">
                                                <SelectValue placeholder="Select category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {USER_CATEGORIES.map((cat) => (
                                                    <SelectItem key={cat.value} value={cat.value}>
                                                        {cat.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
                                        <Label htmlFor="influencer_id">
                                            User *
                                        </Label>
                                        <Select
                                            name="influencer_id"
                                            value={selectedUserId}
                                            onValueChange={setSelectedUserId}
                                            disabled={!selectedCategory}
                                            required
                                        >
                                            <SelectTrigger id="influencer_id">
                                                <SelectValue
                                                    placeholder={
                                                        !selectedCategory
                                                            ? 'Pick a category first'
                                                            : loadingUsers
                                                              ? 'Loading...'
                                                              : 'Select user'
                                                    }
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <Box sx={{ p: 1 }}>
                                                    <Box sx={{ position: 'relative' }}>
                                                        <Search
                                                            style={{
                                                                position: 'absolute',
                                                                left: 8,
                                                                top: '50%',
                                                                transform: 'translateY(-50%)',
                                                                width: 16,
                                                                height: 16,
                                                                color: '#9ca3af',
                                                            }}
                                                        />
                                                        <Input
                                                            placeholder="Search name or email..."
                                                            value={searchTerm}
                                                            onChange={(e) => setSearchTerm(e.target.value)}
                                                            onKeyDown={(e) => e.stopPropagation()}
                                                            onMouseDown={(e) => e.stopPropagation()}
                                                            onClick={(e) => e.stopPropagation()}
                                                            style={{ paddingLeft: 32 }}
                                                        />
                                                    </Box>
                                                </Box>
                                                {users.length === 0 && !loadingUsers && (
                                                    <Box sx={{ px: 2, py: 1.5, fontSize: '0.875rem', color: 'text.secondary' }}>
                                                        No users found
                                                    </Box>
                                                )}
                                                {users.map((user) => (
                                                    <SelectItem key={user.id} value={user.id.toString()}>
                                                        {user.name} ({user.email})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {errors.influencer_id && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.influencer_id}
                                            </Typography>
                                        )}
                                    </Box>
                                </Box>

                                <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                            {errors.description}
                                        </Typography>
                                    )}
                                </Box>

                                {selectedCategory && (
                                    <Box
                                        sx={{
                                            p: 2,
                                            borderRadius: 1,
                                            bgcolor: 'action.hover',
                                            display: 'flex',
                                            flexDirection: 'column',
                                            gap: 0.5,
                                        }}
                                    >
                                        <Typography variant="body2" sx={{ fontWeight: 500 }}>
                                            Registration Bonus (auto-calculated)
                                        </Typography>
                                        <Typography variant="body2" color="text.secondary">
                                            Tier 1: GH₵{tier1Bonus} | Tier 2: GH₵{tier2Bonus}
                                        </Typography>
                                        <Typography variant="caption" color="text.secondary">
                                            Based on {selectedPct}% of onboarding fee for{' '}
                                            {USER_CATEGORIES.find((c) => c.value === selectedCategory)?.label}
                                        </Typography>
                                    </Box>
                                )}

                                <Box sx={{ display: 'grid', gap: 3, gridTemplateColumns: { md: 'repeat(2, 1fr)' } }}>
                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Leave empty for unlimited usage
                                        </Typography>
                                        {errors.max_usages && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.max_usages}
                                            </Typography>
                                        )}
                                    </Box>

                                    <Box sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
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
                                        <Typography sx={{ fontSize: '0.875rem', color: 'text.secondary' }}>
                                            Leave empty for no expiration
                                        </Typography>
                                        {errors.expires_at && (
                                            <Typography sx={{ fontSize: '0.875rem', color: 'error.main' }}>
                                                {errors.expires_at}
                                            </Typography>
                                        )}
                                    </Box>
                                </Box>

                                <Box sx={{ display: 'flex', justifyContent: 'flex-end', gap: 1.5 }}>
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
                                </Box>
                            </Box>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
