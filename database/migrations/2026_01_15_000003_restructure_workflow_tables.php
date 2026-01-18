<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add version and status to workflows table (if not exists)
        if (!Schema::hasColumn('workflows', 'version')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->integer('version')->default(1)->after('role_id');
            });
        }
        if (!Schema::hasColumn('workflows', 'status')) {
            Schema::table('workflows', function (Blueprint $table) {
                $table->string('status', 20)->default('DRAFT')->after('version');
            });
        }

        // 2. Add workflow_id to workflow_steps (new FK)
        if (!Schema::hasColumn('workflow_steps', 'workflow_id')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->foreignId('workflow_id')->nullable()->after('id');
            });
        }

        // 3. Migrate workflow_steps data
        if (Schema::hasTable('workflow_versions') && Schema::hasColumn('workflow_steps', 'workflow_version_id')) {
            DB::statement('
                UPDATE workflow_steps ws
                JOIN workflow_versions wv ON ws.workflow_version_id = wv.id
                SET ws.workflow_id = wv.workflow_id
            ');
        }

        // 4. Drop old FK and column from workflow_steps
        if (Schema::hasColumn('workflow_steps', 'workflow_version_id')) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->dropForeign(['workflow_version_id']);
                $table->dropColumn('workflow_version_id');
            });
        }

        // 5. Add FK constraint to workflow_steps.workflow_id
        $fkExists = DB::select("
            SELECT * FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'workflow_steps' 
            AND CONSTRAINT_NAME = 'workflow_steps_workflow_id_foreign'
        ");
        
        if (empty($fkExists)) {
            Schema::table('workflow_steps', function (Blueprint $table) {
                $table->foreign('workflow_id')
                    ->references('id')
                    ->on('workflows')
                    ->onDelete('cascade');
            });
        }

        // 6. Add workflow_id to documents table
        if (!Schema::hasColumn('documents', 'workflow_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreignId('workflow_id')->nullable()->after('template_document_id');
            });
        }

        // 7. Migrate documents data
        if (Schema::hasTable('workflow_versions') && Schema::hasColumn('documents', 'workflow_version_id')) {
            DB::statement('
                UPDATE documents d
                JOIN workflow_versions wv ON d.workflow_version_id = wv.id
                SET d.workflow_id = wv.workflow_id
            ');
        }

        // 8. Drop old FK from documents
        if (Schema::hasColumn('documents', 'workflow_version_id')) {
            Schema::table('documents', function (Blueprint $table) {
                $table->dropForeign(['workflow_version_id']);
                $table->dropColumn('workflow_version_id');
            });
        }

        // 9. Add FK constraint to documents.workflow_id
        $fkExists2 = DB::select("
            SELECT * FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            AND TABLE_NAME = 'documents' 
            AND CONSTRAINT_NAME = 'documents_workflow_id_foreign'
        ");
        
        if (empty($fkExists2)) {
            Schema::table('documents', function (Blueprint $table) {
                $table->foreign('workflow_id')
                    ->references('id')
                    ->on('workflows')
                    ->onDelete('set null');
            });
        }

        // 10. Drop workflow_versions table
        Schema::dropIfExists('workflow_versions');
    }

    public function down(): void
    {
        // Recreate workflow_versions table
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('template_id')->nullable()->constrained('template_documents')->onDelete('cascade');
            $table->integer('version')->default(1);
            $table->string('status', 20)->default('DRAFT');
            $table->timestamps();
        });

        // Restore documents.workflow_version_id
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);
            $table->dropColumn('workflow_id');
            $table->foreignId('workflow_version_id')->nullable()->after('template_document_id')
                ->constrained('workflow_versions')->onDelete('set null');
        });

        // Restore workflow_steps.workflow_version_id
        Schema::table('workflow_steps', function (Blueprint $table) {
            $table->dropForeign(['workflow_id']);
            $table->dropColumn('workflow_id');
            $table->foreignId('workflow_version_id')->nullable()->after('id')
                ->constrained('workflow_versions')->onDelete('cascade');
        });

        // Remove version and status from workflows
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn(['version', 'status']);
        });
    }
};
