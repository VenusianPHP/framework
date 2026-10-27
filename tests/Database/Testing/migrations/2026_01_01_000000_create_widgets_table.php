<?php

use Venusian\Tests\Database\Fixtures\TestingHost;
use Voyager\Database\Migrations\Migration;
use Voyager\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        TestingHost::$migrated++;

        app('db')->connection()->getSchemaBuilder()->create('widgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        app('db')->connection()->getSchemaBuilder()->dropIfExists('widgets');
    }
};
