<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic's own wording of a consent (Documentos y firmas, D4). Only the
 * text changes: the case fields and the signers stay those of Zooetia's
 * template. No row = Zooetia's text. New documents copy the text in force;
 * documents already created keep theirs (it is frozen in their snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->clinic();
            $table->string('type', 40);
            $table->jsonb('body');                              // [{text|list, when?}]
            $table->unsignedInteger('version')->default(1);     // bumps on every save
            $table->unsignedSmallInteger('base_version');       // Zooetia's version it was edited from
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['clinic_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
