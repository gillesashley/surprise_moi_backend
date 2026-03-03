import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    return (
        <>
            <Box
                sx={{
                    display: 'flex',
                    aspectRatio: '1/1',
                    width: 32,
                    height: 32,
                    alignItems: 'center',
                    justifyContent: 'center',
                    borderRadius: 1,
                    bgcolor: 'primary.main',
                    color: 'primary.contrastText',
                }}
            >
                <AppLogoIcon style={{ width: 20, height: 20 }} />
            </Box>
            <Box
                sx={{
                    ml: 0.5,
                    display: 'grid',
                    flex: 1,
                    textAlign: 'left',
                    fontSize: '0.875rem',
                }}
            >
                <Typography
                    component="span"
                    sx={{
                        mb: 0.25,
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap',
                        lineHeight: 1.25,
                        fontWeight: 600,
                        fontSize: 'inherit',
                    }}
                >
                    SurpriseMoi
                </Typography>
            </Box>
        </>
    );
}
