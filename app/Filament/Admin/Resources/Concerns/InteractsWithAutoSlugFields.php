<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

trait InteractsWithAutoSlugFields
{
    protected static function makeSlugSyncHiddenField(string $statePath = 'slug_sync_enabled'): Hidden
    {
        return Hidden::make($statePath)
            ->default(true)
            ->dehydrated(false);
    }

    protected static function configureSlugSourceField(
        TextInput $field,
        string $slugField = 'slug',
        string $syncField = 'slug_sync_enabled'
    ): TextInput {
        return $field
            ->afterStateHydrated(function (?string $state, Get $get, Set $set) use ($slugField, $syncField): void {
                $slugState = BlogEditorSupport::initializeSlugEditorState($get($slugField), $state);

                if ($slugState['slug'] !== (string) $get($slugField)) {
                    $set($slugField, $slugState['slug']);
                }

                $set($syncField, $slugState['sync']);
            })
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($slugField, $syncField): void {
                if (! (bool) $get($syncField)) {
                    return;
                }

                $set($slugField, BlogEditorSupport::syncSlugFromSource($state));
            });
    }

    protected static function configureSlugField(
        TextInput $field,
        string $sourceField = 'name',
        string $sourceLabel = 'name',
        string $syncField = 'slug_sync_enabled'
    ): TextInput {
        return $field
            ->live(onBlur: true)
            ->afterStateHydrated(function (?string $state, Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::initializeSlugEditorState($state, $get($sourceField));

                if ($slugState['slug'] !== (string) $state) {
                    $set('slug', $slugState['slug']);
                }

                $set($syncField, $slugState['sync']);
            })
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::updateSlugEditorState($state, $get($sourceField));

                if ($slugState['slug'] !== (string) $state) {
                    $set('slug', $slugState['slug']);
                }

                $set($syncField, $slugState['sync']);
            })
            ->dehydrateStateUsing(fn (?string $state): string => BlogEditorSupport::normalizeSlugInput($state))
            ->hint(fn (TextInput $component, Get $get) => BlogEditorSupport::renderSlugSyncState(
                sourceStatePath: $component->resolveRelativeStatePath($sourceField),
                syncEnabled: (bool) $get($syncField),
            ))
            ->suffixAction(static::makeResetSlugAction($sourceField, $syncField), isInline: true)
            ->helperText(fn (Get $get): string => BlogEditorSupport::describeSlugBehavior(
                sourceValue: $get($sourceField),
                syncEnabled: (bool) $get($syncField),
                sourceLabel: $sourceLabel,
            ));
    }

    protected static function makeResetSlugAction(
        string $sourceField = 'name',
        string $syncField = 'slug_sync_enabled'
    ): Action {
        return Action::make(Str::camel("reset {$sourceField} {$syncField} slug"))
            ->label('Reset')
            ->link()
            ->icon('heroicon-o-arrow-path')
            ->tooltip('Use the generated slug again')
            ->visible(fn (Get $get): bool => filled(BlogEditorSupport::generateSlug($get($sourceField))) && (! (bool) $get($syncField)))
            ->action(function (Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::initializeSlugEditorState(null, $get($sourceField));

                $set('slug', $slugState['slug']);
                $set($syncField, $slugState['sync']);
            });
    }
}
