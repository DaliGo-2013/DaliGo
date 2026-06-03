<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignRole extends Command
{
    protected $signature = 'app:assign-role {email} {role}';

    protected $description = 'Asigna (reemplaza) el rol de un usuario identificado por su correo';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No existe un usuario con el correo {$this->argument('email')}.");

            return self::FAILURE;
        }

        $role = $this->argument('role');

        if (! Role::where('name', $role)->exists()) {
            $this->error("El rol '{$role}' no existe. Disponibles: ".Role::pluck('name')->implode(', '));

            return self::FAILURE;
        }

        $user->syncRoles([$role]);

        $this->info("Rol '{$role}' asignado a {$user->email}.");

        return self::SUCCESS;
    }
}
