<?php

namespace AhgCore\Models;

use Illuminate\Contracts\Auth\Authenticatable;

class QubitUser extends QubitActor implements Authenticatable
{
    protected $table = 'user';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'username',
        'email',
        'password_hash',
        'salt',
        'active',
    ];

    protected $hidden = [
        'password_hash',
        'salt',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Get groups this user belongs to.
     */
    public function groups()
    {
        return $this->belongsToMany(AclGroup::class, 'acl_user_group', 'user_id', 'group_id');
    }

    /**
     * Get clipboard saves for this user.
     */
    public function clipboardSaves()
    {
        return $this->hasMany(ClipboardSave::class, 'user_id');
    }

    /**
     * Get jobs created by this user.
     */
    public function jobs()
    {
        return $this->hasMany(QubitJob::class, 'user_id');
    }

    // ========================================
    // Authenticatable interface
    // ========================================

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier()
    {
        return $this->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getAuthPassword(): ?string
    {
        return $this->password_hash;
    }

    public function getRememberToken(): ?string
    {
        return null; // Heratio does not use remember tokens
    }

    public function setRememberToken($value): void
    {
        // Heratio does not use remember tokens
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->groups()->where('acl_group.id', 100)->exists();
    }

    /**
     * Check if user is an editor.
     */
    public function isEditor(): bool
    {
        return $this->groups()->where('acl_group.id', 101)->exists();
    }

    /**
     * Get the display name for this user.
     */
    public function getDisplayName(string $culture = 'en'): string
    {
        $name = $this->getTranslated('authorized_form_of_name', $culture);

        return $name ?: $this->username ?: $this->email ?: 'Unknown';
    }
}
