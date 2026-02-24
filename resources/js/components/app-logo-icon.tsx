import { HTMLAttributes } from 'react';

export default function AppLogoIcon(props: HTMLAttributes<HTMLImageElement>) {
    return (
        <img {...props} src="/images/logo-purple.svg" alt="SurpriseMoi Logo" />
    );
}
