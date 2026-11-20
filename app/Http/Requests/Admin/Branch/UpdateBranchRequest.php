<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Branch;

use App\Models\Branch;

class UpdateBranchRequest extends BranchFormRequest
{
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch && $this->user()->can('update', $branch);
    }

    /**
     * The branch code is not validated or accepted here: it can never change.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...$this->sharedRules(),
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }
}
