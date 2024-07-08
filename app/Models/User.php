<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'roles',
        'permissions'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'roles' => 'array',
        'permissions' => 'array'
    ];
    public function isAdmin(): bool
    {
        return $this->username === 'admin';
    }

    public function roles(): array{
        return $this->roles;
    }
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles());
    }
    public function addRole(string $role): void
    {
        //Check if the role is already added:
        if (!$this->hasRole($role)){
            $roles = $this->roles();
            $roles[] = $role;
            $this->roles = $roles;
            $this->save();
        }
    }
    public function removeRole(string $role): void
    {
        if ($this->hasRole($role)){
            $roles = $this->roles();
            $roles = array_filter($roles, function($r) use ($role){
                return $r !== $role;
            });
            $this->roles = $roles;
            $this->save();
        }
    }

    public function hasPermissionTo(string $permission): bool
    {
        if ($this->hasPermission('all')){
            return true;
        }

        return $this->hasPermission($permission);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    public function addPermission(string $permission): void
    {
        if (!$this->hasPermission($permission)){
            $permissions = $this->permissions;
            $permissions[] = $permission;
            $this->permissions = $permissions;
            $this->save();
        }
    }

    public function removePermission(string $permission): void
    {
        if ($this->hasPermission($permission)){
            $permissions = $this->permissions;
            $permissions = array_filter($permissions, function($p) use ($permission){
                return $p !== $permission;
            });
            $this->permissions = $permissions;
            $this->save();
        }
    }
    public function permissions(): array{
        return $this->permissions;
    }



    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function isOfficial(): bool
    {
        return str_contains($this->email, '@maranatha');
    }
}
