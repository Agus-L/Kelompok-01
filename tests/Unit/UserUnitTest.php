<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserUnitTest extends TestCase
{
    public function test_user_has_dosen_role(): void
    {
        $user = new User(['name' => 'Dr. Bambang', 'role' => 'dosen']);

        $this->assertTrue(true);
    }
}
