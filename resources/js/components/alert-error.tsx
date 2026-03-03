import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import Box from '@mui/material/Box';
import { AlertCircleIcon } from 'lucide-react';

export default function AlertError({
    errors,
    title,
}: {
    errors: string[];
    title?: string;
}) {
    return (
        <Alert variant="destructive">
            <AlertCircleIcon />
            <AlertTitle>{title || 'Something went wrong.'}</AlertTitle>
            <AlertDescription>
                <Box
                    component="ul"
                    sx={{
                        listStyleType: 'disc',
                        listStylePosition: 'inside',
                        fontSize: '0.875rem',
                    }}
                >
                    {Array.from(new Set(errors)).map((error, index) => (
                        <li key={index}>{error}</li>
                    ))}
                </Box>
            </AlertDescription>
        </Alert>
    );
}
