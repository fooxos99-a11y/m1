<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject');
            $table->nullableMorphs('causer');
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
        });

        if (! Schema::hasTable('activity_logs')) {
            return;
        }

        $legacyRows = DB::table('activity_logs')->orderBy('created_at')->get();

        if ($legacyRows->isEmpty()) {
            return;
        }

        DB::table('activity_log')->insert($legacyRows->map(fn ($row) => [
            'log_name' => 'dashboard',
            'description' => $row->action,
            'subject_type' => null,
            'subject_id' => null,
            'causer_type' => null,
            'causer_id' => null,
            'properties' => json_encode([
                'target' => $row->target_name,
                'details' => $row->details,
                'actorName' => $row->actor_name,
                'actorRole' => $row->actor_role,
            ], JSON_UNESCAPED_UNICODE),
            'event' => $row->status,
            'batch_uuid' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->created_at,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};