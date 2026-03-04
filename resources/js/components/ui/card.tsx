import * as React from 'react';
import {
    Card as MuiCard,
    CardContent as MuiCardContent,
    CardActions,
    Typography,
    Box,
} from '@mui/material';

function Card({ className, children, ...props }: React.ComponentProps<'div'>) {
    return (
        <MuiCard
            variant="outlined"
            data-slot="card"
            className={className}
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 3,
                borderRadius: 3,
                py: 3,
                boxShadow: '0 1px 3px rgba(0, 0, 0, 0.04)',
            }}
            {...(props as object)}
        >
            {children}
        </MuiCard>
    );
}

function CardHeader({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <Box
            data-slot="card-header"
            className={className}
            sx={{
                display: 'flex',
                flexDirection: 'column',
                gap: 0.75,
                px: 3,
            }}
            {...props}
        >
            {children}
        </Box>
    );
}

function CardTitle({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    const { ref: _ref, ...rest } = props;
    return (
        <Typography
            variant="h6"
            component="div"
            data-slot="card-title"
            className={className}
            sx={{
                fontWeight: 600,
                lineHeight: 1,
            }}
            {...(rest as object)}
        >
            {children}
        </Typography>
    );
}

function CardDescription({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    const { ref: _ref, ...rest } = props;
    return (
        <Typography
            variant="body2"
            component="div"
            color="text.secondary"
            data-slot="card-description"
            className={className}
            {...(rest as object)}
        >
            {children}
        </Typography>
    );
}

function CardContent({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <MuiCardContent
            data-slot="card-content"
            className={className}
            sx={{
                px: 3,
                py: 0,
                '&:last-child': {
                    pb: 0,
                },
            }}
            {...(props as object)}
        >
            {children}
        </MuiCardContent>
    );
}

function CardFooter({
    className,
    children,
    ...props
}: React.ComponentProps<'div'>) {
    return (
        <CardActions
            data-slot="card-footer"
            className={className}
            sx={{
                display: 'flex',
                alignItems: 'center',
                px: 3,
            }}
            {...(props as object)}
        >
            {children}
        </CardActions>
    );
}

export {
    Card,
    CardHeader,
    CardFooter,
    CardTitle,
    CardDescription,
    CardContent,
};
