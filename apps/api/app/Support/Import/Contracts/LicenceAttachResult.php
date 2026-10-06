<?php

namespace App\Support\Import\Contracts;

enum LicenceAttachResult: string
{
    /** Stored: the doctor had no licence, or a different expiry or specialty. */
    case Attached = 'attached';

    /** The doctor already holds exactly this licence. */
    case Unchanged = 'unchanged';

    /** Staff locked the doctor's licence fields; nothing was written. */
    case Locked = 'locked';

    /** The number belongs to another doctor; queued for review instead. */
    case Conflict = 'conflict';

    /** No (non-deleted) doctor has that id. */
    case DoctorNotFound = 'doctor_not_found';
}
