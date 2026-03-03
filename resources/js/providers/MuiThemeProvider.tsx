import CssBaseline from '@mui/material/CssBaseline';
import { StyledEngineProvider, ThemeProvider } from '@mui/material/styles';
import { useEffect, useState } from 'react';
import { darkTheme, lightTheme } from '../theme/mui-theme';

function getIsDark(): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    return document.documentElement.classList.contains('dark');
}

export function MuiThemeProvider({ children }: { children: React.ReactNode }) {
    const [isDark, setIsDark] = useState(getIsDark);

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setIsDark(getIsDark());
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return (
        <StyledEngineProvider injectFirst>
            <ThemeProvider theme={isDark ? darkTheme : lightTheme}>
                <CssBaseline enableColorScheme />
                {children}
            </ThemeProvider>
        </StyledEngineProvider>
    );
}
