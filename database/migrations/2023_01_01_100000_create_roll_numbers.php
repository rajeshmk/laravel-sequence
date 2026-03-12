<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roll_numbers', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);

            // Parent model class (fully-qualified class name). In the public API this is
            // referred to as "parentClass" (configured via RollNumberConfig::groupBy()).
            //
            // NOTE: We intentionally store "no grouping" as an empty string (not NULL), because
            // NULLs don’t enforce uniqueness in composite UNIQUE indexes (most DBs treat
            // NULL != NULL), which would allow multiple "ungrouped" rows for the same name.
            $table->string('grouping_type', 250)->default('');

            // Parent model primary key value (stored as string). In the public API this is
            // referred to as "parentId".
            //
            // NOTE: This is a string to support UUID/ULID/string PKs, and to avoid type
            // mismatches when different models use different PK types.
            $table->string('grouping_id', 100)->default('');

            $table->unsignedBigInteger('last_number');
            $table->timestamps();

            $table->unique([
                'name',
                'grouping_type',
                'grouping_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roll_numbers');
    }
};
