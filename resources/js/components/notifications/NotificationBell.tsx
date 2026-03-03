import { Bell } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { NotificationDropdown } from './NotificationDropdown';
import { useNotifications } from '@/hooks/useNotifications';
import MuiBadge from '@mui/material/Badge';

export function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const { unreadCount } = useNotifications();

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <MuiBadge
                    badgeContent={unreadCount}
                    color="error"
                    max={99}
                >
                    <Button variant="ghost" size="icon">
                        <Bell style={{ width: 20, height: 20 }} />
                    </Button>
                </MuiBadge>
            </PopoverTrigger>
            <PopoverContent align="end" style={{ width: 320, padding: 0 }}>
                <NotificationDropdown onClose={() => setIsOpen(false)} />
            </PopoverContent>
        </Popover>
    );
}
