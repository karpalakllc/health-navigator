<?php

namespace App\Actions\DoctorAccount;

use RuntimeException;

/**
 * A doctor-account action that cannot go ahead (the account already manages
 * another profile, the profile already has one, the request was decided).
 * The message is for staff (admin panel, English).
 */
final class DoctorAccountException extends RuntimeException {}
