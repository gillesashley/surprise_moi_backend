import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';

type LinkProps = ComponentProps<typeof Link>;

export default function TextLink({
    children,
    ...props
}: LinkProps) {
    return (
        <Link
            style={{
                textDecoration: 'underline',
                textUnderlineOffset: '4px',
                transition: 'color 0.3s ease-out, text-decoration-color 0.3s ease-out',
            }}
            {...props}
        >
            {children}
        </Link>
    );
}
