import { createTheme, type ThemeOptions } from '@mui/material/styles';

const sharedTypography: ThemeOptions['typography'] = {
    fontFamily: [
        'Outfit',
        'ui-sans-serif',
        'system-ui',
        'sans-serif',
        'Apple Color Emoji',
        'Segoe UI Emoji',
        'Segoe UI Symbol',
        'Noto Color Emoji',
    ].join(','),
};

const sharedShape: ThemeOptions['shape'] = {
    borderRadius: 4,
};

const componentOverrides: ThemeOptions['components'] = {
    MuiButton: {
        defaultProps: {
            disableElevation: true,
        },
        styleOverrides: {
            root: {
                textTransform: 'none',
            },
        },
    },
    MuiCard: {
        defaultProps: {
            variant: 'outlined',
        },
        styleOverrides: {
            root: {
                boxShadow: '0 1px 3px 0 rgba(0,0,0,0.04), 0 1px 2px -1px rgba(0,0,0,0.04)',
            },
        },
    },
    MuiPaper: {
        defaultProps: {
            elevation: 0,
        },
        styleOverrides: {
            root: {
                boxShadow: '0 1px 2px 0 rgba(0,0,0,0.03)',
            },
        },
    },
    MuiTextField: {
        defaultProps: {
            variant: 'outlined',
            size: 'small',
        },
    },
    MuiOutlinedInput: {
        defaultProps: {
            size: 'small',
        },
    },
    MuiChip: {
        defaultProps: {
            size: 'small',
        },
    },
};

export const lightTheme = createTheme({
    palette: {
        mode: 'light',
        primary: {
            main: '#830E98',
            contrastText: '#FAFAFA',
        },
        secondary: {
            main: '#23093B',
            contrastText: '#FAFAFA',
        },
        warning: {
            main: '#F6A62B',
        },
        error: {
            main: '#DB1515',
            contrastText: '#FFFFFF',
        },
        success: {
            main: '#3DB441',
            contrastText: '#FFFFFF',
        },
        background: {
            default: '#FAF5FC', // Very light purple tint
            paper: '#FFFFFF',
        },
        text: {
            primary: '#1A1A1A',
            secondary: '#737373',
        },
        divider: '#E5D9EA',
    },
    typography: sharedTypography,
    shape: sharedShape,
    components: componentOverrides,
});

export const darkTheme = createTheme({
    palette: {
        mode: 'dark',
        primary: {
            main: '#B44DC9',
            contrastText: '#1A1025',
        },
        secondary: {
            main: '#3D1F5C',
            contrastText: '#FAFAFA',
        },
        warning: {
            main: '#F6A62B',
        },
        error: {
            main: '#C41212',
            contrastText: '#FAE5E5',
        },
        success: {
            main: '#3DB441',
            contrastText: '#FFFFFF',
        },
        background: {
            default: '#1A1025', // Dark purple
            paper: '#241535',
        },
        text: {
            primary: '#FAFAFA',
            secondary: '#B8A3C7',
        },
        divider: '#3D2A52',
    },
    typography: sharedTypography,
    shape: sharedShape,
    components: componentOverrides,
});
