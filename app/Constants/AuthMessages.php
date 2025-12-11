<?php

namespace App\Constants;

class AuthMessages
{
    // Success messages
    const REGISTER_SUCCESS = 'User registered successfully.';
    const LOGIN_SUCCESS = 'Login successful.';
    const REFRESH_SUCCESS = 'Token refreshed successfully.';
    const LOGOUT_SUCCESS = 'Logout successful.';

    // Error messages
    const USER_ALREADY_EXISTS = 'User with this email already exists.';
    const INVALID_CREDENTIALS = 'Invalid email or password.';
    const USER_NOT_FOUND = 'User not found.';
    const TOKEN_INVALID = 'Invalid or expired token.';
    const UNAUTHORIZED = 'Unauthorized access.';
    // Validation error messages
    const NAME_REQUIRED = 'Name is required.';
    const EMAIL_REQUIRED = 'Email is required.';
    const EMAIL_INVALID = 'Email must be a valid email address.';
    const EMAIL_UNIQUE = 'Email is already taken.';
    const PASSWORD_REQUIRED = 'Password is required.';
    const PASSWORD_MIN = 'Password must be at least 8 characters.';
    const PASSWORD_CONFIRMED = 'Password confirmation does not match.';
}
