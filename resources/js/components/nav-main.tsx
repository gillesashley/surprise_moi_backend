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
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    const [openItems, setOpenItems] = useState<string[]>([]);

    const toggleItem = (title: string) => {
        setOpenItems((prev) =>
            prev.includes(title)
                ? prev.filter((item) => item !== title)
                : [...prev, title],
        );
    };

    // Check if any child item is active
    const hasActiveChild = (item: NavItem): boolean => {
        if (!item.items) {
            return false;
        }
        return item.items.some((child) => {
            const resolved = resolveUrl(child.href);
            return resolved === '/dashboard'
                ? page.url === resolved
                : page.url.startsWith(resolved);
        });
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
                                                const resolved = resolveUrl(
                                                    subItem.href,
                                                );
                                                const active =
                                                    resolved === '/dashboard'
                                                        ? page.url === resolved
                                                        : page.url.startsWith(
                                                              resolved,
                                                          );

                                                return (
                                                    <SidebarMenuSubItem
                                                        key={subItem.title}
                                                    >
                                                        <SidebarMenuSubButton
                                                            asChild
                                                            isActive={active}
                                                        >
                                                            <Link
                                                                href={
                                                                    subItem.href
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
                    const resolved = resolveUrl(item.href);
                    const active =
                        resolved === '/dashboard'
                            ? page.url === resolved
                            : page.url.startsWith(resolved);

                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton
                                asChild
                                isActive={active}
                                tooltip={{ children: item.title }}
                            >
                                <Link href={item.href} prefetch>
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
