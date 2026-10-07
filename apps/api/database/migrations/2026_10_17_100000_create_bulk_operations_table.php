<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * bulk_operations: one bulk publish of the import review queue („Објави ги
 * сите верификувани“, „Објави ги и неверификуваните од ФЗОМ“, a large
 * selection, or `import:publish`) running in the background, chunk by chunk.
 *
 * `up_to_id` is the snapshot the confirming modal counted (never more is
 * published); `item_ids` the explicit selection; `cursor` the last item
 * processed, so a stopped run resumes where it stopped. `failures` holds item
 * ids and the exception class/message only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_operations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('status', 16)->index();
            $table->string('driver', 16);
            $table->unsignedBigInteger('up_to_id')->nullable();
            $table->json('item_ids')->nullable();
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->json('failures')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('started_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_operations');
    }
};
