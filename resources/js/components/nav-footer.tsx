import { Icon } from '@/components/icon';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { type ComponentPropsWithoutRef } from 'react';

export function NavFooter({
    items,
    style,
    ...props
}: ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items: NavItem[];
}) {
    const { state } = useSidebar();
    const isCollapsed = state === 'collapsed';

    return (
        <SidebarGroup
            {...props}
            style={{
                ...(isCollapsed ? { padding: 0 } : {}),
                ...style,
            }}
        >
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) => (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                style={{
                                    color: 'inherit',
                                }}
                            >
                                <a
                                    href={resolveUrl(item.href)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    {item.icon && (
                                        <Icon
                                            iconNode={item.icon}
                                            style={{
                                                width: 20,
                                                height: 20,
                                            }}
                                        />
                                    )}
                                    <span>{item.title}</span>
                                </a>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    ))}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
