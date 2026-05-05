import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    Clock,
    CreditCard,
    FileText,
    XCircle,
} from 'lucide-react';
import type { FeedRowType } from './types';

export const ICON_FOR_TYPE: Record<FeedRowType, typeof FileText> = {
    submitted: FileText,
    paid: CreditCard,
    approved: CheckCircle2,
    rejected: XCircle,
    flagged: AlertTriangle,
    flag_reminded: BellRing,
    flag_expired: Clock,
};

export const TITLE_FOR_TYPE: Record<FeedRowType, string> = {
    submitted: 'submitted application',
    paid: 'completed onboarding payment',
    approved: 'approved',
    rejected: 'rejected',
    flagged: 'flagged',
    flag_reminded: 'flag reminder sent',
    flag_expired: 'flag expired',
};
