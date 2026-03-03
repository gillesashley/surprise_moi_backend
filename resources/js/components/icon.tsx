import { type LucideProps } from 'lucide-react';
import { type CSSProperties, type ComponentType } from 'react';

interface IconProps extends Omit<LucideProps, 'ref'> {
    iconNode: ComponentType<LucideProps>;
    style?: CSSProperties;
}

export function Icon({
    iconNode: IconComponent,
    className,
    style,
    ...props
}: IconProps) {
    const defaultStyle: CSSProperties = className
        ? { ...style }
        : { width: 16, height: 16, ...style };

    return <IconComponent className={className} style={defaultStyle} {...props} />;
}
