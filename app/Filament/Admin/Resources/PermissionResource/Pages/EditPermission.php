<?php

namespace App\Filament\Admin\Resources\PermissionResource\Pages;

use App\Filament\Admin\Resources\PermissionResource;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class EditPermission extends EditRecord
{
    protected static string $resource = PermissionResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record instanceof Permission && array_key_exists('name', $data) && PermissionGuardrails::isProtectedPermission($record)) {
            if ((string) $data['name'] !== (string) $record->name) {
                throw ValidationException::withMessages([
                    'name' => PermissionGuardrails::protectedPermissionRenameMessage(),
                ]);
            }
        }

        $record->update($data);

        return $record;
    }
}
