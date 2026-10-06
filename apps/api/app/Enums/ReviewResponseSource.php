<?php

namespace App\Enums;

/**
 * Who wrote the reply under a review. Both are signed with the profile's name;
 * the public label differs („Одговор од лекарот“ for the doctor's own).
 */
enum ReviewResponseSource: string
{
    /** Entered by staff on the profile's behalf (right of reply by email). */
    case Staff = 'staff';

    /** Written by the doctor through their linked account („Мој профил“). */
    case Doctor = 'doctor';
}
