<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StudentContentSeeder extends Seeder
{
    public function run(): void
    {
        $content = json_decode(file_get_contents(__DIR__.'/data/student-content.json'), true, 512, JSON_THROW_ON_ERROR);

        // Insert missing samples only: repeated seeding preserves editorial changes.
        DB::transaction(function () use ($content) {
            foreach ($content['blogs'] as $index => $post) {
                if (!DB::table('blog_posts')->where('slug', $post['slug'])->exists()) {
                    DB::table('blog_posts')->insert($post + ['author' => 'Code Bridge Learning Team', 'is_published' => true, 'created_at' => now()->subDays($index + 1)]);
                }
            }
            foreach ($content['work'] as $index => $work) {
                if (!DB::table('gallery_images')->where('image', $work['image'])->exists()) {
                    DB::table('gallery_images')->insert($work + ['order' => $index, 'created_at' => now()]);
                }
            }
        });
    }
}
