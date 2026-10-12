<?php

namespace App\Policies;

use App\Models\ParentAccount;
use App\Models\Student;

class StudentPolicy
{
    public function access(ParentAccount $parent, Student $student): bool
    {
        return $student->parent_id === $parent->id;
    }
}
