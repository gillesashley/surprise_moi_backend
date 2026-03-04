import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { type User } from '@/types';
import { router } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';

interface UserMenuContentProps {
    user: User;
}

export function UserMenuContent({ user }: UserMenuContentProps) {
    const cleanup = useMobileNavigation();

    return (
        <>
            <DropdownMenuLabel style={{ padding: 0, fontWeight: 400 }}>
                <div
                    style={{
                        display: 'flex',
                        alignItems: 'center',
                        gap: 8,
                        paddingLeft: 4,
                        paddingRight: 4,
                        paddingTop: 6,
                        paddingBottom: 6,
                        textAlign: 'left',
                        fontSize: '0.875rem',
                    }}
                >
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem
                    onClick={() => {
                        cleanup();
                        router.visit(edit.url());
                    }}
                >
                    <Settings style={{ marginRight: 8 }} />
                    Settings
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem
                onClick={() => {
                    cleanup();
                    router.flushAll();
                    router.post(logout.url());
                }}
                data-test="logout-button"
            >
                <LogOut style={{ marginRight: 8 }} />
                Log out
            </DropdownMenuItem>
        </>
    );
}
