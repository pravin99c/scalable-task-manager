<?php

namespace App\Http\Requests\Task;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => [
                'required', 'integer', Rule::exists('projects', 'id'),
                function ($attribute, $value, $fail) {
                    $project = Project::find($value);
                    if (! $project || ! $this->user()->can('editTasks', $project)) {
                        $fail('You do not have permission to create tasks in this project.');
                    }
                },
            ],
            'assigned_to' => [
                'nullable', 'integer', Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    $project = Project::find($this->input('project_id'));
                    if (! $project || ! $this->user()->can('editTasks', $project)
                        || ! $project->canAssignTo((int) $value)) {
                        $fail('Assignee must be the project owner or an editor.');
                    }
                },
            ],
            'status' => ['nullable', Rule::in(['TODO', 'IN_PROGRESS', 'DONE'])],
            'priority' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
