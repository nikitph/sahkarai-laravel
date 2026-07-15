<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\Project;
use App\Support\Audit\Audit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Project::class);

        return Inertia::render('projects/index', ['projects' => Project::query()->latest()->paginate(12)]);
    }

    public function store(StoreProjectRequest $request, Audit $audit): RedirectResponse
    {
        $project = Project::create([...$request->validated(), 'status' => 'active']);
        $audit->record('project.created', $project);

        return back()->with('success', 'Project created.');
    }

    public function destroy(Project $project, Audit $audit): RedirectResponse
    {
        $this->authorize('delete', $project);
        $audit->record('project.deleted', $project, ['name' => $project->name]);
        $project->delete();

        return back()->with('success', 'Project deleted.');
    }
}
