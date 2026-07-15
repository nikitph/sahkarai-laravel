import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

export function OrganizationSwitcher() {
    const { organization } = usePage().props;

    if (!organization?.current) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton className="h-12 rounded-xl border bg-background/70 shadow-sm">
                                <span className="grid size-7 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <Building2 className="size-4" />
                                </span>
                                <span className="min-w-0 flex-1 truncate text-left font-medium">
                                    {organization.current.name}
                                </span>
                                <ChevronsUpDown className="size-4 text-muted-foreground" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-64" align="start">
                            <DropdownMenuLabel>Workspaces</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {organization.all.map((item) => (
                                <DropdownMenuItem
                                    key={item.id}
                                    onClick={() =>
                                        router.post(
                                            `/organizations/${item.id}/switch`,
                                        )
                                    }
                                >
                                    <Building2 className="mr-2 size-4" />
                                    <span className="flex-1 truncate">
                                        {item.name}
                                    </span>
                                    {item.id === organization.current?.id && (
                                        <Check className="size-4" />
                                    )}
                                </DropdownMenuItem>
                            ))}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    );
}
