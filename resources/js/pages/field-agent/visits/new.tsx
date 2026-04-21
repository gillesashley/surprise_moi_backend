import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { Head, useForm } from '@inertiajs/react';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { Check, MapPin, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface Item {
    id: number;
    item_key: string;
    category: string;
    criticality: string;
    passed: boolean | null;
    note: string | null;
}
interface Visit {
    id: string;
    status: string;
    escalated: boolean;
    storefront_photo_path: string | null;
    owner_photo_path: string | null;
    notes: string | null;
    vendor: { id: number; business_name: string | null; name: string };
}
interface Props {
    visit: Visit;
    items: Item[];
}

const CHECKLIST_LABELS: Record<string, string> = {
    'identity.person_matches_ghana_card': 'Does the person in front of you match the Ghana Card photo on file?',
    'identity.name_matches_records': 'Does the name on the physical Ghana Card match the name on file?',
    'physical.location_is_real': 'Is the claimed business address a real, findable location?',
    'physical.business_name_matches': 'Does the business at this address match the business name on file?',
    'physical.business_is_operational': 'Is there signage, stock, or active service — a real going concern?',
    'documents.business_cert_seen':
        'Have you seen the physical business certificate, and does it match the file?',
    'documents.tin_seen': 'Have you seen the physical TIN document, and does it match the file?',
    'financial.phone_reachable': 'Did you call the registered phone and have it ring / be answered?',
    'financial.momo_test_received': 'Did your GHS 1 test MoMo reach the registered mobile money number?',
};

export default function VisitForm({ visit, items }: Props) {
    const [itemState, setItemState] = useState(items);
    const submit = useForm({
        storefront_photo: null as File | null,
        owner_photo: null as File | null,
        notes: visit.notes ?? '',
        escalated: visit.escalated,
    });

    const categories = useMemo(() => {
        const byCat: Record<string, Item[]> = {};
        itemState.forEach((i) => {
            (byCat[i.category] ??= []).push(i);
        });
        return byCat;
    }, [itemState]);

    const hasStorefront = Boolean(visit.storefront_photo_path) || Boolean(submit.data.storefront_photo);
    const hasOwner = Boolean(visit.owner_photo_path) || Boolean(submit.data.owner_photo);
    const allAnswered = itemState.every((i) => i.passed !== null);
    const canSubmit = allAnswered && hasStorefront && hasOwner;
    const isTerminal = visit.status !== 'draft';

    const patchItem = async (itemId: number, body: Record<string, unknown>) => {
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        const token = match ? decodeURIComponent(match[1]) : '';
        await fetch(`/field-agent/visits/forms/${visit.id}/items/${itemId}`, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-XSRF-TOKEN': token,
            },
            body: JSON.stringify(body),
        });
    };

    const toggleItem = (item: Item, passed: boolean) => {
        setItemState((prev) => prev.map((i) => (i.id === item.id ? { ...i, passed } : i)));
        patchItem(item.id, { passed });
    };

    const saveNote = (item: Item, note: string) => {
        patchItem(item.id, { passed: item.passed, note });
    };

    const onSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        submit.post(`/field-agent/visits/forms/${visit.id}/submit`, { forceFormData: true });
    };

    const vendorLabel = visit.vendor.business_name ?? visit.vendor.name;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Visits', href: '/field-agent/visits' },
                { title: vendorLabel, href: `/field-agent/visits/${visit.vendor.id}` },
                { title: 'Form', href: `/field-agent/visits/forms/${visit.id}` },
            ]}
        >
            <Head title="Visit form" />

            <Box
                component="form"
                onSubmit={onSubmit}
                sx={{ display: 'flex', flex: 1, flexDirection: 'column', gap: 3, p: 3, maxWidth: 720, mx: 'auto', width: '100%' }}
            >
                <Box>
                    <Typography variant="h4" fontWeight={700}>
                        Verify {vendorLabel}
                    </Typography>
                    <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                        Answer every question honestly based on what you see in person.
                    </Typography>
                </Box>

                <Alert severity="success" icon={<MapPin size={18} />}>
                    GPS captured — this visit is tied to your location.
                </Alert>

                {Object.entries(categories).map(([cat, catItems]) => (
                    <Card key={cat}>
                        <CardHeader>
                            <CardTitle>
                                <Box component="span" sx={{ textTransform: 'capitalize' }}>
                                    {cat.replace('_', ' ')}
                                </Box>
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Stack spacing={2}>
                                {catItems.map((item) => (
                                    <Box
                                        key={item.id}
                                        sx={{
                                            p: 2,
                                            borderRadius: 1,
                                            border: '1px solid',
                                            borderColor: 'divider',
                                        }}
                                    >
                                        <Typography variant="body2" sx={{ mb: 1.5 }}>
                                            {CHECKLIST_LABELS[item.item_key] ?? item.item_key}
                                        </Typography>
                                        <Box sx={{ display: 'flex', gap: 1, mb: 1.5 }}>
                                            <Button
                                                type="button"
                                                variant={item.passed === true ? 'default' : 'outline'}
                                                disabled={isTerminal}
                                                onClick={() => toggleItem(item, true)}
                                                sx={{
                                                    flex: 1,
                                                    gap: 0.5,
                                                    ...(item.passed === true && {
                                                        backgroundColor: 'success.main',
                                                        '&:hover': { backgroundColor: 'success.dark' },
                                                    }),
                                                }}
                                            >
                                                <Check size={16} />
                                                Pass
                                            </Button>
                                            <Button
                                                type="button"
                                                variant={item.passed === false ? 'destructive' : 'outline'}
                                                disabled={isTerminal}
                                                onClick={() => toggleItem(item, false)}
                                                sx={{ flex: 1, gap: 0.5 }}
                                            >
                                                <X size={16} />
                                                Fail
                                            </Button>
                                        </Box>
                                        <TextField
                                            size="small"
                                            fullWidth
                                            disabled={isTerminal}
                                            placeholder="Optional note"
                                            defaultValue={item.note ?? ''}
                                            onBlur={(e) => saveNote(item, e.target.value)}
                                        />
                                    </Box>
                                ))}
                            </Stack>
                        </CardContent>
                    </Card>
                ))}

                <Card>
                    <CardHeader>
                        <CardTitle>Required evidence</CardTitle>
                        <CardDescription>
                            Capture both photos on site — we use them to audit your visit.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Stack spacing={2}>
                            <Box>
                                <Typography variant="body2" sx={{ mb: 0.5 }}>
                                    Storefront photo {hasStorefront && '✓'}
                                </Typography>
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    onChange={(e) => submit.setData('storefront_photo', e.target.files?.[0] ?? null)}
                                />
                            </Box>
                            <Box>
                                <Typography variant="body2" sx={{ mb: 0.5 }}>
                                    Owner-at-premises photo {hasOwner && '✓'}
                                </Typography>
                                <input
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    onChange={(e) => submit.setData('owner_photo', e.target.files?.[0] ?? null)}
                                />
                            </Box>
                        </Stack>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Notes & escalation</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Stack spacing={2}>
                            <TextField
                                label="General notes"
                                multiline
                                minRows={3}
                                fullWidth
                                disabled={isTerminal}
                                value={submit.data.notes}
                                onChange={(e) => submit.setData('notes', e.target.value)}
                            />
                            <FormControlLabel
                                control={
                                    <Checkbox
                                        disabled={isTerminal}
                                        checked={submit.data.escalated}
                                        onChange={(e) => submit.setData('escalated', e.target.checked)}
                                    />
                                }
                                label="Escalate to admin — tick if something feels off but you can't prove it."
                            />
                        </Stack>
                    </CardContent>
                </Card>

                <Button
                    type="submit"
                    size="lg"
                    disabled={!canSubmit || submit.processing || isTerminal}
                    sx={{ py: 1.5 }}
                >
                    {isTerminal
                        ? `Visit ${visit.status}`
                        : submit.processing
                          ? 'Submitting…'
                          : 'Submit visit'}
                </Button>
            </Box>
        </AppLayout>
    );
}
