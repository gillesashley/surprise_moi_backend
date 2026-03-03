import Typography from '@mui/material/Typography';

export default function InputError({
    message,
}: {
    message?: string;
}) {
    return message ? (
        <Typography variant="caption" color="error">
            {message}
        </Typography>
    ) : null;
}
