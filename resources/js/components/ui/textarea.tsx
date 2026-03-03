import * as React from 'react';
import OutlinedInput from '@mui/material/OutlinedInput';

function Textarea({
    className,
    rows,
    ref,
    ...props
}: React.ComponentProps<'textarea'>) {
    return (
        <OutlinedInput
            multiline
            minRows={rows ?? 3}
            size="small"
            fullWidth
            className={className}
            inputRef={ref}
            {...(props as React.ComponentProps<typeof OutlinedInput>)}
        />
    );
}

export { Textarea };
