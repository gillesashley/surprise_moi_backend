import OutlinedInput, { OutlinedInputProps } from '@mui/material/OutlinedInput';

function Input({
    className,
    type,
    inputRef,
    ...props
}: OutlinedInputProps) {
    return (
        <OutlinedInput
            type={type}
            data-slot="input"
            size="small"
            fullWidth
            className={className}
            inputRef={inputRef}
            {...props}
        />
    );
}

export { Input };
