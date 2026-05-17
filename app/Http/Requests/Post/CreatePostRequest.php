<?php

namespace App\Http\Requests\Post;

use Illuminate\Foundation\Http\FormRequest;

class CreatePostRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'                          => 'nullable|string|max:255',
            'base_content'                   => 'nullable|string',
            'timezone'                       => 'nullable|timezone',
            'tags'                           => 'nullable|array',
            'tags.*'                         => 'string|max:50',
            'media_ids'                      => 'nullable|array',
            'media_ids.*'                    => 'integer|exists:media_assets,id',
            'account_ids'                    => 'nullable|array',
            'account_ids.*'                  => 'integer|exists:social_accounts,id',
            'variants'                       => 'nullable|array',
            'variants.*.social_account_id'   => 'required_with:variants|integer|exists:social_accounts,id',
            'variants.*.content'             => 'nullable|string',
            'variants.*.hashtags'            => 'nullable|array',
            'variants.*.hashtags.*'          => 'string|max:100',
            'variants.*.first_comment'       => 'nullable|string|max:2200',
            'variants.*.link_url'            => 'nullable|url',
            'variants.*.visibility'          => 'nullable|in:public,private,friends',
            'variants.*.platform_options'    => 'nullable|array',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->validated();
            if (empty($data['base_content']) && empty($data['media_ids'])) {
                $validator->errors()->add('content', 'A post must have content or media.');
            }
        });
    }
}
