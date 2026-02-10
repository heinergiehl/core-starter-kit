<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $hasIsAdmin = array_key_exists('is_admin', $data);
        $hasEmailVerifiedAt = array_key_exists('email_verified_at', $data);

        $isAdmin = $hasIsAdmin ? (bool) $data['is_admin'] : $record->is_admin;
        $emailVerifiedAt = $hasEmailVerifiedAt ? $data['email_verified_at'] : $record->email_verified_at;

        if ($record instanceof User && PermissionGuardrails::wouldDemoteLastAdmin($record, $isAdmin)) {
            throw ValidationException::withMessages([
                'is_admin' => PermissionGuardrails::lastAdminDemotionMessage(),
            ]);
        }

        unset($data['is_admin'], $data['email_verified_at']);

        $record->fill($data);
        $record->forceFill([
            'is_admin' => $isAdmin,
            'email_verified_at' => $emailVerifiedAt,
        ]);
        $record->save();

        return $record;
    }
}
