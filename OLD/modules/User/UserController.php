<?php
namespace Modules\User;

use Core\Controller;
use Core\Auth;
use Core\Database;

class UserController extends Controller
{
    // Auth handled by parent constructor

    public function settings()
    {
        // Placeholder for settings
        $this->view('User/settings', ['user' => Auth::user()]);
    }

    public function profile()
    {
        // Placeholder for profile
        $this->view('User/profile', ['user' => Auth::user()]);
    }
}
