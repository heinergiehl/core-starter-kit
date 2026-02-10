<?php

namespace App\Filament\Admin\Resources\RoleResource\Pages;

use App\Filament\Admin\Resources\RoleResource;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record instanceof Role && array_key_exists('name', $data) && PermissionGuardrails::isProtectedRole($record)) {
            if ((string) $data['name'] !== (string) $record->name) {
                throw ValidationException::withMessages([
                    'name' => PermissionGuardrails::protectedRoleRenameMessage(),
                ]);
            }
        }

        $record->update($data);

        return $record;
    }
}
