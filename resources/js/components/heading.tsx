import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export default function Heading({
    title,
    description,
}: {
    title: string;
    description?: string;
}) {
    return (
        <Box sx={{ mb: 4, display: 'flex', flexDirection: 'column', gap: 0.25 }}>
            <Typography variant="h5" fontWeight={600} sx={{ letterSpacing: '-0.01em' }}>
                {title}
            </Typography>
            {description && (
                <Typography variant="body2" color="text.secondary">
                    {description}
                </Typography>
            )}
        </Box>
    );
}
