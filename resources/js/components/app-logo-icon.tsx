import { HTMLAttributes } from 'react';

export default function AppLogoIcon(props: HTMLAttributes<HTMLImageElement>) {
    return (
        <img {...props} src="/images/logo-black.svg" alt="SurpriseMoi Logo" />
    );
}
