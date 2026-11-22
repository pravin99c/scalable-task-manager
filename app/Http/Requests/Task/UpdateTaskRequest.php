<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user() !== null
            && $task instanceof Task
            && $this->user()->can('update', $task);
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => [
                'nullable', 'integer', Rule::exists('users', 'id'),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }

                    $project = $this->route('task')->project;
                    if (! $project->canAssignTo((int) $value)) {
                        $fail('Assignee must be the project owner or an editor.');
                    }
                },
            ],
            'status' => ['sometimes', Rule::in(['TODO', 'IN_PROGRESS', 'DONE'])],
            'priority' => ['sometimes', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
