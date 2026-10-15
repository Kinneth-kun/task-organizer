<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\Toasts;
use App\Models\Department;
use App\Support\Audit;
use App\Support\Palette;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Departments')]
class Departments extends Component
{
    use Toasts;

    public bool $editing = false;

    public ?int $editId = null;

    public string $name = '';

    public string $code = '';

    public string $color = 'slate';

    public string $description = '';

    public function create(): void
    {
        $this->authorize('create', Department::class);
        $this->reset(['editId', 'name', 'code', 'description']);
        $this->color = 'indigo';
        $this->resetValidation();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->authorize('update', $department);
        $this->editId = $department->id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->color = $department->color;
        $this->description = (string) $department->description;
        $this->resetValidation();
        $this->editing = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->editId)],
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('departments', 'code')->ignore($this->editId)],
            'color' => ['required', Rule::in(array_unique([...Palette::CHOICES, 'slate', 'zinc', 'red', 'blue']))],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['description'] = $data['description'] ?: null;

        if ($this->editId) {
            $department = Department::findOrFail($this->editId);
            $this->authorize('update', $department);
            $department->update($data);
            Audit::log('department.updated', "Edited department {$department->name}", $department);
        } else {
            $this->authorize('create', Department::class);
            $department = Department::create($data);
            Audit::log('department.created', "Created department {$department->name}", $department);
        }

        $this->editing = false;
        $this->toast('Department saved.');
    }

    public function toggleActive(int $id): void
    {
        $department = Department::findOrFail($id);
        $this->authorize('update', $department);
        $department->update(['is_active' => ! $department->is_active]);
        Audit::log('department.'.($department->is_active ? 'activated' : 'deactivated'), ($department->is_active ? 'Reactivated' : 'Deactivated')." department {$department->name}", $department);
        $this->toast($department->is_active ? 'Department reactivated.' : 'Department deactivated. Existing people and work keep it.', 'info');
    }

    public function delete(int $id): void
    {
        $department = Department::withCount(['users', 'projects', 'tasks'])->findOrFail($id);
        $this->authorize('delete', $department);

        if ($department->users_count || $department->projects_count || $department->tasks_count) {
            $this->toast('This department still has people or work. Deactivate it instead.', 'error');

            return;
        }

        Audit::log('department.deleted', "Deleted department {$department->name}", $department);
        $department->delete();
        $this->toast('Department deleted.', 'info');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Department::class);

        return view('livewire.admin.departments', [
            'departments' => Department::query()
                ->withCount(['users', 'projects', 'tasks as open_tasks_count' => fn ($q) => $q->open()])
                ->orderBy('name')
                ->get(),
        ]);
    }
}
