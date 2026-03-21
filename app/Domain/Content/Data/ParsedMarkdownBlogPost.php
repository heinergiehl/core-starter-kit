<?php

namespace App\Domain\Content\Data;

use App\Enums\PostStatus;
use Carbon\CarbonInterface;

readonly class ParsedMarkdownBlogPost
{
    /**
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public string $familyKey,
        public string $locale,
        public string $sourcePath,
        public string $sourceHash,
        public string $title,
        public string $slug,
        public ?string $excerpt,
        public string $bodyMarkdown,
        public ?string $authorEmail,
        public ?string $category,
        public array $tags,
        public PostStatus $status,
        public ?CarbonInterface $publishedAt,
        public ?string $metaTitle,
        public ?string $metaDescription,
        public ?string $featuredImage,
    ) {}
}
