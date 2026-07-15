import { Form, Head } from '@inertiajs/react';
import { FolderKanban, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Project = {
    id: number;
    name: string;
    description?: string;
    status: string;
    created_at: string;
};

export default function Projects({
    projects,
}: {
    projects: { data: Project[] };
}) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);

    return (
        <>
            <Head title="Projects" />
            <div className="mx-auto w-full max-w-7xl p-4 md:p-8">
                <div className="mb-7 flex items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            Reference module
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                            Projects
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            A complete tenant-safe vertical slice for agents to
                            copy.
                        </p>
                    </div>
                    <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                        <DialogTrigger asChild>
                            <Button className="rounded-xl">
                                <Plus className="mr-1 size-4" /> New project
                            </Button>
                        </DialogTrigger>
                        <CreateProject
                            onCreated={() => setIsCreateOpen(false)}
                        />
                    </Dialog>
                </div>
                {projects.data.length ? (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.data.map((project) => (
                            <Card
                                key={project.id}
                                className="group rounded-2xl border-border/60 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                            >
                                <CardContent className="p-5">
                                    <div className="mb-5 flex items-start justify-between">
                                        <span className="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
                                            <FolderKanban className="size-5" />
                                        </span>
                                        <Form
                                            action={`/projects/${project.id}`}
                                            method="delete"
                                        >
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="opacity-0 group-hover:opacity-100"
                                                aria-label={`Delete ${project.name}`}
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </Form>
                                    </div>
                                    <h2 className="font-semibold">
                                        {project.name}
                                    </h2>
                                    <p className="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-muted-foreground">
                                        {project.description ||
                                            'No description yet.'}
                                    </p>
                                    <p className="mt-5 text-xs text-muted-foreground">
                                        Created{' '}
                                        {new Date(
                                            project.created_at,
                                        ).toLocaleDateString()}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                ) : (
                    <div className="grid min-h-80 place-items-center rounded-3xl border border-dashed bg-muted/20 text-center">
                        <div>
                            <FolderKanban className="mx-auto size-8 text-muted-foreground" />
                            <h2 className="mt-4 font-medium">
                                No projects yet
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Create one to see the full pattern in action.
                            </p>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

function CreateProject({ onCreated }: { onCreated: () => void }) {
    return (
        <DialogContent className="rounded-2xl">
            <DialogHeader>
                <DialogTitle>Create a project</DialogTitle>
                <DialogDescription>
                    This exercises validation, policy, action, audit and tenant
                    scope.
                </DialogDescription>
            </DialogHeader>
            <Form
                action="/projects"
                method="post"
                className="space-y-4"
                resetOnSuccess
                onSuccess={onCreated}
            >
                <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        name="name"
                        required
                        placeholder="Customer insights"
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="description">Description</Label>
                    <Textarea
                        id="description"
                        name="description"
                        placeholder="What is this project for?"
                    />
                </div>
                <Button className="w-full">Create project</Button>
            </Form>
        </DialogContent>
    );
}

Projects.layout = { breadcrumbs: [{ title: 'Projects', href: '/projects' }] };
