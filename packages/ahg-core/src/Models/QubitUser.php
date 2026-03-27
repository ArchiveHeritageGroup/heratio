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
     * Cached admin flag (avoids repeated DB queries within a request).
     */
    protected ?bool $cachedIsAdmin = null;

    /**
     * Check if user is an administrator.
     */
    public function isAdministrator(): bool
    {
        if ($this->cachedIsAdmin === null) {
            $this->cachedIsAdmin = $this->groups()->where('acl_group.id', 100)->exists();
        }

        return $this->cachedIsAdmin;
    }

    /**
     * Accessor: $user->is_admin
     * Used throughout the codebase for admin checks (publication status filter, action icons, etc.).
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->isAdministrator();
    }

    /**
     * Check if user is an editor.
     */
    public function isEditor(): bool
    {
        return $this->groups()->where('acl_group.id', 101)->exists();
    }

    /**
     * Accessor: $user->is_editor
     * True if the user belongs to the Editor group (101).
     */
    public function getIsEditorAttribute(): bool
    {
        return $this->isEditor();
    }

    /**
     * Accessor: $user->can_edit
     * True if the user is an administrator OR an editor (i.e. can edit/create records).
     */
    public function getCanEditAttribute(): bool
    {
        return $this->isAdministrator() || $this->isEditor();
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
