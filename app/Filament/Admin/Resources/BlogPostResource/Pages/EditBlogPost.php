<?php

namespace App\Filament\Admin\Resources\BlogPostResource\Pages;

use App\Enums\PostStatus;
use App\Filament\Admin\Resources\BlogPostResource;
use Filament\Resources\Pages\EditRecord;

class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Set published_at to now if publishing without a date
        $status = $data['status'] ?? null;

        if (is_string($status)) {
            $status = PostStatus::tryFrom($status);
        }

        if ($status === PostStatus::Published && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
