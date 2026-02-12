<?php

namespace Database\Seeders;

use App\Domain\Content\Data\BlogPostData;
use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\PostStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPostSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        if (! $admin) {
            $this->command->info('No users found. Please run DatabaseSeeder first.');

            return;
        }

        // Ensure we have some categories
        $categories = [
            'Engineering' => 'Deep dives into our stack and struggles.',
            'Culture' => 'How we stay sane while shipping.',
            'Product' => 'Updates, feature drops, and shiny things.',
            'Rants' => 'Opinions that might get us cancelled (by the compiler).',
        ];

        $categoryModels = collect();
        foreach ($categories as $name => $desc) {
            $categoryModels->push(BlogCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            ));
        }

        // Ensure we have some tags
        $tags = ['DevOps', 'Frontend', 'Backend', 'CSS', 'Databases', 'Coffee', 'Design', 'Life hacks'];
        $tagModels = collect();
        foreach ($tags as $tagName) {
            $tagModels->push(BlogTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            ));
        }

        $posts = [
            new BlogPostData(
                title: 'Why deploying on Fridays is a spiritual experience',
                excerpt: 'It tests your faith, your patience, and your ability to apologize succinctly.',
                image_path: 'blog-images/friday_deploy.png',
                status: PostStatus::Published,
            ),
            new BlogPostData(
                title: '10 reasons why your linter hates you',
                excerpt: 'It’s not personal, it’s just strict typing. Okay, maybe it is personal.',
                image_path: 'blog-images/angry_linter.png',
                status: PostStatus::Published,
            ),
            new BlogPostData(
                title: 'How I centered a div and lived to tell the tale',
                excerpt: 'A harrowing journey through Flexbox, Grid, and `margin: 0 auto`.',
                image_path: 'blog-images/centering_div.png',
                status: PostStatus::Published,
            ),
            new BlogPostData(
                title: 'The brave little server that could (handle 5 req/sec)',
                excerpt: 'Scaling is a problem for future-us. Present-us is just happy it boots.',
                image_path: 'blog-images/brave_server.png',
                status: PostStatus::Published,
            ),
            new BlogPostData(
                title: "My relationship status? It's complicated (with git merge)",
                excerpt: 'HEAD detached, conflicts everywhere, and I think I lost a file.',
                image_path: 'blog-images/git_merge.png',
                status: PostStatus::Published,
            ),
            new BlogPostData(
                title: 'Coffee: The only reliable dependency injection',
                excerpt: 'Without it, the runtime environment (me) crashes immediately.',
            ),
            new BlogPostData(
                title: "Kubernetes: I thought you said 'cool bennies'?",
                excerpt: 'I just wanted to run a container, now I have a YAML addiction.',
            ),
            new BlogPostData(
                title: 'Dark mode is not a preference, it is a lifestyle',
                excerpt: 'Light mode attracts bugs. Everyone knows this.',
            ),
            new BlogPostData(
                title: 'Legacy code: Archaeology for developers',
                excerpt: 'Digging through comments from 2015 to understand why `var x = true` exists.',
            ),
            new BlogPostData(
                title: 'If it works, do not touch it. Please.',
                excerpt: 'The load bearing `print` statement stays in the codebase.',
            ),
        ];

        // Create directory for blog images if it doesn't exist
        Storage::disk('public')->makeDirectory('blog-images');

        // Get existing files
        $existingFiles = Storage::disk('public')->files('blog-images');

        foreach ($posts as $data) {
            $imagePath = $data->image_path;

            // If no specific image mapped, and no file exists at that path (e.g. typos), pick random
            if ($imagePath && ! Storage::disk('public')->exists($imagePath)) {
                $imagePath = null;
            }

            if (! $imagePath) {
                if (count($existingFiles) > 0) {
                    // Pick a random existing image
                    $randomFile = $existingFiles[array_rand($existingFiles)];
                    $imagePath = $randomFile;
                }
            }

            // Generate body with LOCAL embedded image
            // If no image, we just don't embed one in body
            $body = $this->generateBody($data->title, $imagePath);

            $post = BlogPost::updateOrCreate(
                ['slug' => Str::slug($data->title)],
                [
                    'title' => $data->title,
                    'excerpt' => $data->excerpt,
                    'body_markdown' => $body,
                    'featured_image' => $imagePath,
                    'status' => $data->status,
                    'published_at' => now()->subDays(rand(1, 365)),
                    'author_id' => $admin->id,
                    'category_id' => $categoryModels->random()->id,
                    'reading_time' => rand(3, 10),
                    'body_html' => Str::markdown($body),
                ]
            );

            $post->tags()->sync($tagModels->random(rand(1, 3))->pluck('id'));
        }
    }

    private function generateBody(string $title, ?string $imagePath): string
    {
        $imageMarkdown = '';
        if ($imagePath) {
            $imageUrl = Storage::url($imagePath);
            $imageMarkdown = "\n![Image]({$imageUrl})\n";
        }

        return <<<MD
# {$title}
{$imageMarkdown}
Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Given the title, you can imagine this post is about **serious technical challenges** or just _ranting_.

## The Problem

Usually, it starts with a simple requirement. Then, complexity ensues.

1. Step one: Denial
2. Step two: Stack Overflow
3. Step three: Acceptance

> "Programming is thinking, not typing." — Casey Patton

## The Solution

Surprisingly, the solution was simpler than expected. Or maybe it was a hack. Who knows?

```php
public function solveProblem()
{
    return true; // it works!
}
```

## Conclusion

Thanks for reading! Subscribe for more insights into the chaotic life of a developer.
MD;
    }
}
