<?php

namespace App\Policies;

use App\Models\PlantRecord;
use App\Models\User;

class PlantRecordPolicy
{
    public function view(User $u, PlantRecord $p): bool
    {
        return $u->id === $p->user_id;
    }

    public function update(User $u, PlantRecord $p): bool
    {
        return $this->view($u, $p);
    }

    public function delete(User $u, PlantRecord $p): bool
    {
        return $this->view($u, $p);
    }

    public function export(User $u, PlantRecord $p): bool
    {
        return $this->view($u, $p);
    }
}
