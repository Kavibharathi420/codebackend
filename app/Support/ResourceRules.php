<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class ResourceRules
{
    public static function for(string $resource, ?int $id = null): array
    {
        $required = $id === null ? 'required' : 'sometimes|required';
        $text = 'nullable|string|max:10000';
        $image = ['nullable', 'string', 'max:255', 'regex:~^(?:/[^/]|https?://)~i'];
        $rules = match ($resource) {
            'admins' => ['name'=>"$required|string|max:120", 'email'=>[...explode('|', $required), 'email', 'max:120', Rule::unique('admins')->ignore($id)], 'password'=>($id === null ? 'required' : 'sometimes').'|string|min:12|max:128', 'role'=>"$required|in:admin,staff"],
            'categories' => ['name'=>"$required|string|max:120", 'description'=>$text, 'icon'=>'nullable|string|max:60'],
            'courses' => ['title'=>"$required|string|max:160", 'category_id'=>"$required|integer|exists:course_categories,id", 'summary'=>'nullable|string|max:300', 'description'=>$text, 'duration'=>'nullable|string|max:60', 'level'=>'nullable|string|max:60', 'delivery_mode'=>'nullable|string|max:60', 'schedule'=>'nullable|string|max:160', 'next_batch_date'=>'nullable|date', 'batch_time'=>'nullable|string|max:120', 'available_seats'=>'nullable|integer|min:0|max:100000', 'fee_note'=>'nullable|string|max:200', 'trainer_info'=>'nullable|string|max:5000', 'tier'=>'sometimes|required|string|max:40', 'image'=>$image, 'is_active'=>'boolean'],
            'branches' => ['name'=>"$required|string|max:120", 'city'=>"$required|string|max:80", 'address'=>$text, 'phone'=>'nullable|string|max:120', 'email'=>'nullable|email|max:120', 'hours'=>'nullable|string|max:200', 'map_embed'=>['nullable','url:http,https','max:2000'], 'is_primary'=>'boolean'],
            'testimonials' => ['name'=>"$required|string|max:120", 'role'=>'nullable|string|max:160', 'content'=>"$required|string|min:10|max:5000", 'rating'=>'integer|between:1,5', 'image'=>$image, 'is_active'=>'boolean', 'moderation_status'=>'sometimes|in:pending,approved,rejected'],
            'features' => ['title'=>"$required|string|max:160", 'description'=>$text, 'icon'=>'nullable|string|max:60'],
            'blog' => ['title'=>"$required|string|max:200", 'excerpt'=>'nullable|string|max:400', 'content'=>'nullable|string|max:100000', 'image'=>$image, 'author'=>'sometimes|required|string|max:120', 'tag'=>'nullable|string|max:80', 'is_published'=>'boolean'],
            'gallery' => ['title'=>'nullable|string|max:160', 'description'=>'nullable|string|max:2000', 'student_name'=>'nullable|string|max:120', 'project_course'=>'nullable|string|max:160', 'image'=>array_merge([$required], array_slice($image, 1)), 'category'=>'nullable|string|max:80', 'technologies'=>'nullable|string|max:300', 'external_url'=>'nullable|url:http,https|max:2000'],
            'awards' => ['title'=>"$required|string|max:200", 'issuer'=>'nullable|string|max:160', 'year'=>'nullable|string|max:10', 'description'=>$text, 'image'=>$image],
            'enquiries' => ['name'=>"$required|string|max:120", 'phone'=>"$required|string|max:40", 'preferred_contact'=>'nullable|in:phone,whatsapp,email', 'email'=>'nullable|email|max:120', 'course_id'=>'nullable|integer|exists:courses,id', 'branch_id'=>'nullable|integer|exists:branches,id', 'message'=>$text, 'consent'=>'accepted', 'college'=>'nullable|string|max:150', 'address'=>$text, 'degree'=>'nullable|string|max:120', 'type'=>'in:contact,appointment,govt_intern,career_guidance', 'status'=>'in:new,contacted,qualified,converted,lost', 'assigned_to'=>'nullable|integer|exists:admins,id', 'follow_up_at'=>'nullable|date'],
        };
        if (!in_array($resource, ['admins', 'enquiries', 'blog'], true)) $rules['order'] = 'integer|between:0,100000';
        if (in_array($resource, ['categories', 'courses', 'blog'], true)) {
            $table = ['categories'=>'course_categories','courses'=>'courses','blog'=>'blog_posts'][$resource];
            $max = ['categories'=>140,'courses'=>180,'blog'=>220][$resource];
            $rules['slug'] = ['sometimes','required','string',"max:$max",'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',Rule::unique($table)->ignore($id)];
        }
        if ($resource === 'courses') {
            foreach (['syllabus','roadmap','designations','prerequisites','projects','quiz'] as $field) $rules[$field] = 'nullable|array|max:100';
            foreach (['syllabus','roadmap','designations','prerequisites','projects'] as $field) $rules["$field.*"] = 'string|max:3000';
            $rules['quiz.*'] = 'array:question,options,answer';
            $rules['quiz.*.question'] = 'required|string|max:2000';
            $rules['quiz.*.options'] = 'required|array|min:2|max:10';
            $rules['quiz.*.options.*'] = 'required|string|max:1000';
            $rules['quiz.*.answer'] = 'required|integer|min:0|max:9';
        }
        return $rules;
    }
}
