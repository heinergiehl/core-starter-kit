<?php

namespace App\Filament\Admin\Resources\BlogPostResource\Pages;

use App\Domain\Content\Models\BlogPost;
use App\Enums\PostStatus;
use App\Filament\Admin\Resources\BlogPostResource;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\QueryException;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTranslation')
                ->label('Add translation')
                ->icon('heroicon-o-language')
                ->visible(fn (): bool => $this->record->missingTranslationLocales() !== [])
                ->modalHeading('Create translation draft')
                ->modalDescription('Copy the current post into another locale. The new translation starts as a draft so you can adjust the content and SEO safely. If that locale already uses the same slug, the draft gets the next available slug automatically.')
                ->modalSubmitActionLabel('Create translation')
                ->form([
                    Select::make('locale')
                        ->label('Locale')
                        ->options(fn (): array => collect(BlogPostResource::localeOptions())
                            ->only($this->record->missingTranslationLocales())
                            ->all())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data) {
                    $targetLocale = (string) ($data['locale'] ?? '');

                    abort_unless(in_array($targetLocale, $this->record->missingTranslationLocales(), true), 422);

                    $sourcePost = $this->record->loadMissing('tags');
                    $existingTranslation = BlogPost::query()
                        ->where('translation_group_uuid', $sourcePost->translation_group_uuid)
                        ->where('locale', $targetLocale)
                        ->first();

                    if ($existingTranslation) {
                        Notification::make()
                            ->title('Translation already exists.')
                            ->body('Opening the existing locale variant instead of creating a duplicate.')
                            ->warning()
                            ->send();

                        return redirect()->to(BlogPostResource::getUrl('edit', ['record' => $existingTranslation]));
                    }

                    $translation = $sourcePost->replicate([
                        'published_at',
                        'created_at',
                        'updated_at',
                    ]);

                    $translation->translation_group_uuid = $sourcePost->translation_group_uuid;
                    $translation->locale = $targetLocale;
                    $translation->slug = BlogEditorSupport::generateUniqueBlogPostSlug(
                        locale: $targetLocale,
                        preferredSlug: $sourcePost->slug,
                        fallbackTitle: $sourcePost->title,
                    );
                    $translation->status = PostStatus::Draft;
                    $translation->published_at = null;

                    $slugWasAdjusted = $translation->slug !== $sourcePost->slug;

                    for ($attempt = 0; $attempt < 3; $attempt++) {
                        try {
                            $translation->save();

                            break;
                        } catch (QueryException $exception) {
                            $existingTranslation = BlogPost::query()
                                ->where('translation_group_uuid', $sourcePost->translation_group_uuid)
                                ->where('locale', $targetLocale)
                                ->first();

                            if ($existingTranslation) {
                                Notification::make()
                                    ->title('Translation already exists.')
                                    ->body('Opening the existing locale variant instead of creating a duplicate.')
                                    ->warning()
                                    ->send();

                                return redirect()->to(BlogPostResource::getUrl('edit', ['record' => $existingTranslation]));
                            }

                            if ($attempt === 2) {
                                throw $exception;
                            }

                            $translation->slug = BlogEditorSupport::generateUniqueBlogPostSlug(
                                locale: $targetLocale,
                                preferredSlug: $sourcePost->slug,
                                fallbackTitle: $sourcePost->title,
                            );
                            $slugWasAdjusted = true;
                        }
                    }

                    $translation->tags()->sync($sourcePost->tags->modelKeys());

                    $notification = Notification::make()
                        ->title('Translation draft created.')
                        ->success();

                    if ($slugWasAdjusted) {
                        $notification->body("Slug adjusted to `{$translation->slug}` because {$targetLocale} already had that URL.");
                    }

                    $notification->send();

                    return redirect()->to(BlogPostResource::getUrl('edit', ['record' => $translation]));
                }),
            DeleteAction::make(),
        ];
    }
}
