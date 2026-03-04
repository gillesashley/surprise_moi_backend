import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <Box
                sx={{
                    display: 'flex',
                    width: 32,
                    height: 32,
                    alignItems: 'center',
                    justifyContent: 'center',
                    flexShrink: 0,
                }}
            >
                <AppLogoIcon style={{ width: 32, height: 32 }} />
            </Box>
            <Box
                sx={{
                    ml: 1,
                    display: 'grid',
                    flex: 1,
                    textAlign: 'left',
                    fontSize: '0.875rem',
                }}
            >
                <Typography
                    component="span"
                    sx={{
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        lineHeight: 1.25,
                        fontWeight: 700,
                        fontSize: '0.9375rem',
                        letterSpacing: '-0.01em',
                    }}
                >
                    SurpriseMoi
                </Typography>
            </Box>
        </>
    );
}
