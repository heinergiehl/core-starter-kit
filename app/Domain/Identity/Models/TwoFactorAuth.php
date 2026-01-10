<?php

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-Factor Authentication model.
 *
 * Stores encrypted TOTP secrets and backup codes per user.
 */
class TwoFactorAuth extends Model
{
    protected $table = 'two_factor_secrets';

    protected $fillable = [
        'user_id',
        'secret',
        'backup_codes',
        'enabled_at',
        'confirmed_at',
    ];

    protected $casts = [
        'backup_codes' => 'encrypted:array',
        'enabled_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    protected $hidden = [
        'secret',
        'backup_codes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get decrypted secret for QR code generation.
     */
    public function getDecryptedSecret(): string
    {
        return Crypt::decryptString($this->secret);
    }

    /**
     * Generate a new TOTP secret.
     */
    public static function generateSecret(): string
    {
        return (new Google2FA())->generateSecretKey(32);
    }

    /**
     * Generate backup codes.
     */
    public static function generateBackupCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    /**
     * Verify a TOTP code.
     */
    public function verify(string $code): bool
    {
        $google2fa = new Google2FA();
        
        return $google2fa->verifyKey($this->getDecryptedSecret(), $code);
    }

    /**
     * Verify and consume a backup code.
     */
    public function verifyBackupCode(string $code): bool
    {
        $codes = $this->backup_codes ?? [];
        $code = strtoupper(trim($code));
        
        $index = array_search($code, $codes, true);
        
        if ($index === false) {
            return false;
        }

        // Remove used code
        unset($codes[$index]);
        $this->update(['backup_codes' => array_values($codes)]);

        return true;
    }

    /**
     * Check if 2FA is fully enabled and confirmed.
     */
    public function isEnabled(): bool
    {
        return $this->enabled_at !== null && $this->confirmed_at !== null;
    }

    /**
     * Get QR code URI for authenticator apps.
     */
    public function getQrCodeUri(string $email, string $appName): string
    {
        $google2fa = new Google2FA();
        
        return $google2fa->getQRCodeUrl(
            $appName,
            $email,
            $this->getDecryptedSecret()
        );
    }
}
