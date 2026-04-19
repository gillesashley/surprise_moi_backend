import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import { Copy, Check } from 'lucide-react';
import { useState } from 'react';

interface Props {
    code: string;
}

export default function ReferralCodeCard({ code }: Props) {
    const [copied, setCopied] = useState(false);

    const handleCopy = async () => {
        try {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Silent fail — non-HTTPS or denied clipboard permission
        }
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Your Referral Code</CardTitle>
            </CardHeader>
            <CardContent>
                <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                    <Typography
                        variant="h5"
                        sx={{ fontFamily: 'monospace', fontWeight: 700, letterSpacing: '0.05em' }}
                    >
                        {code}
                    </Typography>
                    <button
                        type="button"
                        onClick={handleCopy}
                        className="flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
                        aria-label="Copy referral code"
                    >
                        {copied ? <Check size={16} /> : <Copy size={16} />}
                        {copied ? 'Copied' : 'Copy'}
                    </button>
                </Box>
                <Typography variant="body2" color="text.secondary" sx={{ mt: 1 }}>
                    Share this code with a vendor at registration so they are attributed to you.
                </Typography>
            </CardContent>
        </Card>
    );
}
