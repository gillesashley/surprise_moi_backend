import { Appearance, useAppearance } from '@/hooks/use-appearance';
import Box from '@mui/material/Box';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import { LucideIcon, Monitor, Moon, Sun } from 'lucide-react';
import { type SxProps, type Theme } from '@mui/material/styles';

export default function AppearanceToggleTab({
    sx: sxProp,
    ...props
}: { sx?: SxProps<Theme> } & Omit<React.ComponentProps<typeof Box>, 'sx'>) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <ToggleButtonGroup
            value={appearance}
            exclusive
            onChange={(_e, newValue) => {
                if (newValue !== null) {
                    updateAppearance(newValue as Appearance);
                }
            }}
            sx={[
                {
                    display: 'inline-flex',
                    gap: 0.5,
                    borderRadius: 2,
                    bgcolor: 'action.hover',
                    p: 0.5,
                    '& .MuiToggleButtonGroup-grouped': {
                        border: 0,
                        '&:not(:first-of-type)': {
                            borderRadius: 1,
                            ml: 0,
                        },
                        '&:first-of-type': {
                            borderRadius: 1,
                        },
                    },
                },
                ...(Array.isArray(sxProp) ? sxProp : sxProp ? [sxProp] : []),
            ]}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <ToggleButton
                    key={value}
                    value={value}
                    sx={{
                        px: 1.75,
                        py: 0.75,
                        textTransform: 'none',
                        transition: 'background-color 0.15s, color 0.15s',
                        color: 'text.secondary',
                        '&:hover': {
                            bgcolor: 'action.hover',
                            color: 'text.primary',
                        },
                        '&.Mui-selected': {
                            bgcolor: 'background.paper',
                            boxShadow: 1,
                            color: 'text.primary',
                            '&:hover': {
                                bgcolor: 'background.paper',
                            },
                        },
                    }}
                >
                    <Icon style={{ width: 16, height: 16, marginLeft: -4 }} />
                    <Box
                        component="span"
                        sx={{ ml: 0.75, fontSize: '0.875rem' }}
                    >
                        {label}
                    </Box>
                </ToggleButton>
            ))}
        </ToggleButtonGroup>
    );
}
