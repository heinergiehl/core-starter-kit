<?php

namespace App\Domain\Content\Data;

use App\Enums\PostStatus;
use Spatie\LaravelData\Data;

class BlogPostData extends Data
{
    public function __construct(
        public string $title,
        public string $excerpt,
        public ?string $image_path = null,
        public PostStatus $status = PostStatus::Published,
    ) {}
}
