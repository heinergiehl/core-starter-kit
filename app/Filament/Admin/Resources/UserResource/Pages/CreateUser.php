<?php

namespace App\Filament\Admin\Resources\UserResource\Pages;

use App\Filament\Admin\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $isAdmin = array_key_exists('is_admin', $data) ? (bool) $data['is_admin'] : false;
        $emailVerifiedAt = array_key_exists('email_verified_at', $data) ? $data['email_verified_at'] : null;

        unset($data['is_admin'], $data['email_verified_at']);

        /** @var Model $record */
        $record = new ($this->getModel())($data);
        $record->save();

        $record->forceFill([
            'is_admin' => $isAdmin,
            'email_verified_at' => $emailVerifiedAt,
        ])->save();

        return $record;
    }
}
