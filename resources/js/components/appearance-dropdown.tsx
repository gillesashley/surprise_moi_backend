import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import Box from '@mui/material/Box';
import { Monitor, Moon, Sun } from 'lucide-react';

export default function AppearanceToggleDropdown({
    ...props
}: React.ComponentProps<typeof Box>) {
    const { appearance, updateAppearance } = useAppearance();

    const getCurrentIcon = () => {
        switch (appearance) {
            case 'dark':
                return <Moon style={{ width: 20, height: 20 }} />;
            case 'light':
                return <Sun style={{ width: 20, height: 20 }} />;
            default:
                return <Monitor style={{ width: 20, height: 20 }} />;
        }
    };

    return (
        <Box {...props}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon">
                        {getCurrentIcon()}
                        <Box
                            component="span"
                            sx={{
                                position: 'absolute',
                                width: 1,
                                height: 1,
                                overflow: 'hidden',
                                clip: 'rect(0,0,0,0)',
                                whiteSpace: 'nowrap',
                                border: 0,
                            }}
                        >
                            Toggle theme
                        </Box>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => updateAppearance('light')}>
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 1,
                            }}
                        >
                            <Sun style={{ width: 20, height: 20 }} />
                            Light
                        </Box>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('dark')}>
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 1,
                            }}
                        >
                            <Moon style={{ width: 20, height: 20 }} />
                            Dark
                        </Box>
                    </DropdownMenuItem>
                    <DropdownMenuItem
                        onClick={() => updateAppearance('system')}
                    >
                        <Box
                            sx={{
                                display: 'flex',
                                alignItems: 'center',
                                gap: 1,
                            }}
                        >
                            <Monitor style={{ width: 20, height: 20 }} />
                            System
                        </Box>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </Box>
    );
}
