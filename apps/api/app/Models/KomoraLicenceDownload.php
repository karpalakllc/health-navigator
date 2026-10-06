<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One fetch of a Лекарска комора list file: for conditional requests (ETag,
 * Last-Modified) and the audit trail. storage_path points at the raw file on
 * the private disk while it is kept (the last few batches).
 *
 * @property string $batch
 * @property string $url
 * @property string $label
 * @property string $status downloaded | not_modified
 * @property string|null $etag
 * @property string|null $last_modified
 * @property string|null $sha256
 * @property int|null $bytes
 * @property string|null $storage_path
 * @property Carbon|null $list_date
 * @property Carbon $fetched_at
 */
class KomoraLicenceDownload extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'list_date' => 'date',
            'fetched_at' => 'datetime',
        ];
    }
}
