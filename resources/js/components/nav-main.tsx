import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { type InertiaLinkProps, Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useCallback, useState } from 'react';

const STORAGE_KEY = 'sidebar-open-items';

function getStoredOpenItems(): string[] {
    try {
        const stored = sessionStorage.getItem(STORAGE_KEY);
        return stored ? JSON.parse(stored) : [];
    } catch {
        return [];
    }
}

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const [openItems, setOpenItems] = useState<string[]>(getStoredOpenItems);

    const toggleItem = useCallback((title: string) => {
        setOpenItems((prev) => {
            const next = prev.includes(title)
                ? prev.filter((item) => item !== title)
                : [...prev, title];
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(next));
            return next;
        });
    }, []);

    const isActive = (href?: InertiaLinkProps['href']): boolean => {
        if (!href) return false;
        const resolved = resolveUrl(href);
        return resolved === '/dashboard'
            ? page.url === resolved
            : page.url.startsWith(resolved);
    };

    // Check if any child item is active
    const hasActiveChild = (item: NavItem): boolean => {
        if (!item.items) {
            return false;
        }
        return item.items.some((child) => isActive(child.href));
    };

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    // If item has children, render as collapsible
                    if (item.items && item.items.length > 0) {
                        const isOpen =
                            openItems.includes(item.title) ||
                            hasActiveChild(item);

                        return (
                            <Collapsible
                                key={item.title}
                                asChild
                                open={isOpen}
                                onOpenChange={() => toggleItem(item.title)}
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton
                                            tooltip={{
                                                children: item.title,
                                            }}
                                        >
                                            {item.icon && <item.icon />}
                                            <span>{item.title}</span>
                                            <ChevronRight
                                                className={`ml-auto transition-transform duration-200 ${
                                                    isOpen
                                                        ? 'rotate-90'
                                                        : 'rotate-0'
                                                }`}
                                            />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items.map((subItem) => {
                                                return (
                                                    <SidebarMenuSubItem
                                                        key={subItem.href ? resolveUrl(subItem.href) : subItem.title}
                                                    >
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={isActive(subItem.href)}
                                                        >
                                                            <Link
                                                                href={
                                                                    subItem.href!
                                                                }
                                                                prefetch
                                                            >
                                                                {subItem.icon && (
                                                                    <subItem.icon />
                                                                )}
                                                                <span>
                                                                    {
                                                                        subItem.title
                                                                    }
                                                                </span>
                                                            </Link>
                                                        </SidebarMenuSubButton>
                                                    </SidebarMenuSubItem>
                                                );
                                            })}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        );
                    }

                    // Regular menu item without children
                    return (
                        <SidebarMenuItem key={item.href ? resolveUrl(item.href) : item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={isActive(item.href)}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href!} prefetch>
                                    {item.icon && <item.icon />}
                                    <span>{item.title}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
