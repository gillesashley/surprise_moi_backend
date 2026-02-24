import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useNotifications } from '@/hooks/useNotifications';
import { Bell } from 'lucide-react';
import { useState } from 'react';
import { NotificationDropdown } from './NotificationDropdown';

export function NotificationBell() {
    const [isOpen, setIsOpen] = useState(false);
    const { unreadCount } = useNotifications();

    return (
        <Popover open={isOpen} onOpenChange={setIsOpen}>
            <PopoverTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unreadCount > 0 && (
                        <span className="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive text-xs text-white">
                            {unreadCount > 99 ? '99+' : unreadCount}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0" sideOffset={5}>
                <NotificationDropdown onClose={() => setIsOpen(false)} />
            </PopoverContent>
        </Popover>
    );
}
