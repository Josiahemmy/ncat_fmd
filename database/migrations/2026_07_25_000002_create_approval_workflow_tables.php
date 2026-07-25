<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configurable approval workflow (spec §12.1). A workflow per document type
 * holds ordered levels; each level is bound to exactly one permission OR one
 * role. `requisition_approvals` is the per-document snapshot: one row per level
 * is materialised when the document is submitted, carrying the level's name and
 * binding denormalised so later admin edits (or a deleted level) cannot change
 * or corrupt an in-flight document's chain.
 *
 * The seeded default is a single level bound to `requisitions.approve`, which
 * reproduces the pre-migration single-approval behaviour exactly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('document_type');          // 'requisition' today; PO/RO later
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['document_type', 'is_active']);
        });

        Schema::create('approval_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('name');                   // e.g. "HOD Approval"
            // Exactly one of these is set; enforced by the service + validation.
            $table->string('permission_name')->nullable();
            $table->string('role_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['approval_workflow_id', 'sequence']);
        });

        Schema::create('requisition_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            // Traceability only. The snapshot below is what the engine resolves
            // against, so deleting a level never breaks an in-flight chain.
            $table->foreignId('approval_level_id')->nullable()
                ->constrained('approval_levels')->nullOnDelete();

            // A rejected requisition can be edited and re-submitted; each pass
            // is its own cycle so earlier decisions stay readable as history.
            $table->unsignedSmallInteger('cycle')->default(1);
            $table->unsignedSmallInteger('sequence');
            $table->string('level_name');
            $table->string('permission_name')->nullable();
            $table->string('role_name')->nullable();

            $table->enum('decision', ['approve', 'reject'])->nullable();
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['requisition_id', 'cycle', 'sequence']);
            $table->index(['requisition_id', 'decision']);
        });

        $workflowId = DB::table('approval_workflows')->insertGetId([
            'document_type' => 'requisition',
            'name' => 'Requisition approval',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = DB::table('approval_levels')->insertGetId([
            'approval_workflow_id' => $workflowId,
            'sequence' => 1,
            'name' => 'Approval',
            'permission_name' => 'requisitions.approve',
            'role_name' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->backfill($levelId);
    }

    /**
     * Every requisition that already carries a decision gets a matching record
     * against the default level, so the decision trail renders for legacy rows.
     * Submitted-but-undecided requisitions are left alone: the engine
     * materialises their snapshot lazily on first read.
     */
    protected function backfill(int $levelId): void
    {
        DB::table('requisitions')
            ->whereIn('status', ['approved', 'rejected', 'issued', 'closed'])
            ->orderBy('id')
            ->select('id', 'status', 'approved_by_user_id', 'approved_at', 'rejected_at', 'approval_remarks', 'created_at')
            ->chunk(200, function ($rows) use ($levelId) {
                $now = now();
                $records = [];

                foreach ($rows as $r) {
                    // issued/closed only follow a full approval.
                    $rejected = $r->status === 'rejected';

                    $records[] = [
                        'requisition_id' => $r->id,
                        'approval_level_id' => $levelId,
                        'cycle' => 1,
                        'sequence' => 1,
                        'level_name' => 'Approval',
                        'permission_name' => 'requisitions.approve',
                        'role_name' => null,
                        'decision' => $rejected ? 'reject' : 'approve',
                        'decided_by_user_id' => $r->approved_by_user_id,
                        'decided_at' => ($rejected ? $r->rejected_at : $r->approved_at) ?? $r->created_at,
                        'remarks' => $r->approval_remarks,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($records) {
                    DB::table('requisition_approvals')->insert($records);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_approvals');
        Schema::dropIfExists('approval_levels');
        Schema::dropIfExists('approval_workflows');
    }
};
