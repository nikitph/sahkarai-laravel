import { Form, Head } from '@inertiajs/react';
import { Clock3, Mail, ShieldCheck, UserPlus, UsersRound } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Member = {
    id: number;
    name: string;
    email: string;
    pivot: { role: string };
};
type Invitation = {
    id: number;
    email: string;
    role: string;
    expires_at: string;
};

export default function Members({
    members,
    invitations,
    roles,
}: {
    members: Member[];
    invitations: Invitation[];
    roles: string[];
}) {
    return (
        <>
            <Head title="Members" />
            <div className="mx-auto w-full max-w-7xl p-4 md:p-8">
                <div className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        Workspace
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        People & access
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Invite teammates and keep authorization explicit.
                    </p>
                </div>
                <div className="grid gap-6 lg:grid-cols-[1.5fr_1fr]">
                    <Card className="rounded-2xl border-border/60 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UsersRound className="size-4" /> Members
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {members.map((member) => (
                                <div
                                    key={member.id}
                                    className="flex items-center gap-3 rounded-xl p-3 hover:bg-muted/50"
                                >
                                    <span className="grid size-10 place-items-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                        {member.name
                                            .split(' ')
                                            .map((n) => n[0])
                                            .slice(0, 2)
                                            .join('')}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-medium">
                                            {member.name}
                                        </p>
                                        <p className="truncate text-xs text-muted-foreground">
                                            {member.email}
                                        </p>
                                    </div>
                                    <Badge
                                        variant="secondary"
                                        className="capitalize"
                                    >
                                        <ShieldCheck className="mr-1 size-3" />
                                        {member.pivot.role}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card className="h-fit rounded-2xl border-border/60 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <UserPlus className="size-4" /> Invite a
                                teammate
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action="/members/invitations"
                                method="post"
                                className="space-y-4"
                                resetOnSuccess
                            >
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        placeholder="teammate@company.com"
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="role">Role</Label>
                                    <Select name="role" defaultValue={roles[0]}>
                                        <SelectTrigger id="role">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {roles.map((role) => (
                                                <SelectItem
                                                    key={role}
                                                    value={role}
                                                    className="capitalize"
                                                >
                                                    {role}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <Button className="w-full rounded-xl">
                                    <Mail className="mr-1 size-4" /> Send
                                    invitation
                                </Button>
                            </Form>
                        </CardContent>
                    </Card>
                </div>
                {invitations.length > 0 && (
                    <Card className="mt-6 rounded-2xl border-border/60 shadow-sm">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Clock3 className="size-4" /> Pending
                                invitations
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="divide-y">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    className="flex items-center gap-3 py-3"
                                >
                                    <Mail className="size-4 text-muted-foreground" />
                                    <span className="flex-1 text-sm">
                                        {invitation.email}
                                    </span>
                                    <Badge
                                        variant="outline"
                                        className="capitalize"
                                    >
                                        {invitation.role}
                                    </Badge>
                                    <span className="hidden text-xs text-muted-foreground sm:block">
                                        Expires{' '}
                                        {new Date(
                                            invitation.expires_at,
                                        ).toLocaleDateString()}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

Members.layout = { breadcrumbs: [{ title: 'Members', href: '/members' }] };
