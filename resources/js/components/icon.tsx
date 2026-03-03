import { type LucideProps } from 'lucide-react';
import { type CSSProperties, type ComponentType } from 'react';

interface IconProps extends Omit<LucideProps, 'ref'> {
    iconNode: ComponentType<LucideProps>;
    style?: CSSProperties;
}

export function Icon({
    iconNode: IconComponent,
    style,
    ...props
}: IconProps) {
    return <IconComponent style={{ width: 16, height: 16, ...style }} {...props} />;
}
