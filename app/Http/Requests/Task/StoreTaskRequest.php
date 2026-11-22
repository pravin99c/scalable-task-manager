<?php

namespace App\Http\Requests\Task;

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
        $userId = $this->user()->getKey();

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where('created_by', $userId),
            ],
            // Until project membership exists, prohibit assigning tasks to
            // arbitrary accounts and exposing task details to third parties.
            'assigned_to' => ['nullable', 'integer', Rule::in([$userId])],
            'status' => ['nullable', Rule::in(['TODO', 'IN_PROGRESS', 'DONE'])],
            'priority' => ['nullable', Rule::in(['LOW', 'MEDIUM', 'HIGH'])],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
