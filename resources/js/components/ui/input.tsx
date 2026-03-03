import * as React from 'react';
import OutlinedInput from '@mui/material/OutlinedInput';

function Input({
    className,
    type,
    ref,
    ...props
}: React.ComponentProps<'input'>) {
    return (
        <OutlinedInput
            type={type}
            data-slot="input"
            size="small"
            fullWidth
            className={className}
            inputRef={ref}
            inputProps={{
                'aria-invalid': props['aria-invalid'],
            }}
            {...(props as React.ComponentProps<typeof OutlinedInput>)}
        />
    );
}

export { Input };
